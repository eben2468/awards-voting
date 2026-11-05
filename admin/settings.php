<?php
/**
 * Admin - Settings
 * Awards Voting System
 */

require_once '../config/database.php';
require_once '../includes/auth-functions.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$page_title = 'Settings';
$pdo = getDBConnection();

/**
 * Load every setting this page manages.
 */
$loadSettings = function () {
    return [
        'site_name'                   => getSetting('site_name', SITE_NAME),
        'site_url'                    => getSetting('site_url', SITE_URL),
        'admin_email'                 => getSetting('admin_email', ADMIN_EMAIL),
        'show_vote_counts'            => getSetting('show_vote_counts', '1'),
        // Mail
        'smtp_enabled'                => getSetting('smtp_enabled', '0'),
        'smtp_host'                   => getSetting('smtp_host', 'smtp.gmail.com'),
        'smtp_port'                   => getSetting('smtp_port', '587'),
        'smtp_secure'                 => getSetting('smtp_secure', 'tls'),
        'smtp_username'               => getSetting('smtp_username', ''),
        'smtp_password'               => getSetting('smtp_password', ''),
        'smtp_from_email'             => getSetting('smtp_from_email', ''),
        'smtp_from_name'              => getSetting('smtp_from_name', SITE_NAME),
        // Security
        'two_factor_required'         => getSetting('two_factor_required', '1'),
        'two_factor_fallback'         => getSetting('two_factor_fallback', '1'),
        'organizer_approval_required' => getSetting('organizer_approval_required', '1'),
        'approval_notify_email'       => getSetting('approval_notify_email', ''),
    ];
};

$settings = $loadSettings();

$success = false;
$message = '';
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
    } else {
        $formSection = $_POST['form_section'] ?? 'general';

        // Collects any key that could not be written, so a failed save is never
        // reported to the admin as a success.
        $writeFailures = [];
        $put = function ($key, $value) use (&$writeFailures) {
            if (!saveSetting($key, $value)) {
                $writeFailures[] = $key;
            }
        };

        try {
            // ---------------- General ----------------
            if ($formSection === 'general') {
                $put('site_name', $_POST['site_name'] ?? SITE_NAME);
                $put('site_url', $_POST['site_url'] ?? SITE_URL);
                $put('admin_email', $_POST['admin_email'] ?? ADMIN_EMAIL);

                $success = empty($writeFailures);
                $message = 'General settings updated successfully.';
            }

            // ---------------- Email (SMTP) ----------------
            if ($formSection === 'mail') {
                $put('smtp_enabled', isset($_POST['smtp_enabled']) ? '1' : '0');
                $put('smtp_host', trim($_POST['smtp_host'] ?? 'smtp.gmail.com'));
                $put('smtp_port', (string) intval($_POST['smtp_port'] ?? 587));
                $put('smtp_secure', in_array($_POST['smtp_secure'] ?? 'tls', ['tls', 'ssl', 'none'], true)
                    ? $_POST['smtp_secure'] : 'tls');
                $put('smtp_username', trim($_POST['smtp_username'] ?? ''));
                $put('smtp_from_email', trim($_POST['smtp_from_email'] ?? ''));
                $put('smtp_from_name', trim($_POST['smtp_from_name'] ?? SITE_NAME));

                // Only overwrite the app password when a new one is typed in
                $newPassword = str_replace(' ', '', $_POST['smtp_password'] ?? '');
                if ($newPassword !== '') {
                    $put('smtp_password', $newPassword);
                }

                $success = empty($writeFailures);
                $message = 'Email (SMTP) settings saved.';
            }

            // ---------------- Security ----------------
            if ($formSection === 'security') {
                $put('two_factor_required', isset($_POST['two_factor_required']) ? '1' : '0');
                $put('two_factor_fallback', isset($_POST['two_factor_fallback']) ? '1' : '0');
                $put('organizer_approval_required', isset($_POST['organizer_approval_required']) ? '1' : '0');
                $put('approval_notify_email', trim($_POST['approval_notify_email'] ?? ''));

                $success = empty($writeFailures);
                $message = 'Security settings saved.';
            }

            if (!empty($writeFailures)) {
                $message = 'These settings could not be saved: <strong>'
                    . htmlspecialchars(implode(', ', $writeFailures)) . '</strong>. '
                    . 'Open <a href="' . SITE_URL . '/admin/diagnostics.php" class="alert-link">Diagnostics</a> '
                    . 'to see the exact database error.';
            }

            // ---------------- Send a test email ----------------
            if ($formSection === 'test_mail') {
                $testTo = trim($_POST['test_email'] ?? '');

                if (!filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
                    $testResult = ['ok' => false, 'text' => 'Please enter a valid email address to test with.'];
                } else {
                    $body = '<p>Hello,</p>
                        <p>If you are reading this, your ' . htmlspecialchars(SITE_NAME) . ' mail settings are
                        working correctly. Login verification codes, password resets and organizer approval
                        notices will be delivered from this address.</p>
                        <p style="font-size:13px; color:#777;">Sent ' . date('F j, Y \a\t g:i A') . '</p>';

                    $sent = sendMailMessage(
                        $testTo,
                        SITE_NAME . ' test email',
                        buildEmailTemplate('Test Email', $body, 'Your mail settings are working'),
                        ['freshConfig' => true]
                    );

                    $testResult = $sent
                        ? ['ok' => true, 'text' => 'Test email sent to ' . htmlspecialchars($testTo) . '. Check the inbox (and spam folder).']
                        : ['ok' => false, 'text' => 'Could not send: ' . htmlspecialchars(getLastMailError() ?: 'unknown error')];
                }
            }

            $settings = $loadSettings();
        } catch (Exception $e) {
            $success = false;
            $message = 'Error saving settings: ' . $e->getMessage();
        }
    }
}

$hasSmtpPassword = $settings['smtp_password'] !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/logo/share-image.png">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/logo/share-image.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/admin.css'); ?>">
</head>
<body class="admin-body">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="admin-main">
        <?php include 'includes/topbar.php'; ?>
        
        <div class="admin-content p-4">
            <div class="mb-4">
                <h2 class="mb-1">Settings</h2>
                <p class="text-muted mb-0">Configure system settings</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- General Settings -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-gear me-2"></i>General Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="form_section" value="general">

                                <div class="mb-3">
                                    <label class="form-label">Site Name</label>
                                    <input type="text" class="form-control" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Site URL</label>
                                    <input type="url" class="form-control" name="site_url" value="<?php echo htmlspecialchars($settings['site_url']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Admin Email</label>
                                    <input type="email" class="form-control" name="admin_email" value="<?php echo htmlspecialchars($settings['admin_email']); ?>">
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-2"></i>Save Settings
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Voting Settings -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-check2-square me-2"></i>Voting Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="showVoteCounts" <?php echo $settings['show_vote_counts'] === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="showVoteCounts">
                                        Show vote counts publicly
                                    </label>
                                </div>
                                <small class="text-muted">Display vote counts on public pages. When disabled, a link to the Live Results Portal will be shown for nominees to view standings.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Email (SMTP) Settings -->
                    <div class="card border-0 shadow-sm mt-4" id="mailSettings">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-envelope-at me-2"></i>Email (SMTP) Settings</h5>
                            <span class="badge bg-<?php echo $settings['smtp_enabled'] === '1' ? 'success' : 'secondary'; ?>">
                                <?php echo $settings['smtp_enabled'] === '1' ? 'Active' : 'Disabled'; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info small">
                                <i class="bi bi-google me-1"></i>
                                <strong>Using Gmail?</strong> Turn on 2-Step Verification for that Google account, then create an
                                <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener" class="alert-link">App Password</a>
                                and paste the 16-character password below. Your normal Gmail password will not work.
                            </div>

                            <?php if ($testResult): ?>
                                <div class="alert alert-<?php echo $testResult['ok'] ? 'success' : 'danger'; ?> alert-dismissible fade show">
                                    <i class="bi bi-<?php echo $testResult['ok'] ? 'check-circle' : 'exclamation-triangle'; ?> me-1"></i>
                                    <?php echo $testResult['text']; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="form_section" value="mail">

                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="smtp_enabled" name="smtp_enabled"
                                           <?php echo $settings['smtp_enabled'] === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="smtp_enabled">
                                        Send email through SMTP
                                    </label>
                                    <div class="form-text">
                                        Required for login verification codes, password resets and approval emails.
                                        With this off, the site falls back to PHP <code>mail()</code>, which does not work on localhost.
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" name="smtp_host"
                                               value="<?php echo htmlspecialchars($settings['smtp_host']); ?>"
                                               placeholder="smtp.gmail.com">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Port</label>
                                        <input type="number" class="form-control" name="smtp_port"
                                               value="<?php echo htmlspecialchars($settings['smtp_port']); ?>" placeholder="587">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Encryption</label>
                                        <select class="form-select" name="smtp_secure">
                                            <option value="tls" <?php echo $settings['smtp_secure'] === 'tls' ? 'selected' : ''; ?>>STARTTLS (port 587)</option>
                                            <option value="ssl" <?php echo $settings['smtp_secure'] === 'ssl' ? 'selected' : ''; ?>>SSL (port 465)</option>
                                            <option value="none" <?php echo $settings['smtp_secure'] === 'none' ? 'selected' : ''; ?>>None</option>
                                        </select>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label">Gmail Address (SMTP username)</label>
                                        <input type="email" class="form-control" name="smtp_username"
                                               value="<?php echo htmlspecialchars($settings['smtp_username']); ?>"
                                               placeholder="yourname@gmail.com">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">App Password</label>
                                        <input type="password" class="form-control" name="smtp_password"
                                               placeholder="<?php echo $hasSmtpPassword ? 'Saved - leave blank to keep it' : 'abcd efgh ijkl mnop'; ?>"
                                               autocomplete="new-password">
                                        <div class="form-text">
                                            <?php if ($hasSmtpPassword): ?>
                                                <i class="bi bi-check-circle text-success me-1"></i>A password is saved.
                                                Leave this blank unless you want to replace it.
                                            <?php else: ?>
                                                Paste the 16-character App Password. Spaces are removed automatically.
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">From Address</label>
                                        <input type="email" class="form-control" name="smtp_from_email"
                                               value="<?php echo htmlspecialchars($settings['smtp_from_email']); ?>"
                                               placeholder="Defaults to the Gmail address above">
                                        <div class="form-text">Gmail rewrites this to your account address unless it is a verified alias.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">From Name</label>
                                        <input type="text" class="form-control" name="smtp_from_name"
                                               value="<?php echo htmlspecialchars($settings['smtp_from_name']); ?>"
                                               placeholder="<?php echo htmlspecialchars(SITE_NAME); ?>">
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary mt-4">
                                    <i class="bi bi-check-lg me-2"></i>Save Email Settings
                                </button>
                            </form>

                            <hr class="my-4">

                            <form method="POST" class="row g-2 align-items-end">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="form_section" value="test_mail">
                                <div class="col-md-7">
                                    <label class="form-label fw-semibold">Send a test email</label>
                                    <input type="email" class="form-control" name="test_email"
                                           value="<?php echo htmlspecialchars($settings['admin_email']); ?>"
                                           placeholder="where should we send it?">
                                </div>
                                <div class="col-md-5">
                                    <button type="submit" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-send me-2"></i>Send Test Email
                                    </button>
                                </div>
                                <div class="col-12">
                                    <div class="form-text">Save your settings first, then send a test to confirm delivery.</div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Security & Access -->
                    <div class="card border-0 shadow-sm mt-4" id="securitySettings">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Security &amp; Access</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="form_section" value="security">

                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="two_factor_required" name="two_factor_required"
                                           <?php echo $settings['two_factor_required'] === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="two_factor_required">
                                        Require two-step verification at login
                                    </label>
                                    <div class="form-text">
                                        After the password is accepted, a 6-digit code is emailed to the account's
                                        address and must be entered to finish signing in.
                                    </div>
                                </div>

                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="two_factor_fallback" name="two_factor_fallback"
                                           <?php echo $settings['two_factor_fallback'] === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="two_factor_fallback">
                                        Allow login if the code email fails to send
                                    </label>
                                    <div class="form-text text-warning-emphasis">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        Keep this ON until you have confirmed a test email arrives &mdash; otherwise a mail
                                        outage will lock everyone, including you, out of the admin area.
                                    </div>
                                </div>

                                <hr class="my-4">

                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="organizer_approval_required" name="organizer_approval_required"
                                           <?php echo $settings['organizer_approval_required'] === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="organizer_approval_required">
                                        New organizers must be approved by an admin
                                    </label>
                                    <div class="form-text">
                                        Newly registered organizers cannot log in until approved on the
                                        <a href="<?php echo SITE_URL; ?>/admin/organizer-approvals.php">Organizer Approvals</a> page.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Extra approval notification email</label>
                                    <input type="email" class="form-control" name="approval_notify_email"
                                           value="<?php echo htmlspecialchars($settings['approval_notify_email']); ?>"
                                           placeholder="optional - e.g. your personal Gmail">
                                    <div class="form-text">
                                        Every active admin is emailed when an organizer registers. Add one more address here if you like.
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-2"></i>Save Security Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Info & Stats -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>System Info</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td class="text-muted">PHP Version</td>
                                    <td class="text-end"><?php echo phpversion(); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">MySQL Version</td>
                                    <td class="text-end"><?php echo $pdo->query('SELECT VERSION()')->fetchColumn(); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Server</td>
                                    <td class="text-end"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Upload Max Size</td>
                                    <td class="text-end"><?php echo ini_get('upload_max_filesize'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-database me-2"></i>Database Stats</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td class="text-muted">Total Users</td>
                                    <td class="text-end"><?php echo $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Total Events</td>
                                    <td class="text-end"><?php echo $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn(); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Total Categories</td>
                                    <td class="text-end"><?php echo $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Total Nominees</td>
                                    <td class="text-end"><?php echo $pdo->query("SELECT COUNT(*) FROM nominees")->fetchColumn(); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Total Votes</td>
                                    <td class="text-end"><?php echo $pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn(); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
    <script>
        document.getElementById('showVoteCounts').addEventListener('change', function() {
            const isChecked = this.checked ? '1' : '0';
            
            fetch('ajax/update_setting.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    key: 'show_vote_counts',
                    value: isChecked
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show a toast or notification if needed
                    console.log('Setting updated');
                } else {
                    alert('Failed to update setting: ' + data.message);
                    // Revert toggle state
                    this.checked = !this.checked;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the setting.');
                this.checked = !this.checked;
            });
        });
    </script>
</body>
</html>
