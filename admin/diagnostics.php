<?php
/**
 * Admin - Diagnostics
 * Awards Voting System (VoteHubGH)
 *
 * Self-check page for the live server. Confirms the auth/email upgrade is
 * correctly installed and shows the real reason behind any failure:
 *   - are the migration tables/columns present?
 *   - can settings actually be written to the database?
 *   - is the SMTP host reachable from this server?
 *   - do PHP and MySQL agree on the time?
 *
 * Safe to leave installed (admin-only), but you may delete it once happy.
 */

require_once '../config/database.php';
require_once '../includes/auth-functions.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied. Admin privileges required.');
    redirect('login.php');
}

$page_title = 'Diagnostics';
$pdo = getDBConnection();

$checks = [];

/**
 * Record one diagnostic result.
 * $state: ok | warn | fail | info
 */
function addCheck(&$checks, $group, $label, $state, $detail, $fix = '') {
    $checks[] = [
        'group'  => $group,
        'label'  => $label,
        'state'  => $state,
        'detail' => $detail,
        'fix'    => $fix,
    ];
}

function colExists(PDO $pdo, $table, $column) {
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $s->execute([$table, $column]);
        return (int) $s->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function tableExists(PDO $pdo, $table) {
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $s->execute([$table]);
        return (int) $s->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// =====================================================================
// 1. Database migration state
// =====================================================================
$migrationUrl = SITE_URL . '/admin/run-auth-migration.php';

foreach (['approval_status', 'approved_at', 'approved_by', 'approval_note', 'two_factor_enabled'] as $col) {
    $ok = colExists($pdo, 'users', $col);
    addCheck($checks, 'Database', "users.$col", $ok ? 'ok' : 'fail',
        $ok ? 'Present' : 'Missing',
        $ok ? '' : 'Run the migration: ' . $migrationUrl);
}

foreach (['login_verification_codes', 'email_change_requests'] as $tbl) {
    $ok = tableExists($pdo, $tbl);
    addCheck($checks, 'Database', "Table $tbl", $ok ? 'ok' : 'fail',
        $ok ? 'Present' : 'Missing',
        $ok ? '' : 'Run the migration: ' . $migrationUrl);
}

foreach (['code_hash', 'attempts', 'used_at'] as $col) {
    $ok = colExists($pdo, 'password_resets', $col);
    addCheck($checks, 'Database', "password_resets.$col", $ok ? 'ok' : 'fail',
        $ok ? 'Present' : 'Missing',
        $ok ? '' : 'Run the migration: ' . $migrationUrl);
}

// password_resets.expires_at must NOT auto-update, or resets die instantly
try {
    $s = $pdo->query("SELECT DATA_TYPE, EXTRA FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'password_resets' AND COLUMN_NAME = 'expires_at'");
    $row = $s->fetch();
    if (!$row) {
        addCheck($checks, 'Database', 'password_resets.expires_at', 'fail', 'Column missing',
            'Run the migration: ' . $migrationUrl);
    } else {
        $autoUpdate = stripos($row['EXTRA'] ?? '', 'on update') !== false;
        addCheck($checks, 'Database', 'password_resets.expires_at type',
            $autoUpdate ? 'fail' : 'ok',
            strtoupper($row['DATA_TYPE']) . ($autoUpdate ? ' with ON UPDATE CURRENT_TIMESTAMP' : ' (no auto-update)'),
            $autoUpdate
                ? 'MySQL auto-updates this column, which expires a reset request the moment a wrong code is typed. '
                  . 'Fix with: ALTER TABLE password_resets MODIFY COLUMN expires_at DATETIME NOT NULL;'
                : '');
    }
} catch (Exception $e) {
    addCheck($checks, 'Database', 'password_resets.expires_at', 'warn', $e->getMessage());
}

// =====================================================================
// 2. Settings table: can we actually write?
// =====================================================================
try {
    $s = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings'
          AND COLUMN_NAME = 'setting_key' AND NON_UNIQUE = 0");
    $hasUnique = (int) $s->fetchColumn() > 0;
    addCheck($checks, 'Settings', 'settings.setting_key unique index',
        $hasUnique ? 'ok' : 'warn',
        $hasUnique ? 'Present' : 'Missing (duplicate rows possible)',
        $hasUnique ? '' : 'ALTER TABLE settings ADD UNIQUE KEY setting_key (setting_key);');
} catch (Exception $e) {
    addCheck($checks, 'Settings', 'settings unique index', 'warn', $e->getMessage());
}

// Duplicate keys silently shadow each other
try {
    $dups = $pdo->query("SELECT setting_key, COUNT(*) c FROM settings GROUP BY setting_key HAVING c > 1")->fetchAll();
    addCheck($checks, 'Settings', 'Duplicate setting rows',
        empty($dups) ? 'ok' : 'fail',
        empty($dups) ? 'None' : count($dups) . ' duplicated key(s): '
            . implode(', ', array_map(fn($d) => $d['setting_key'] . ' x' . $d['c'], $dups)),
        empty($dups) ? '' : 'Delete the older duplicate rows in phpMyAdmin, then add the unique index.');
} catch (Exception $e) {
    addCheck($checks, 'Settings', 'Duplicate setting rows', 'warn', $e->getMessage());
}

// The real test: write a probe value and read it back
$probeKey = '__diag_probe';
$probeValue = 'probe-' . bin2hex(random_bytes(4));
$writeOk = saveSetting($probeKey, $probeValue);
$readBack = getSetting($probeKey, '');

addCheck($checks, 'Settings', 'Write a setting and read it back',
    ($writeOk && $readBack === $probeValue) ? 'ok' : 'fail',
    ($writeOk && $readBack === $probeValue)
        ? 'Settings save correctly on this server.'
        : 'Write failed. Wrote "' . e($probeValue) . '", read back "' . e((string) $readBack) . '". '
            . 'Database reply: ' . e(lastSettingError() ?: 'none'),
    ($writeOk && $readBack === $probeValue) ? ''
        : 'The database user may lack UPDATE/INSERT rights on the settings table.');

// How close this account is to its connection limits. Shared hosts set these per
// user, and going over makes saves fail at some moments and work at others.
try {
    $limits = [];
    $grants = implode(' ', $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN));
    if (preg_match_all('/MAX_(USER_CONNECTIONS|CONNECTIONS_PER_HOUR)\s+(\d+)/i', $grants, $found, PREG_SET_ORDER)) {
        foreach ($found as $limit) {
            $limits[strtoupper($limit[1])] = (int) $limit[2];
        }
    }

    $simultaneous = $limits['USER_CONNECTIONS'] ?? 0;
    if ($simultaneous === 0) {
        $simultaneous = (int) $pdo->query('SELECT @@max_user_connections')->fetchColumn();
    }
    $openNow = count($pdo->query('SHOW PROCESSLIST')->fetchAll());
    $hourly = $limits['CONNECTIONS_PER_HOUR'] ?? 0;

    $nearLimit = $simultaneous > 0 && $openNow >= $simultaneous * 0.8;
    addCheck($checks, 'Settings', 'Database connections',
        $nearLimit ? 'warn' : 'ok',
        'Open now: ' . $openNow . ' of ' . ($simultaneous > 0 ? $simultaneous : 'no set limit')
            . '. Hourly limit: ' . ($hourly > 0 ? $hourly : 'none set') . '.',
        $nearLimit ? 'The site is close to its limit. Wait a minute and refresh this page.' : '');
} catch (Exception $e) {
    addCheck($checks, 'Settings', 'Database connections', 'info', 'Could not read the limits: ' . $e->getMessage());
}

// Tidy the probe away
try {
    $pdo->prepare("DELETE FROM settings WHERE setting_key = ?")->execute([$probeKey]);
    clearSettingCache();
} catch (Exception $e) {
    // harmless
}

// Show what the mail settings currently hold
$mailKeys = ['smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_secure', 'smtp_username',
             'smtp_password', 'smtp_from_email', 'smtp_from_name'];
$storedMail = [];
foreach ($mailKeys as $k) {
    $v = getSetting($k, null);
    if ($v === null) {
        $storedMail[$k] = '(row missing)';
    } elseif ($k === 'smtp_password') {
        $storedMail[$k] = $v === '' ? '(empty)' : '(set, ' . strlen($v) . ' chars)';
    } else {
        $storedMail[$k] = $v === '' ? '(empty)' : $v;
    }
}

// =====================================================================
// 3. Mail configuration & connectivity
// =====================================================================
$cfg = getMailConfig(true);

addCheck($checks, 'Email', 'SMTP enabled', $cfg['enabled'] ? 'ok' : 'warn',
    $cfg['enabled'] ? 'Yes' : 'No - verification emails cannot be sent',
    $cfg['enabled'] ? '' : 'Tick "Send email through SMTP" in Settings, then save.');

addCheck($checks, 'Email', 'SMTP username', $cfg['username'] !== '' ? 'ok' : 'warn',
    $cfg['username'] !== '' ? $cfg['username'] : 'Not set',
    $cfg['username'] !== '' ? '' : 'Enter your Gmail address in Settings.');

addCheck($checks, 'Email', 'App password', $cfg['password'] !== '' ? 'ok' : 'warn',
    $cfg['password'] !== '' ? 'Stored (' . strlen($cfg['password']) . ' characters)' : 'Not set',
    $cfg['password'] !== ''
        ? (strlen($cfg['password']) === 16 ? '' : 'Gmail app passwords are 16 characters - this one is ' . strlen($cfg['password']) . '.')
        : 'Create one at https://myaccount.google.com/apppasswords and paste it in Settings.');

addCheck($checks, 'Email', 'PHP openssl extension', extension_loaded('openssl') ? 'ok' : 'fail',
    extension_loaded('openssl') ? 'Loaded' : 'Missing - TLS/SSL cannot work',
    extension_loaded('openssl') ? '' : 'Ask your host to enable the PHP openssl extension.');

addCheck($checks, 'Email', 'allow_url_fopen / sockets',
    function_exists('stream_socket_client') ? 'ok' : 'fail',
    function_exists('stream_socket_client') ? 'stream_socket_client() available' : 'Disabled',
    function_exists('stream_socket_client') ? '' : 'Ask your host to allow outgoing socket connections.');

// Can we actually open a TCP connection to the SMTP host?
if ($cfg['host'] !== '') {
    $target = ($cfg['secure'] === 'ssl' ? 'ssl://' : '') . $cfg['host'] . ':' . $cfg['port'];
    $errno = 0; $errstr = '';
    $t0 = microtime(true);
    $sock = @stream_socket_client(
        $target, $errno, $errstr, 8, STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
    );
    $ms = round((microtime(true) - $t0) * 1000);

    if ($sock) {
        $greeting = trim((string) @fgets($sock, 512));
        @fclose($sock);
        addCheck($checks, 'Email', 'Connect to ' . $target, 'ok',
            'Connected in ' . $ms . 'ms. Server said: ' . e(substr($greeting, 0, 90)));
    } else {
        addCheck($checks, 'Email', 'Connect to ' . $target, 'fail',
            'Failed after ' . $ms . 'ms (' . $errno . ': ' . e($errstr) . ')',
            'Your host is probably blocking outgoing SMTP. Try port 465 with SSL encryption instead of 587/STARTTLS, '
            . 'or ask your host to open outbound SMTP.');
    }
}

// =====================================================================
// 4. Clock agreement (a mismatch silently expires every code)
// =====================================================================
try {
    $dbNow = $pdo->query("SELECT NOW()")->fetchColumn();
    $skew = abs(strtotime($dbNow) - time());
    addCheck($checks, 'Environment', 'PHP vs MySQL clock',
        $skew <= 120 ? 'ok' : 'info',
        'PHP: ' . date('Y-m-d H:i:s') . ' (' . date_default_timezone_get() . ') | MySQL: ' . $dbNow
            . ' | difference: ' . $skew . 's',
        $skew <= 120 ? ''
            : 'Harmless: all expiry checks are done inside SQL, so the two clocks do not need to agree.');
} catch (Exception $e) {
    addCheck($checks, 'Environment', 'PHP vs MySQL clock', 'warn', $e->getMessage());
}

addCheck($checks, 'Environment', 'PHP version', version_compare(PHP_VERSION, '7.4', '>=') ? 'ok' : 'fail',
    PHP_VERSION, version_compare(PHP_VERSION, '7.4', '>=') ? '' : 'PHP 7.4 or newer is required.');

try {
    addCheck($checks, 'Environment', 'MySQL version', 'info', $pdo->query('SELECT VERSION()')->fetchColumn());
} catch (Exception $e) {
    addCheck($checks, 'Environment', 'MySQL version', 'warn', $e->getMessage());
}

addCheck($checks, 'Environment', 'Detected site URL', 'info', SITE_URL,
    'Reset links in emails are built from this. It must match your real domain.');

// =====================================================================
// 5. Feature switches & pending queue
// =====================================================================
addCheck($checks, 'Features', 'Two-step verification', isTwoFactorRequired() ? 'ok' : 'warn',
    isTwoFactorRequired() ? 'Required at login' : 'Disabled');

addCheck($checks, 'Features', 'Login fallback when email fails',
    (string) getSetting('two_factor_fallback', '1') === '1' ? 'info' : 'ok',
    (string) getSetting('two_factor_fallback', '1') === '1'
        ? 'ON - users can still sign in if the code email fails (prevents lockout)'
        : 'OFF - a mail outage will block all logins',
    (string) getSetting('two_factor_fallback', '1') === '1'
        ? 'Turn this off only once a test email has arrived successfully.' : '');

addCheck($checks, 'Features', 'Organizer approval', isOrganizerApprovalRequired() ? 'ok' : 'warn',
    isOrganizerApprovalRequired() ? 'New organizers need admin approval' : 'Disabled - organizers log in immediately');

addCheck($checks, 'Features', 'Organizers awaiting approval', 'info',
    getPendingOrganizerCount() . ' pending');

try {
    addCheck($checks, 'Features', 'Admins notified on new registration', 'info',
        implode(', ', getAdminNotificationEmails()) ?: 'none found');
} catch (Exception $e) {
    addCheck($checks, 'Features', 'Admin notification list', 'warn', $e->getMessage());
}

// Tally
$counts = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
foreach ($checks as $c) {
    $counts[$c['state']]++;
}

$groups = [];
foreach ($checks as $c) {
    $groups[$c['group']][] = $c;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>

    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/logo/favicon.png?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/portal.css'); ?>">
    <style>
        .diag-row { border-left: 4px solid transparent; }
        .diag-row.ok   { border-left-color: #198754; }
        .diag-row.warn { border-left-color: #ffc107; }
        .diag-row.fail { border-left-color: #dc3545; }
        .diag-row.info { border-left-color: #0dcaf0; }
        .diag-fix { font-size: .82rem; }
        code { word-break: break-all; }
    </style>
</head>
<body class="admin-body">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-main">
        <?php include 'includes/topbar.php'; ?>

        <div class="admin-content p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                    <h2 class="mb-1">Diagnostics</h2>
                    <p class="text-muted mb-0">Self-check for the login security &amp; email upgrade.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?php echo SITE_URL; ?>/admin/settings.php" class="btn btn-outline-secondary">
                        <i class="bi bi-gear me-1"></i>Settings
                    </a>
                    <a href="" class="btn btn-primary"><i class="bi bi-arrow-clockwise me-1"></i>Re-run</a>
                </div>
            </div>

            <!-- Summary -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="h2 mb-0 text-success fw-bold"><?php echo $counts['ok']; ?></div>
                            <div class="small text-muted">Passing</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="h2 mb-0 text-danger fw-bold"><?php echo $counts['fail']; ?></div>
                            <div class="small text-muted">Needs fixing</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="h2 mb-0 text-warning fw-bold"><?php echo $counts['warn']; ?></div>
                            <div class="small text-muted">Warnings</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="h2 mb-0 text-info fw-bold"><?php echo $counts['info']; ?></div>
                            <div class="small text-muted">Information</div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($counts['fail'] > 0): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong><?php echo $counts['fail']; ?> check<?php echo $counts['fail'] === 1 ? '' : 's'; ?> failed.</strong>
                    Each failure below has a suggested fix.
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>No failures.</strong> The upgrade is installed correctly on this server.
                </div>
            <?php endif; ?>

            <!-- Checks -->
            <?php foreach ($groups as $groupName => $groupChecks): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><?php echo e($groupName); ?></h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($groupChecks as $c): ?>
                            <div class="list-group-item diag-row <?php echo $c['state']; ?>">
                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                    <div class="flex-grow-1" style="min-width:240px;">
                                        <div class="fw-semibold"><?php echo e($c['label']); ?></div>
                                        <div class="small text-muted"><?php echo $c['detail']; ?></div>
                                        <?php if ($c['fix']): ?>
                                            <div class="diag-fix mt-2 p-2 bg-light rounded border">
                                                <i class="bi bi-tools me-1 text-primary"></i><?php echo $c['fix']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge bg-<?php echo match($c['state']) {
                                        'ok' => 'success', 'fail' => 'danger', 'warn' => 'warning text-dark', default => 'info'
                                    }; ?>">
                                        <?php echo match($c['state']) {
                                            'ok' => 'OK', 'fail' => 'FAIL', 'warn' => 'WARN', default => 'INFO'
                                        }; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Raw stored mail settings -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Mail settings actually stored in the database</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Key</th><th>Stored value</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($storedMail as $k => $v): ?>
                            <tr>
                                <td><code><?php echo e($k); ?></code></td>
                                <td class="<?php echo ($v === '(empty)' || $v === '(row missing)') ? 'text-danger' : ''; ?>">
                                    <?php echo e($v); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white small text-muted">
                    If these are empty right after you pressed <em>Save Email Settings</em>, the write is failing &mdash;
                    look at the "Write a setting and read it back" check above.
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
</body>
</html>
