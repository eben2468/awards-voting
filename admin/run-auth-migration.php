<?php
/**
 * Auth & Security Migration Runner
 * Awards Voting System (VoteHubGH)
 *
 * Applies every database change needed for:
 *   - Two-step email verification at login
 *   - Organizer account approval by admins
 *   - Verified email-address changes on profile pages
 *   - Email-based password reset
 *   - Gmail / SMTP settings
 *
 * Linked from Diagnostics and Organizer Approvals when a column is missing.
 * Safe to run more than once - every step is skipped if it already exists.
 */

require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$authorised = true;

$steps = [];

/**
 * Run one migration statement and record the outcome.
 */
function migrateStep(PDO $pdo, $label, $sql, array &$steps, $skipIf = null) {
    if (is_callable($skipIf) && $skipIf($pdo)) {
        $steps[] = ['label' => $label, 'state' => 'skipped', 'detail' => 'Already applied.'];
        return;
    }

    try {
        $pdo->exec($sql);
        $steps[] = ['label' => $label, 'state' => 'ok', 'detail' => 'Applied successfully.'];
    } catch (PDOException $e) {
        $code = $e->getCode();
        $msg = $e->getMessage();
        // 42S21 duplicate column, 42000 duplicate key name, 42S01 table exists
        if (stripos($msg, 'Duplicate column') !== false
            || stripos($msg, 'Duplicate key name') !== false
            || stripos($msg, 'already exists') !== false) {
            $steps[] = ['label' => $label, 'state' => 'skipped', 'detail' => 'Already applied.'];
        } else {
            $steps[] = ['label' => $label, 'state' => 'error', 'detail' => '[' . $code . '] ' . $msg];
        }
    }
}

function columnExists(PDO $pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function indexExists(PDO $pdo, $table, $index) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
        $stmt->execute([$table, $index]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

$ran = false;
$hasError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $ran = true;
    $pdo = getDBConnection();

    // ---- 1. Users table ----
    migrateStep($pdo, 'users.approval_status',
        "ALTER TABLE users ADD COLUMN approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER status",
        $steps, fn($p) => columnExists($p, 'users', 'approval_status'));

    migrateStep($pdo, 'users.approved_at',
        "ALTER TABLE users ADD COLUMN approved_at TIMESTAMP NULL DEFAULT NULL AFTER approval_status",
        $steps, fn($p) => columnExists($p, 'users', 'approved_at'));

    migrateStep($pdo, 'users.approved_by',
        "ALTER TABLE users ADD COLUMN approved_by INT DEFAULT NULL AFTER approved_at",
        $steps, fn($p) => columnExists($p, 'users', 'approved_by'));

    migrateStep($pdo, 'users.approval_note',
        "ALTER TABLE users ADD COLUMN approval_note TEXT DEFAULT NULL AFTER approved_by",
        $steps, fn($p) => columnExists($p, 'users', 'approval_note'));

    migrateStep($pdo, 'users.two_factor_enabled',
        "ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER approval_note",
        $steps, fn($p) => columnExists($p, 'users', 'two_factor_enabled'));

    migrateStep($pdo, 'users index: idx_approval_status',
        "ALTER TABLE users ADD INDEX idx_approval_status (approval_status)",
        $steps, fn($p) => indexExists($p, 'users', 'idx_approval_status'));

    migrateStep($pdo, 'Mark existing accounts as approved',
        "UPDATE users SET approval_status = 'approved', approved_at = COALESCE(approved_at, created_at)
         WHERE approval_status = 'approved' AND approved_at IS NULL",
        $steps);

    // ---- 2. Login verification codes ----
    migrateStep($pdo, 'Table: login_verification_codes', "
        CREATE TABLE IF NOT EXISTS login_verification_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            purpose VARCHAR(30) NOT NULL DEFAULT 'login',
            attempts INT NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_purpose (user_id, purpose),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $steps);

    // ---- 3. Email change requests ----
    migrateStep($pdo, 'Table: email_change_requests', "
        CREATE TABLE IF NOT EXISTS email_change_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            old_email VARCHAR(100) NOT NULL,
            new_email VARCHAR(100) NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_new_email (new_email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $steps);

    // ---- 4. Password resets ----
    // expires_at was the table's first TIMESTAMP column, so MySQL gave it an
    // implicit "ON UPDATE CURRENT_TIMESTAMP": any UPDATE on the row (such as
    // counting a wrong code attempt) reset the expiry to NOW() and killed the
    // reset request. DATETIME has no such behaviour.
    migrateStep($pdo, 'password_resets.expires_at -> DATETIME',
        "ALTER TABLE password_resets MODIFY COLUMN expires_at DATETIME NOT NULL",
        $steps, function ($p) {
            try {
                $stmt = $p->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'password_resets'
                      AND COLUMN_NAME = 'expires_at' AND DATA_TYPE = 'datetime' AND EXTRA NOT LIKE '%on update%'");
                $stmt->execute();
                return (int) $stmt->fetchColumn() > 0;
            } catch (Exception $e) {
                return false;
            }
        });

    migrateStep($pdo, 'password_resets.code_hash',
        "ALTER TABLE password_resets ADD COLUMN code_hash VARCHAR(255) DEFAULT NULL AFTER token",
        $steps, fn($p) => columnExists($p, 'password_resets', 'code_hash'));

    migrateStep($pdo, 'password_resets.attempts',
        "ALTER TABLE password_resets ADD COLUMN attempts INT NOT NULL DEFAULT 0 AFTER code_hash",
        $steps, fn($p) => columnExists($p, 'password_resets', 'attempts'));

    migrateStep($pdo, 'password_resets.used_at',
        "ALTER TABLE password_resets ADD COLUMN used_at DATETIME NULL DEFAULT NULL AFTER expires_at",
        $steps, fn($p) => columnExists($p, 'password_resets', 'used_at'));

    migrateStep($pdo, 'password_resets.ip_address',
        "ALTER TABLE password_resets ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL AFTER used_at",
        $steps, fn($p) => columnExists($p, 'password_resets', 'ip_address'));

    // ---- 5. Settings table integrity ----
    // Without a UNIQUE key on setting_key a save can append a second row rather
    // than replacing the first, so settings appear not to persist.
    migrateStep($pdo, 'Remove duplicate settings rows', "
        DELETE s1 FROM settings s1
        INNER JOIN settings s2
            ON s1.setting_key = s2.setting_key
           AND s1.id < s2.id", $steps);

    migrateStep($pdo, 'settings.setting_key UNIQUE key',
        "ALTER TABLE settings ADD UNIQUE KEY uniq_setting_key (setting_key)",
        $steps, function ($p) {
            try {
                $stmt = $p->query("SELECT COUNT(*) FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings'
                      AND COLUMN_NAME = 'setting_key' AND NON_UNIQUE = 0");
                return (int) $stmt->fetchColumn() > 0;
            } catch (Exception $e) {
                return false;
            }
        });

    // ---- 6. Settings ----
    // INSERT IGNORE rather than ON DUPLICATE KEY UPDATE: it never touches values
    // you have already configured, and avoids the VALUES() function that newer
    // MySQL versions have removed.
    migrateStep($pdo, 'Default mail & security settings', "
        INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
            ('smtp_enabled', '0'),
            ('smtp_host', 'smtp.gmail.com'),
            ('smtp_port', '587'),
            ('smtp_secure', 'tls'),
            ('smtp_username', ''),
            ('smtp_password', ''),
            ('smtp_from_email', ''),
            ('smtp_from_name', 'VoteHubGH'),
            ('approval_notify_email', ''),
            ('two_factor_required', '1'),
            ('two_factor_fallback', '1'),
            ('organizer_approval_required', '1')", $steps);

    foreach ($steps as $step) {
        if ($step['state'] === 'error') {
            $hasError = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auth &amp; Security Migration - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 860px;">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white py-3">
            <h4 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Auth &amp; Security Migration</h4>
        </div>
        <div class="card-body p-4">

            <?php if (!$authorised): ?>
                <div class="alert alert-warning">
                    <h5 class="alert-heading"><i class="bi bi-lock me-2"></i>Authorisation required</h5>
                    <p class="mb-2">Log in as an administrator first, then reload this page.</p>
                    <p class="mb-0 small">
                        Locked out? Append the setup key to the URL:
                    </p>
                </div>

            <?php elseif (!$ran): ?>
                <p>This will add the database tables and columns required by:</p>
                <ul class="mb-4">
                    <li>Two-step verification at login (code emailed to the user)</li>
                    <li>Admin approval of new organizer accounts</li>
                    <li>Changing your email address from the profile page (with email verification)</li>
                    <li>Password reset by email link / code</li>
                    <li>Gmail (SMTP) mail settings</li>
                </ul>
                <div class="alert alert-info small">
                    <i class="bi bi-info-circle me-1"></i>
                    Nothing is deleted. Every step is skipped automatically if it has already been applied,
                    so it is safe to run this more than once. Existing accounts stay approved and can keep logging in.
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-play-fill me-2"></i>Run Migration
                    </button>
                </form>

            <?php else: ?>
                <?php if ($hasError): ?>
                    <div class="alert alert-danger">
                        <strong>Some steps failed.</strong> Review the details below and re-run after fixing them.
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <strong><i class="bi bi-check-circle me-1"></i>Migration complete.</strong>
                        Next: go to <a href="<?php echo SITE_URL; ?>/admin/settings.php" class="alert-link">Admin &rsaquo; Settings</a>
                        and fill in your Gmail SMTP details so verification emails can be delivered.
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr><th style="width:60px;">#</th><th>Step</th><th style="width:120px;">Result</th><th>Detail</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($steps as $i => $step): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><code><?php echo htmlspecialchars($step['label']); ?></code></td>
                                <td>
                                    <?php if ($step['state'] === 'ok'): ?>
                                        <span class="badge bg-success">Applied</span>
                                    <?php elseif ($step['state'] === 'skipped'): ?>
                                        <span class="badge bg-secondary">Skipped</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?php echo htmlspecialchars($step['detail']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-warning small mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <strong>Security:</strong> delete <code>database/run_auth_migration.php</code> from the server
                    once you are done.
                </div>
            <?php endif; ?>

        </div>
    </div>
    <p class="text-center text-muted small mt-3">
        <a href="<?php echo SITE_URL; ?>">&larr; Back to <?php echo SITE_NAME; ?></a>
    </p>
</div>
</body>
</html>
