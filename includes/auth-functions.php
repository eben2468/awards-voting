<?php
/**
 * Authentication & Verification Helpers
 * Awards Voting System (VoteHubGH)
 *
 * Shared logic for:
 *   - Two-step login verification codes (emailed)
 *   - Organizer approval emails to admins / organizers
 *   - Verified email-address changes
 *   - Password reset links & codes
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mailer.php';

if (!defined('AUTH_FUNCTIONS_LOADED')) {
    define('AUTH_FUNCTIONS_LOADED', true);

    /**
     * Mask an email address for display: joh****@gmail.com
     */
    function maskEmail($email) {
        $parts = explode('@', (string) $email);
        if (count($parts) !== 2) {
            return $email;
        }
        $name = $parts[0];
        $visible = max(1, min(3, (int) floor(strlen($name) / 2)));
        return substr($name, 0, $visible) . str_repeat('*', max(3, strlen($name) - $visible)) . '@' . $parts[1];
    }

    /**
     * Is two-step verification switched on system-wide?
     */
    function isTwoFactorRequired() {
        return (string) getSetting('two_factor_required', '1') === '1';
    }

    /**
     * Is admin approval of new organizers switched on?
     */
    function isOrganizerApprovalRequired() {
        return (string) getSetting('organizer_approval_required', '1') === '1';
    }

    /**
     * Throttle code requests: at most one every 60 seconds and 6 per hour.
     *
     * @return string|null Error message when throttled, null when allowed
     */
    function checkCodeThrottle(PDO $pdo, $userId, $purpose = 'login') {
        try {
            // NOTE: the age is calculated by MySQL, not PHP. The database server and
            // PHP can sit in different timezones, so mixing NOW() with PHP's time()
            // produces wildly wrong results.
            $stmt = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age
                FROM login_verification_codes
                WHERE user_id = ? AND purpose = ?
                ORDER BY id DESC LIMIT 1");
            $stmt->execute([$userId, $purpose]);
            $age = $stmt->fetchColumn();

            if ($age !== false && $age !== null && (int) $age < 60) {
                $wait = 60 - (int) $age;
                return 'Please wait ' . $wait . ' more second' . ($wait === 1 ? '' : 's') . ' before requesting another code.';
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_verification_codes
                WHERE user_id = ? AND purpose = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmt->execute([$userId, $purpose]);
            if ((int) $stmt->fetchColumn() >= 6) {
                return 'Too many verification codes requested. Please try again in an hour, or contact support.';
            }
        } catch (Exception $e) {
            error_log('checkCodeThrottle error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Create a login verification code and email it to the user.
     *
     * The `throttled` flag matters: a throttled request is NOT a delivery
     * failure. A code was issued moments ago and is still valid, so the caller
     * must still demand the second factor. Only a genuine send failure may be
     * treated as "email is broken".
     *
     * @return array ['sent' => bool, 'error' => string, 'throttled' => bool]
     */
    function issueLoginCode(PDO $pdo, array $user) {
        $throttle = checkCodeThrottle($pdo, $user['id'], 'login');
        if ($throttle !== null) {
            return ['sent' => false, 'error' => $throttle, 'throttled' => true];
        }

        $code = generateVerificationCode(6);

        try {
            // Void any outstanding codes for this user
            $pdo->prepare("UPDATE login_verification_codes SET used_at = NOW()
                WHERE user_id = ? AND purpose = 'login' AND used_at IS NULL")
                ->execute([$user['id']]);

            $stmt = $pdo->prepare("INSERT INTO login_verification_codes
                (user_id, code_hash, purpose, expires_at, ip_address, user_agent)
                VALUES (?, ?, 'login', DATE_ADD(NOW(), INTERVAL ? MINUTE), ?, ?)");
            $stmt->execute([
                $user['id'],
                hashVerificationCode($code),
                LOGIN_CODE_TTL_MINUTES,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
        } catch (PDOException $e) {
            error_log('issueLoginCode DB error: ' . $e->getMessage());
            return ['sent' => false, 'error' => 'Could not create a verification code. Please try again.', 'throttled' => false];
        }

        $firstName = explode(' ', trim($user['fullname']))[0];
        $when = date('F j, Y \a\t g:i A');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $body = '<p>Hi ' . e($firstName) . ',</p>
            <p>Use the verification code below to finish signing in to your ' . e(SITE_NAME) . ' account.</p>'
            . buildCodeBlock($code) .
            '<p style="text-align:center; color:#777; font-size:13px;">This code expires in '
            . LOGIN_CODE_TTL_MINUTES . ' minutes and can only be used once.</p>
            <hr style="border:none; border-top:1px solid #eee; margin:24px 0;">
            <p style="font-size:13px; color:#777; margin:0;"><strong>Sign-in attempt details</strong><br>
            Time: ' . e($when) . '<br>
            IP address: ' . e($ip) . '</p>
            <p style="font-size:13px; color:#c0392b; margin-top:16px;">
            If you did not try to sign in, do not share this code with anyone and change your password immediately.</p>';

        $sent = sendMailMessage(
            $user['email'],
            'Your ' . SITE_NAME . ' login code',
            buildEmailTemplate('Two-Step Verification', $body, 'Your login code is ' . $code)
        );

        if (!$sent) {
            return ['sent' => false, 'error' => getLastMailError() ?: 'We could not send the verification email.', 'throttled' => false];
        }

        return ['sent' => true, 'error' => '', 'throttled' => false];
    }

    /**
     * Check a submitted login code.
     *
     * @return array ['ok' => bool, 'error' => string]
     */
    function verifyLoginCode(PDO $pdo, $userId, $code, $purpose = 'login') {
        $code = preg_replace('/\D/', '', (string) $code);

        if (strlen($code) !== 6) {
            return ['ok' => false, 'error' => 'Please enter the 6-digit code from your email.'];
        }

        try {
            // Expiry is evaluated by MySQL so a PHP/MySQL timezone mismatch cannot
            // make a fresh code look expired (or an old one look valid).
            $stmt = $pdo->prepare("SELECT *, (expires_at < NOW()) AS is_expired
                FROM login_verification_codes
                WHERE user_id = ? AND purpose = ? AND used_at IS NULL
                ORDER BY id DESC LIMIT 1");
            $stmt->execute([$userId, $purpose]);
            $record = $stmt->fetch();

            if (!$record) {
                return ['ok' => false, 'error' => 'No active code found. Please request a new one.'];
            }

            if ((int) $record['is_expired'] === 1) {
                return ['ok' => false, 'error' => 'That code has expired. Please request a new one.'];
            }

            if ((int) $record['attempts'] >= LOGIN_CODE_MAX_ATTEMPTS) {
                $pdo->prepare("UPDATE login_verification_codes SET used_at = NOW() WHERE id = ?")
                    ->execute([$record['id']]);
                return ['ok' => false, 'error' => 'Too many incorrect attempts. Please request a new code.'];
            }

            if (!hash_equals($record['code_hash'], hashVerificationCode($code))) {
                $pdo->prepare("UPDATE login_verification_codes SET attempts = attempts + 1 WHERE id = ?")
                    ->execute([$record['id']]);
                $left = LOGIN_CODE_MAX_ATTEMPTS - ((int) $record['attempts'] + 1);
                return [
                    'ok' => false,
                    'error' => 'Incorrect code. ' . ($left > 0
                        ? $left . ' attempt' . ($left === 1 ? '' : 's') . ' remaining.'
                        : 'Please request a new code.'),
                ];
            }

            $pdo->prepare("UPDATE login_verification_codes SET used_at = NOW() WHERE id = ?")
                ->execute([$record['id']]);

            return ['ok' => true, 'error' => ''];
        } catch (PDOException $e) {
            error_log('verifyLoginCode error: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Verification failed. Please try again.'];
        }
    }

    /**
     * The sign-in pages that can have their own picture in Settings. A page with
     * no picture of its own borrows the one named by 'fallback'.
     */
    function authImagePages() {
        return [
            'login'    => ['label' => 'Login', 'stem' => 'login', 'fallback' => null, 'hint' => ''],
            'register' => ['label' => 'Signup', 'stem' => 'signup', 'fallback' => null, 'hint' => ''],
            'forgot'   => ['label' => 'Forgot password', 'stem' => 'forgot', 'fallback' => 'login', 'hint' => 'Also shown on the "Check your email" step.'],
            'reset'    => ['label' => 'Reset password', 'stem' => 'reset', 'fallback' => 'forgot', 'hint' => 'Shown while entering the code and choosing a new password.'],
            'verify'   => ['label' => 'Login code', 'stem' => 'verify', 'fallback' => 'login', 'hint' => 'The page that asks for the 6-digit code.'],
            'approval' => ['label' => 'Awaiting approval', 'stem' => 'approval', 'fallback' => 'register', 'hint' => 'Also shown to organizers who were not approved.'],
        ];
    }

    /**
     * Picture for a sign-in page's panel: its own, then its fallback, then the emblem.
     */
    function authPanelImage($page) {
        $pages = authImagePages();

        while ($page !== null) {
            $file = basename((string) getSetting('auth_image_' . $page, ''));
            if ($file !== '' && is_file(dirname(__DIR__) . '/uploads/site/' . $file)) {
                return SITE_URL . '/uploads/site/' . rawurlencode($file);
            }
            $page = $pages[$page]['fallback'] ?? null;
        }

        return assetUrl('assets/logo/emblem.png');
    }

    /**
     * The email a login attempt is filed under.
     */
    function loginAttemptEmail($email) {
        return strtolower(trim($email));
    }

    /**
     * Failed attempts for this email from this connection, and how long is left.
     *
     * The count is per email and connection on purpose. Counting by email alone
     * would let anyone lock the admin out on demand, and the site sits behind a
     * CDN, so counting by IP alone could lock out many visitors at once.
     *
     * @return array locked (bool), left (attempts remaining), wait (seconds), max
     */
    function loginLockState(PDO $pdo, $email) {
        $max = max(0, min(50, (int) getSetting('login_max_attempts', '5')));
        if ($max === 0) {
            return ['locked' => false, 'left' => null, 'wait' => 0, 'max' => 0];
        }

        $minutes = max(1, min(1440, (int) getSetting('login_lockout_minutes', '15')));
        $key = loginAttemptEmail($email);

        // new_values is a JSON column on MySQL, which reformats what is stored, so
        // read the field out of it rather than comparing the whole value
        $emailField = "LOWER(JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.email')))";

        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS failures,
                   TIMESTAMPDIFF(SECOND, NOW(), MAX(created_at) + INTERVAL $minutes MINUTE) AS wait_seconds
            FROM audit_logs
            WHERE action = 'failed_login'
              AND $emailField = ?
              AND ip_address <=> ?
              AND created_at >= NOW() - INTERVAL $minutes MINUTE
              AND id > COALESCE((SELECT MAX(id) FROM (
                      SELECT id FROM audit_logs WHERE action = 'login_attempts_cleared' AND $emailField = ?
                  ) AS cleared), 0)
        ");
        $stmt->execute([$key, $_SERVER['REMOTE_ADDR'] ?? null, $key]);
        $row = $stmt->fetch();

        $failures = (int) $row['failures'];
        return [
            'locked' => $failures >= $max,
            'left'   => max(0, $max - $failures),
            'wait'   => max(0, (int) $row['wait_seconds']),
            'max'    => $max,
        ];
    }

    function loginLockedMessage($waitSeconds) {
        $minutes = max(1, (int) ceil($waitSeconds / 60));
        return 'Too many failed attempts. Please try again in ' . $minutes . ($minutes === 1 ? ' minute.' : ' minutes.');
    }

    /**
     * Record a wrong password and return what to tell the person. The wording is
     * the same whether or not the account exists, so emails cannot be discovered.
     */
    function recordFailedLogin(PDO $pdo, $email) {
        $pdo->prepare("INSERT INTO audit_logs (action, ip_address, user_agent, new_values) VALUES ('failed_login', ?, ?, ?)")
            ->execute([$_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, json_encode(['email' => loginAttemptEmail($email)])]);

        $state = loginLockState($pdo, $email);
        if ($state['locked']) {
            return loginLockedMessage($state['wait']);
        }
        if ($state['max'] > 0 && $state['left'] <= 2) {
            return 'Invalid email or password. ' . $state['left'] . ($state['left'] === 1 ? ' attempt' : ' attempts') . ' left.';
        }
        return 'Invalid email or password.';
    }

    /**
     * A correct password or a password reset wipes the slate clean.
     */
    function clearFailedLogins(PDO $pdo, $email) {
        $pdo->prepare("INSERT INTO audit_logs (action, ip_address, user_agent, new_values) VALUES ('login_attempts_cleared', ?, ?, ?)")
            ->execute([$_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, json_encode(['email' => loginAttemptEmail($email)])]);
    }

    /**
     * Establish the logged-in session for a verified user and return the
     * destination URL (relative to SITE_URL).
     */
    function establishUserSession(PDO $pdo, array $user) {
        session_regenerate_id(true);

        $_SESSION['user_id']     = $user['id'];
        $_SESSION['user_name']   = $user['fullname'];
        $_SESSION['user_email']  = $user['email'];
        $_SESSION['user_role']   = $user['role'];
        $_SESSION['user_avatar'] = $user['avatar'] ?? null;
        $_SESSION['last_activity'] = time();

        unset($_SESSION['pending_login'], $_SESSION['pending_2fa_user_id']);

        try {
            $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?")->execute([$user['id']]);
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (?, 'login', ?, ?)")
                ->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
        } catch (PDOException $e) {
            error_log('establishUserSession logging error: ' . $e->getMessage());
        }

        $default = $user['role'] === 'admin'
            ? 'admin/'
            : ($user['role'] === 'organizer' ? 'organizer/' : '');

        $redirect = $_SESSION['redirect_url'] ?? $default;
        unset($_SESSION['redirect_url']);

        return $redirect;
    }

    /**
     * Email every administrator that a new organizer is waiting for approval.
     *
     * @return bool true when at least one email went out
     */
    function notifyAdminsOfPendingOrganizer(array $user) {
        // The person applying never gets the admin notice, even if their address is on the list
        $admins = array_diff(getAdminNotificationEmails(), [strtolower(trim($user['email']))]);
        if (empty($admins)) {
            return false;
        }

        $approvalUrl = SITE_URL . '/admin/organizer-approvals.php';
        $registered = date('F j, Y \a\t g:i A', strtotime($user['created_at'] ?? 'now'));

        $body = '<p>A new organizer account has been created and is waiting for your approval.
            The organizer cannot sign in until an administrator approves the account.</p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                   style="border-collapse:collapse; margin:20px 0; font-size:14px;">
                <tr>
                    <td style="padding:10px 8px; color:#777; width:130px; border-bottom:1px solid #eee;"><strong>Name</strong></td>
                    <td style="padding:10px 8px; color:#333; border-bottom:1px solid #eee;">' . e($user['fullname']) . '</td>
                </tr>
                <tr>
                    <td style="padding:10px 8px; color:#777; border-bottom:1px solid #eee;"><strong>Email</strong></td>
                    <td style="padding:10px 8px; color:#333; border-bottom:1px solid #eee;">
                        <a href="mailto:' . e($user['email']) . '" style="color:#2a5298; text-decoration:none;">'
                        . e($user['email']) . '</a></td>
                </tr>
                <tr>
                    <td style="padding:10px 8px; color:#777; border-bottom:1px solid #eee;"><strong>Phone</strong></td>
                    <td style="padding:10px 8px; color:#333; border-bottom:1px solid #eee;">'
                        . e($user['phone'] ?: 'Not provided') . '</td>
                </tr>
                <tr>
                    <td style="padding:10px 8px; color:#777;"><strong>Registered</strong></td>
                    <td style="padding:10px 8px; color:#333;">' . e($registered) . '</td>
                </tr>
            </table>'
            . buildEmailButton($approvalUrl, 'Review & Approve') .
            '<p style="font-size:13px; color:#777;">Or open: <a href="' . e($approvalUrl)
            . '" style="color:#2a5298;">' . e($approvalUrl) . '</a></p>';

        $html = buildEmailTemplate(
            'Organizer Approval Needed',
            $body,
            $user['fullname'] . ' is waiting for account approval'
        );

        $sentAny = false;
        foreach ($admins as $adminEmail) {
            $ok = sendMailMessage(
                $adminEmail,
                '[Action Required] New organizer awaiting approval: ' . $user['fullname'],
                $html,
                ['replyTo' => $user['email'], 'replyToName' => $user['fullname']]
            );
            $sentAny = $sentAny || $ok;
        }

        return $sentAny;
    }

    /**
     * Tell an organizer their account has been approved.
     */
    function sendOrganizerApprovedEmail(array $user) {
        $firstName = explode(' ', trim($user['fullname']))[0];
        $loginUrl = SITE_URL . '/login.php';

        $body = '<p>Hi ' . e($firstName) . ',</p>
            <p>Good news - your organizer account on ' . e(SITE_NAME)
            . ' has been <strong style="color:#198754;">approved</strong>. You can now sign in and start
            creating events, categories and nominees.</p>
            <p>For your security, each time you sign in we will email you a 6-digit verification code.</p>'
            . buildEmailButton($loginUrl, 'Sign In Now') .
            '<p style="font-size:13px; color:#777;">Need a hand getting started? Reply to this email or reach us on WhatsApp at '
            . e(WHATSAPP_NUMBER) . '.</p>';

        return sendMailMessage(
            $user['email'],
            'Your ' . SITE_NAME . ' organizer account has been approved',
            buildEmailTemplate('Account Approved', $body, 'You can now sign in to ' . SITE_NAME)
        );
    }

    /**
     * Tell an organizer their account was not approved.
     */
    function sendOrganizerRejectedEmail(array $user, $reason = '') {
        $firstName = explode(' ', trim($user['fullname']))[0];

        $reasonBlock = trim($reason) !== ''
            ? '<div style="background-color:#fff4f4; border-left:4px solid #dc3545; padding:14px 18px; margin:18px 0; color:#444;">'
                . nl2br(e($reason)) . '</div>'
            : '';

        $body = '<p>Hi ' . e($firstName) . ',</p>
            <p>Thank you for your interest in organising events on ' . e(SITE_NAME)
            . '. After review, we are unable to approve your organizer account at this time.</p>'
            . $reasonBlock .
            '<p>If you believe this was a mistake, or you would like to provide more information,
            please get in touch with our team:</p>
            <p style="font-size:14px;">
                WhatsApp / Call: <strong>' . e(WHATSAPP_NUMBER) . '</strong><br>
                Alternate line: <strong>' . e(SUPPORT_PHONE_ALT) . '</strong><br>
                Email: <a href="mailto:' . e(ADMIN_EMAIL) . '" style="color:#2a5298;">'
                . e(ADMIN_EMAIL) . '</a>
            </p>';

        return sendMailMessage(
            $user['email'],
            'Update on your ' . SITE_NAME . ' organizer application',
            buildEmailTemplate('Application Update', $body, 'An update on your organizer application')
        );
    }

    /**
     * Confirm to a newly registered organizer that we received their application.
     */
    function sendOrganizerPendingEmail(array $user) {
        $firstName = explode(' ', trim($user['fullname']))[0];

        $body = '<p>Hi ' . e($firstName) . ',</p>
            <p>Thanks for registering as an organizer on ' . e(SITE_NAME) . '.</p>
            <p>Your account is now <strong style="color:#fd7e14;">awaiting administrator approval</strong>.
            We review new organizer accounts manually to keep the platform safe for voters.
            You will receive another email as soon as your account is approved - this is usually
            within a few hours during business hours.</p>
            <div style="background-color:#f1f5ff; border-radius:8px; padding:18px; margin:20px 0;">
                <p style="margin:0 0 10px; font-weight:bold; color:#1e3c72;">Need it approved faster?</p>
                <p style="margin:0; font-size:14px;">
                    WhatsApp or call <strong>' . e(WHATSAPP_NUMBER) . '</strong><br>
                    Alternate line: <strong>' . e(SUPPORT_PHONE_ALT) . '</strong><br>
                    Email: <a href="mailto:' . e(ADMIN_EMAIL) . '" style="color:#2a5298;">'
                    . e(ADMIN_EMAIL) . '</a>
                </p>
            </div>
            <p style="font-size:13px; color:#777;">Registered email: ' . e($user['email']) . '</p>';

        return sendMailMessage(
            $user['email'],
            'We received your ' . SITE_NAME . ' organizer application',
            buildEmailTemplate('Awaiting Approval', $body, 'Your organizer account is pending approval')
        );
    }

    /**
     * Start an email-address change: store the request and email a code to the NEW address.
     *
     * @return array ['sent' => bool, 'error' => string]
     */
    function issueEmailChangeCode(PDO $pdo, array $user, $newEmail) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_change_requests
                WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmt->execute([$user['id']]);
            if ((int) $stmt->fetchColumn() >= 5) {
                return ['sent' => false, 'error' => 'Too many email change requests. Please try again in an hour.'];
            }

            $code = generateVerificationCode(6);

            // Void any outstanding requests
            $pdo->prepare("UPDATE email_change_requests SET used_at = NOW()
                WHERE user_id = ? AND used_at IS NULL")->execute([$user['id']]);

            $pdo->prepare("INSERT INTO email_change_requests
                (user_id, old_email, new_email, code_hash, expires_at)
                VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))")
                ->execute([
                    $user['id'],
                    $user['email'],
                    $newEmail,
                    hashVerificationCode($code),
                    EMAIL_CHANGE_TTL_MINUTES,
                ]);
        } catch (PDOException $e) {
            error_log('issueEmailChangeCode DB error: ' . $e->getMessage());
            return ['sent' => false, 'error' => 'Could not start the email change. Please try again.'];
        }

        $firstName = explode(' ', trim($user['fullname']))[0];

        $body = '<p>Hi ' . e($firstName) . ',</p>
            <p>You asked to change the email address on your ' . e(SITE_NAME)
            . ' account to <strong>' . e($newEmail) . '</strong>.</p>
            <p>Enter this confirmation code on your profile page to complete the change:</p>'
            . buildCodeBlock($code) .
            '<p style="text-align:center; color:#777; font-size:13px;">This code expires in '
            . EMAIL_CHANGE_TTL_MINUTES . ' minutes.</p>
            <p style="font-size:13px; color:#c0392b;">If you did not request this change, you can ignore this
            email - your address will stay as ' . e($user['email']) . '.</p>';

        $sent = sendMailMessage(
            $newEmail,
            'Confirm your new ' . SITE_NAME . ' email address',
            buildEmailTemplate('Confirm Your New Email', $body, 'Your confirmation code is ' . $code)
        );

        if (!$sent) {
            return ['sent' => false, 'error' => getLastMailError() ?: 'We could not send the confirmation email.'];
        }

        // Courtesy heads-up to the old address
        $alertBody = '<p>Hi ' . e($firstName) . ',</p>
            <p>A request was made to change the email address on your ' . e(SITE_NAME)
            . ' account from <strong>' . e($user['email']) . '</strong> to <strong>'
            . e(maskEmail($newEmail)) . '</strong>.</p>
            <p>If this was you, no action is needed - just confirm the code we sent to the new address.</p>
            <p style="color:#c0392b;"><strong>If this was not you</strong>, change your password immediately
            and contact us on ' . e(WHATSAPP_NUMBER) . '.</p>';

        sendMailMessage(
            $user['email'],
            'Security alert: email change requested on your ' . SITE_NAME . ' account',
            buildEmailTemplate('Security Alert', $alertBody, 'An email change was requested on your account')
        );

        return ['sent' => true, 'error' => ''];
    }

    /**
     * Confirm a pending email change with the emailed code.
     *
     * @return array ['ok' => bool, 'error' => string, 'email' => string]
     */
    function confirmEmailChange(PDO $pdo, array $user, $code) {
        $code = preg_replace('/\D/', '', (string) $code);

        if (strlen($code) !== 6) {
            return ['ok' => false, 'error' => 'Please enter the 6-digit confirmation code.', 'email' => ''];
        }

        try {
            // Expiry is evaluated by MySQL - see the note in verifyLoginCode()
            $stmt = $pdo->prepare("SELECT *, (expires_at < NOW()) AS is_expired
                FROM email_change_requests
                WHERE user_id = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1");
            $stmt->execute([$user['id']]);
            $request = $stmt->fetch();

            if (!$request) {
                return ['ok' => false, 'error' => 'No pending email change found. Please start again.', 'email' => ''];
            }

            if ((int) $request['is_expired'] === 1) {
                return ['ok' => false, 'error' => 'That code has expired. Please start the email change again.', 'email' => ''];
            }

            if ((int) $request['attempts'] >= LOGIN_CODE_MAX_ATTEMPTS) {
                $pdo->prepare("UPDATE email_change_requests SET used_at = NOW() WHERE id = ?")
                    ->execute([$request['id']]);
                return ['ok' => false, 'error' => 'Too many incorrect attempts. Please start again.', 'email' => ''];
            }

            if (!hash_equals($request['code_hash'], hashVerificationCode($code))) {
                $pdo->prepare("UPDATE email_change_requests SET attempts = attempts + 1 WHERE id = ?")
                    ->execute([$request['id']]);
                $left = LOGIN_CODE_MAX_ATTEMPTS - ((int) $request['attempts'] + 1);
                return [
                    'ok' => false,
                    'error' => 'Incorrect code. ' . ($left > 0 ? $left . ' attempt(s) remaining.' : 'Please start again.'),
                    'email' => '',
                ];
            }

            // Make sure nobody claimed the address in the meantime
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
            $check->execute([$request['new_email'], $user['id']]);
            if ($check->fetch()) {
                $pdo->prepare("UPDATE email_change_requests SET used_at = NOW() WHERE id = ?")
                    ->execute([$request['id']]);
                return ['ok' => false, 'error' => 'That email address is already registered to another account.', 'email' => ''];
            }

            $pdo->prepare("UPDATE users SET email = ?, email_verified_at = NOW(), updated_at = NOW() WHERE id = ?")
                ->execute([$request['new_email'], $user['id']]);
            $pdo->prepare("UPDATE email_change_requests SET used_at = NOW() WHERE id = ?")
                ->execute([$request['id']]);

            logAudit($user['id'], 'email_changed', 'users', $user['id'],
                ['email' => $request['old_email']], ['email' => $request['new_email']]);

            $_SESSION['user_email'] = $request['new_email'];

            return ['ok' => true, 'error' => '', 'email' => $request['new_email']];
        } catch (PDOException $e) {
            error_log('confirmEmailChange error: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not complete the email change. Please try again.', 'email' => ''];
        }
    }

    /**
     * Create a password reset token + code and email them to the user.
     *
     * @return array ['sent' => bool, 'error' => string]
     */
    function issuePasswordReset(PDO $pdo, array $user) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_resets
                WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmt->execute([$user['email']]);
            if ((int) $stmt->fetchColumn() >= 5) {
                return ['sent' => false, 'error' => 'Too many reset requests for this account. Please try again in an hour.'];
            }

            $token = bin2hex(random_bytes(32));
            $code  = generateVerificationCode(6);

            // Void previous reset requests for this address
            $pdo->prepare("DELETE FROM password_resets WHERE email = ? AND used_at IS NULL")
                ->execute([$user['email']]);

            $pdo->prepare("INSERT INTO password_resets (email, token, code_hash, expires_at, ip_address)
                VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)")
                ->execute([
                    $user['email'],
                    hash('sha256', $token),
                    hashVerificationCode($code),
                    RESET_TOKEN_TTL_MINUTES,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
        } catch (PDOException $e) {
            error_log('issuePasswordReset DB error: ' . $e->getMessage());
            return ['sent' => false, 'error' => 'Could not start the password reset. Please try again.'];
        }

        $firstName = explode(' ', trim($user['fullname']))[0];
        $resetUrl = SITE_URL . '/reset-password.php?token=' . $token . '&email=' . rawurlencode($user['email']);

        $body = '<p>Hi ' . e($firstName) . ',</p>
            <p>We received a request to reset the password for your ' . e(SITE_NAME)
            . ' account. Click the button below to choose a new password.</p>'
            . buildEmailButton($resetUrl, 'Reset My Password') .
            '<p style="font-size:13px; color:#777;">Button not working? Copy and paste this link into your browser:<br>
            <a href="' . e($resetUrl) . '" style="color:#2a5298; word-break:break-all;">'
            . e($resetUrl) . '</a></p>
            <hr style="border:none; border-top:1px solid #eee; margin:24px 0;">
            <p style="margin:0 0 6px;">Prefer a code? Enter this on the reset page:</p>'
            . buildCodeBlock($code) .
            '<p style="text-align:center; color:#777; font-size:13px;">The link and code both expire in '
            . RESET_TOKEN_TTL_MINUTES . ' minutes.</p>
            <p style="font-size:13px; color:#c0392b;">If you did not request a password reset, you can safely
            ignore this email - your password will not change.</p>';

        $sent = sendMailMessage(
            $user['email'],
            'Reset your ' . SITE_NAME . ' password',
            buildEmailTemplate('Password Reset', $body, 'Reset your password - code ' . $code)
        );

        if (!$sent) {
            return ['sent' => false, 'error' => getLastMailError() ?: 'We could not send the reset email.'];
        }

        return ['sent' => true, 'error' => ''];
    }

    /**
     * Look up a valid password reset row by token or by email + code.
     *
     * @return array|null
     */
    function findValidPasswordReset(PDO $pdo, $email, $token = null, $code = null) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM password_resets
                WHERE email = ? AND used_at IS NULL AND expires_at > NOW()
                ORDER BY id DESC LIMIT 1");
            $stmt->execute([$email]);
            $row = $stmt->fetch();

            if (!$row) {
                return null;
            }

            if ($token !== null && $token !== '') {
                return hash_equals($row['token'], hash('sha256', $token)) ? $row : null;
            }

            if ($code !== null && $code !== '') {
                $code = preg_replace('/\D/', '', $code);
                if ($row['code_hash'] && hash_equals($row['code_hash'], hashVerificationCode($code))) {
                    return $row;
                }
                $pdo->prepare("UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?")
                    ->execute([$row['id']]);
                return null;
            }

            return null;
        } catch (PDOException $e) {
            error_log('findValidPasswordReset error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Email the user confirming their password was changed.
     */
    function sendPasswordChangedEmail(array $user) {
        $firstName = explode(' ', trim($user['fullname']))[0];

        $body = '<p>Hi ' . e($firstName) . ',</p>
            <p>Your ' . e(SITE_NAME) . ' password was changed on <strong>'
            . date('F j, Y \a\t g:i A') . '</strong>.</p>
            <p>If this was you, no further action is needed.</p>
            <p style="color:#c0392b;"><strong>If this was not you</strong>, contact us immediately on '
            . e(WHATSAPP_NUMBER) . ' or ' . e(ADMIN_EMAIL) . '.</p>';

        return sendMailMessage(
            $user['email'],
            'Your ' . SITE_NAME . ' password was changed',
            buildEmailTemplate('Password Changed', $body, 'Your password was just changed')
        );
    }

    /**
     * Reusable "contact an admin" panel shown on the pending-approval screens.
     *
     * @param string $context Short text explaining why they are contacting us
     * @return string HTML
     */
    function renderSupportContactPanel($context = 'I registered as an organizer on VoteHubGH and would like my account approved.') {
        $waLink = WHATSAPP_LINK . '?text=' . rawurlencode($context);
        $tel    = str_replace(' ', '', SUPPORT_PHONE);
        $telAlt = str_replace(' ', '', SUPPORT_PHONE_ALT);

        ob_start();
        ?>
        <div class="auth-contacts">
            <strong>Need it approved faster?</strong>
            <a href="<?php echo e($waLink); ?>" target="_blank" rel="noopener" class="auth-contact-whatsapp">
                <i class="bi bi-whatsapp"></i> Chat on WhatsApp
            </a>
            <div class="auth-contact-row">
                <a href="tel:<?php echo e($tel); ?>"><i class="bi bi-telephone"></i> <?php echo e(SUPPORT_PHONE); ?></a>
                <a href="tel:<?php echo e($telAlt); ?>"><i class="bi bi-telephone"></i> <?php echo e(SUPPORT_PHONE_ALT); ?></a>
            </div>
            <a href="mailto:<?php echo e(ADMIN_EMAIL); ?>"><i class="bi bi-envelope"></i> <?php echo e(ADMIN_EMAIL); ?></a>
            <p>Support hours: Monday to Saturday, 8:00 AM to 8:00 PM (GMT)</p>
        </div>
        <?php
        return ob_get_clean();
    }
}
