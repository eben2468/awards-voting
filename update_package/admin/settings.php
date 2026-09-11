<?php
/**
 * Admin - Settings
 * Awards Voting System
 */

require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$page_title = 'Settings';
$pdo = getDBConnection();

// Fetch settings from database with fallbacks to constants
$settings = [
    'site_name' => getSetting('site_name', SITE_NAME),
    'site_url' => getSetting('site_url', SITE_URL),
    'admin_email' => getSetting('admin_email', ADMIN_EMAIL),
    'show_vote_counts' => getSetting('show_vote_counts', '1'),
];

$success = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
    } else {
        try {
            $site_name = $_POST['site_name'] ?? SITE_NAME;
            $site_url = $_POST['site_url'] ?? SITE_URL;
            $admin_email = $_POST['admin_email'] ?? ADMIN_EMAIL;

            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            
            $stmt->execute(['site_name', $site_name]);
            $stmt->execute(['site_url', $site_url]);
            $stmt->execute(['admin_email', $admin_email]);

            $success = true;
            $message = 'Settings updated successfully.';
            
            // Update the local $settings array after saving
            $settings['site_name'] = $site_name;
            $settings['site_url'] = $site_url;
            $settings['admin_email'] = $admin_email;
        } catch (Exception $e) {
            $success = false;
            $message = 'Error saving settings: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/uploads/logo/VoteHubGH.png">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/uploads/logo/VoteHubGH.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">
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
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
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
