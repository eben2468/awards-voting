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

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
 * Send email using PHP's built-in mail function
 * 
 * @param string $to - Recipient email address
 * @param string $subject - Email subject
 * @param string $message - Email body content
 * @param array $headers - Additional headers (optional)
 * @return bool - Success status
 */
function sendEmail($to, $subject, $message, $headers = []) {
    $fromEmail = ADMIN_EMAIL; // Using support@votehubgh.com
    $fromName = 'VoteHubGH Support';
    
    // Set default headers
    $defaultHeaders = [
        "From: $fromName <$fromEmail>",
        "X-Mailer: PHP/" . phpversion(),
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8"
    ];
    
    // Merge custom headers with defaults
    // If a header exists in both, the one from $headers will be used (if we handle it correctly)
    $finalHeaders = $defaultHeaders;
    foreach ($headers as $customHeader) {
        // Check if this header already exists in defaultHeaders (basic check)
        $headerName = explode(':', $customHeader)[0];
        $found = false;
        foreach ($finalHeaders as $key => $existingHeader) {
            if (stripos($existingHeader, $headerName . ':') === 0) {
                $finalHeaders[$key] = $customHeader;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $finalHeaders[] = $customHeader;
        }
    }
    
    // Convert headers array to string
    $headerString = implode("\r\n", $finalHeaders);
    
    // Send email with -f parameter for better deliverability (sets the envelope sender)
    // This is often required by mail servers to prevent spoofing
    $result = @mail($to, $subject, $message, $headerString, "-f $fromEmail");
    
    // Log the email attempt
    if ($result) {
        error_log("Email sent successfully to: $to | Subject: $subject");
    } else {
        $lastError = error_get_last();
        error_log("Failed to send email to: $to | Subject: $subject | Error: " . ($lastError['message'] ?? 'Unknown error'));
    }
    
    return $result;
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
