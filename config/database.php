<?php
/**
 * Database Configuration
 * Awards Voting System
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'awards_voting');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// PDO Connection
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

// Site Configuration
define('SITE_NAME', 'VoteHubGH');
define('CURRENCY', 'GHS');
define('VOTE_PRICE', 1.00); // Default price per vote if not set on event

// Auto-detect SITE_URL to prevent broken CSS and links on live server
if (isset($_SERVER['HTTP_HOST'])) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    
    // Check if we are in a subfolder (like /awards-voting on local XAMPP)
    if ($host === 'localhost' || $host === '127.0.0.1') {
        define('SITE_URL', 'http://localhost/awards-voting');
    } else {
        // On production, we assume it's at the root of the domain
        define('SITE_URL', $protocol . $host);
    }
} else {
    // Fallback for CLI or other environments
    define('SITE_URL', 'http://localhost/awards-voting');
}
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('ADMIN_EMAIL', 'support@votehubgh.com');
define('WHATSAPP_NUMBER', '+233548811774');
define('WHATSAPP_LINK', 'https://wa.me/233548811774');
define('SUPPORT_PHONE', '+233548811774');
define('SUPPORT_PHONE_ALT', '+233245849246');

// Security / verification settings
define('LOGIN_CODE_TTL_MINUTES', 10);   // How long a login 2FA code stays valid
define('LOGIN_CODE_MAX_ATTEMPTS', 5);   // Wrong-code attempts before the code is voided
define('RESET_TOKEN_TTL_MINUTES', 60);  // How long a password reset link stays valid
define('EMAIL_CHANGE_TTL_MINUTES', 30); // How long an email-change code stays valid

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Build a URL for a static asset with a cache-busting version stamp.
 *
 * Browsers (and most hosts' caches) hold on to CSS and JS for a long time, so
 * without this a visitor keeps the old stylesheet after an update and the site
 * looks broken or unchanged. The stamp is the file's modification time, so it
 * only changes when the file actually changes.
 *
 * @param string $relativePath Path from the project root, e.g. 'assets/css/admin.css'
 * @return string
 */
function assetUrl($relativePath) {
    $relativePath = ltrim($relativePath, '/');
    $fsPath = __DIR__ . '/../' . $relativePath;

    $version = is_file($fsPath) ? filemtime($fsPath) : null;

    return SITE_URL . '/' . $relativePath . ($version ? '?v=' . $version : '');
}

// CSRF Token Generation
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF Token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitize Input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Redirect Function
function redirect($url) {
    header("Location: " . SITE_URL . "/" . $url);
    exit;
}

// Flash Messages
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Check if user is organizer
function isOrganizer() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'organizer';
}

// Check if user has admin or organizer privileges
function hasAdminAccess() {
    return isAdmin() || isOrganizer();
}

// Get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get a setting value from the database
 * @param string $key - Setting key
 * @param mixed $default - Default value if not found
 * @return mixed
 */
function getSetting($key, $default = null) {
    // Per-request cache: a page can read a dozen settings, and each miss used to
    // open a brand new database connection. clearSettingCache() bumps the
    // generation counter, which drops everything cached here.
    static $cache = [];
    static $generation = -1;

    $currentGeneration = $GLOBALS['__settings_generation'] ?? 0;
    if ($generation !== $currentGeneration) {
        $cache = [];
        $generation = $currentGeneration;
    }

    if (array_key_exists($key, $cache)) {
        return $cache[$key] === null ? $default : $cache[$key];
    }

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$key]);
        $result = $stmt->fetch();

        $cache[$key] = $result ? $result['setting_value'] : null;
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        error_log("Get Setting Error: " . $e->getMessage());
        return $default;
    }
}

/**
 * Write a single setting and confirm it actually landed in the database.
 *
 * Deliberately avoids "INSERT ... ON DUPLICATE KEY UPDATE setting_value =
 * VALUES(setting_value)": the VALUES() function is deprecated from MySQL 8.0.20
 * and removed in later versions, so on some hosts that form fails and settings
 * silently never save. UPDATE-then-INSERT works on every MySQL/MariaDB version
 * and also copes with a settings table that is missing its UNIQUE index.
 *
 * @param string $key
 * @param string $value
 * @return bool True when the stored value matches what was requested
 */
function saveSetting($key, $value) {
    $value = (string) $value;

    try {
        $pdo = getDBConnection();

        $update = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $update->execute([$value, $key]);

        $check = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
        $check->execute([$key]);

        if ((int) $check->fetchColumn() === 0) {
            $insert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $insert->execute([$key, $value]);
        }

        // Read it back so a silent failure cannot be reported as success
        $verify = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? ORDER BY id DESC LIMIT 1");
        $verify->execute([$key]);
        $stored = $verify->fetchColumn();

        clearSettingCache();

        if ((string) $stored !== $value) {
            error_log("Save Setting mismatch for '$key': wanted '" . substr($value, 0, 40)
                . "', stored '" . substr((string) $stored, 0, 40) . "'");
            return false;
        }

        return true;
    } catch (Exception $e) {
        error_log("Save Setting Error for '$key': " . $e->getMessage());
        return false;
    }
}

/**
 * Forget every cached setting so the next getSetting() re-reads the database.
 * Called automatically by saveSetting().
 */
function clearSettingCache() {
    $GLOBALS['__settings_generation'] = ($GLOBALS['__settings_generation'] ?? 0) + 1;
}

/**
 * Log an action to the audit_logs table
 */
function logAudit($user_id, $action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $old_json = $old_values ? json_encode($old_values) : null;
        $new_json = $new_values ? json_encode($new_values) : null;
        
        return $stmt->execute([$user_id, $action, $table_name, $record_id, $old_json, $new_json, $ip, $ua]);
    } catch (Exception $e) {
        // Fail silently to prevent breaking the main flow
        error_log("Audit Log Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a notification for a user
 */
function createNotification($user_id, $title, $message, $type = 'info', $link = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO notifications (user_id, title, message, type, link)
            VALUES (?, ?, ?, ?, ?)"
        );
        return $stmt->execute([$user_id, $title, $message, $type, $link]);
    } catch (Exception $e) {
        error_log("Notification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email.
 *
 * Delivery goes through Gmail/SMTP when it has been configured in
 * Admin > Settings > Email (SMTP) Settings, otherwise it falls back to PHP's
 * built-in mail() function.
 *
 * @param string $to - Recipient email address
 * @param string $subject - Email subject
 * @param string $message - Email body content (HTML)
 * @param array $headers - Additional raw headers, e.g. ["Reply-To: Name <a@b.com>"]
 * @return bool - Success status
 */
function sendEmail($to, $subject, $message, $headers = []) {
    require_once __DIR__ . '/mailer.php';

    $options = [];

    // Translate the legacy raw-header array into mailer options
    foreach ($headers as $customHeader) {
        if (stripos($customHeader, 'Reply-To:') === 0) {
            $value = trim(substr($customHeader, strlen('Reply-To:')));
            if (preg_match('/^(.*?)<([^>]+)>$/', $value, $m)) {
                $options['replyToName'] = trim($m[1]);
                $options['replyTo'] = trim($m[2]);
            } else {
                $options['replyTo'] = $value;
            }
        } elseif (stripos($customHeader, 'Cc:') === 0) {
            $options['cc'] = array_map('trim', explode(',', trim(substr($customHeader, 3))));
        } elseif (stripos($customHeader, 'Bcc:') === 0) {
            $options['bcc'] = array_map('trim', explode(',', trim(substr($customHeader, 4))));
        }
    }

    return sendMailMessage($to, $subject, $message, $options);
}

/**
 * Generate a numeric one-time verification code.
 *
 * @param int $length
 * @return string
 */
function generateVerificationCode($length = 6) {
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= random_int(0, 9);
    }
    return $code;
}

/**
 * Hash a one-time code before storing it in the database.
 */
function hashVerificationCode($code) {
    return hash('sha256', $code . '|' . DB_NAME);
}

/**
 * Get the email addresses of every administrator who should be notified.
 *
 * @return array
 */
function getAdminNotificationEmails() {
    $emails = [];

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND status = 'active'");
        foreach ($stmt->fetchAll() as $row) {
            if (filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower($row['email']);
            }
        }
    } catch (Exception $e) {
        error_log('getAdminNotificationEmails error: ' . $e->getMessage());
    }

    $configured = getSetting('admin_email', ADMIN_EMAIL);
    if (filter_var($configured, FILTER_VALIDATE_EMAIL)) {
        $emails[] = strtolower($configured);
    }

    $notifyTo = getSetting('approval_notify_email', '');
    if (filter_var($notifyTo, FILTER_VALIDATE_EMAIL)) {
        $emails[] = strtolower($notifyTo);
    }

    return array_values(array_unique($emails));
}

/**
 * Number of organizer accounts awaiting admin approval.
 *
 * @return int
 */
function getPendingOrganizerCount() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'organizer' AND approval_status = 'pending'");
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        // Column may not exist yet (migration not run)
        return 0;
    }
}

/**
 * Purge expired login/email verification codes and password resets.
 */
function purgeExpiredVerificationCodes() {
    try {
        $pdo = getDBConnection();
        $pdo->exec("DELETE FROM login_verification_codes WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $pdo->exec("DELETE FROM email_change_requests WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $pdo->exec("DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    } catch (Exception $e) {
        // Tables may not exist yet; ignore
    }
}

/**
 * Send contact form message to support team
 * 
 * @param string $name - Sender's name
 * @param string $email - Sender's email
 * @param string $subject - Message subject
 * @param string $message - Message content
 * @return bool - Success status
 */
function sendContactMessage($name, $email, $subject, $message) {
    // Prepare email content
    $emailSubject = "[Contact Form] $subject";
    
    $emailBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>New Contact Message</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f7f6; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1);'>
            <div style='background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); padding: 30px; text-align: center;'>
                <h1 style='color: #ffffff; margin: 0; font-size: 24px;'>New Contact Inquiry</h1>
                <p style='color: rgba(255,255,255,0.8); margin-top: 10px;'>VoteHubGH Support System</p>
            </div>
            
            <div style='padding: 30px;'>
                <div style='margin-bottom: 25px;'>
                    <h3 style='color: #1e3c72; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-top: 0;'>Sender Information</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px 0; color: #777; width: 100px;'><strong>Name:</strong></td>
                            <td style='padding: 8px 0; color: #333;'>" . htmlspecialchars($name) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #777;'><strong>Email:</strong></td>
                            <td style='padding: 8px 0; color: #333;'><a href='mailto:" . htmlspecialchars($email) . "' style='color: #2a5298; text-decoration: none;'>" . htmlspecialchars($email) . "</a></td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #777;'><strong>Subject:</strong></td>
                            <td style='padding: 8px 0; color: #333;'>" . htmlspecialchars($subject) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #777;'><strong>Date:</strong></td>
                            <td style='padding: 8px 0; color: #333;'>" . date('F j, Y \a\t g:i A') . "</td>
                        </tr>
                    </table>
                </div>
                
                <div style='margin-bottom: 25px;'>
                    <h3 style='color: #1e3c72; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-top: 0;'>Message Content</h3>
                    <div style='background-color: #f9f9f9; border-left: 4px solid #2a5298; padding: 20px; color: #444; white-space: pre-wrap; font-style: italic;'>" . nl2br(htmlspecialchars($message)) . "</div>
                </div>
                
                <div style='text-align: center; margin-top: 30px;'>
                    <a href='mailto:" . htmlspecialchars($email) . "' style='background-color: #2a5298; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Reply to " . htmlspecialchars($name) . "</a>
                </div>
            </div>
            
            <div style='background-color: #f4f7f6; padding: 20px; text-align: center; color: #888; font-size: 12px;'>
                <p style='margin: 0;'>This is an automated message from the VoteHubGH Contact Form.</p>
                <p style='margin: 5px 0 0;'>&copy; " . date('Y') . " VoteHubGH. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Set Reply-To to the sender's email so support can just click 'Reply'
    $headers = [
        "Reply-To: $name <$email>"
    ];
    
    // Send to support email
    $supportEmail = ADMIN_EMAIL;
    
    return sendEmail($supportEmail, $emailSubject, $emailBody, $headers);
}

/**
 * Check if an event has expired (past its end_date)
 * Real-time check against the database
 * 
 * @param int $eventId - The event ID to check
 * @return bool - True if the event has expired
 */
function isEventExpired($eventId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, end_date, status FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();
        
        if (!$event) {
            return true; // Event doesn't exist, treat as expired
        }
        
        // Check if event end_date has passed
        if (strtotime($event['end_date']) < time()) {
            // Auto-update status to 'completed' if still 'active'
            if ($event['status'] === 'active') {
                $updateStmt = $pdo->prepare("UPDATE events SET status = 'completed', updated_at = NOW() WHERE id = ? AND status = 'active'");
                $updateStmt->execute([$eventId]);
                error_log("Event #$eventId auto-deactivated: end_date has passed.");
            }
            return true;
        }
        
        // Also check if status is not active
        if ($event['status'] !== 'active') {
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("isEventExpired Error: " . $e->getMessage());
        return false; // Fail open to avoid blocking legitimate votes on DB errors
    }
}

/**
 * Deactivate all expired events and their associated nominee codes.
 * This should be called by a cron job or scheduled task.
 * 
 * @return array - Summary of deactivated events and nominees
 */
function deactivateExpiredEvents() {
    try {
        $pdo = getDBConnection();
        $results = ['events_deactivated' => 0, 'nominees_deactivated' => 0, 'events' => []];
        
        // 1. Find all active events whose end_date has passed
        $stmt = $pdo->prepare("
            SELECT id, title, end_date, created_by 
            FROM events 
            WHERE status = 'active' AND end_date < NOW()
        ");
        $stmt->execute();
        $expiredEvents = $stmt->fetchAll();
        
        if (empty($expiredEvents)) {
            return $results;
        }
        
        foreach ($expiredEvents as $event) {
            $pdo->beginTransaction();
            
            try {
                // 2. Update event status to 'completed'
                $updateEvent = $pdo->prepare("
                    UPDATE events SET status = 'completed', updated_at = NOW() 
                    WHERE id = ? AND status = 'active'
                ");
                $updateEvent->execute([$event['id']]);
                
                if ($updateEvent->rowCount() > 0) {
                    $results['events_deactivated']++;
                    
                    // 3. Deactivate all nominees belonging to this event's categories
                    $deactivateNominees = $pdo->prepare("
                        UPDATE nominees n
                        JOIN categories c ON n.category_id = c.id
                        SET n.status = 'inactive', n.updated_at = NOW()
                        WHERE c.event_id = ? AND n.status = 'active'
                    ");
                    $deactivateNominees->execute([$event['id']]);
                    $nomineesAffected = $deactivateNominees->rowCount();
                    $results['nominees_deactivated'] += $nomineesAffected;
                    
                    // 4. Cancel any pending transactions for this event
                    $cancelPending = $pdo->prepare("
                        UPDATE transactions SET status = 'failed', updated_at = NOW()
                        WHERE event_id = ? AND status = 'pending'
                    ");
                    $cancelPending->execute([$event['id']]);
                    
                    // 5. Log the deactivation
                    $logStmt = $pdo->prepare("
                        INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address)
                        VALUES (NULL, 'event_auto_deactivated', 'events', ?, ?, 'SYSTEM')
                    ");
                    $logStmt->execute([
                        $event['id'],
                        json_encode([
                            'event_title' => $event['title'],
                            'end_date' => $event['end_date'],
                            'nominees_deactivated' => $nomineesAffected,
                            'deactivated_at' => date('Y-m-d H:i:s')
                        ])
                    ]);
                    
                    // 6. Notify the event organizer
                    if ($event['created_by']) {
                        createNotification(
                            $event['created_by'],
                            "Event Ended: " . $event['title'],
                            "Your event '" . $event['title'] . "' has ended and voting has been automatically closed. All nominee codes have been deactivated.",
                            "warning",
                            "organizer/events/index.php"
                        );
                    }
                    
                    // 7. Notify admin
                    createNotification(
                        1,
                        "Event Auto-Deactivated: " . $event['title'],
                        "Event '" . $event['title'] . "' has been automatically deactivated. $nomineesAffected nominee(s) were set to inactive.",
                        "info",
                        "admin/events.php"
                    );
                    
                    $results['events'][] = [
                        'id' => $event['id'],
                        'title' => $event['title'],
                        'end_date' => $event['end_date'],
                        'nominees_deactivated' => $nomineesAffected
                    ];
                }
                
                $pdo->commit();
                
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Error deactivating event #{$event['id']}: " . $e->getMessage());
            }
        }
        
        return $results;
        
    } catch (Exception $e) {
        error_log("deactivateExpiredEvents Error: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}
