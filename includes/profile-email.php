<?php
/**
 * Profile Email Address Management
 * Awards Voting System
 *
 * Shared by admin/profile.php and organizer/profile.php so both pages behave
 * identically. Changing an email address is a two-step action:
 *
 *   1. The user enters their password and the new address; a 6-digit code is
 *      emailed to the NEW address (and a security alert to the old one).
 *   2. The user enters that code to confirm the change.
 */

require_once __DIR__ . '/auth-functions.php';

if (!defined('PROFILE_EMAIL_LOADED')) {
    define('PROFILE_EMAIL_LOADED', true);

    /**
     * Fetch the user's outstanding email change request, if any.
     *
     * @return array|null
     */
    function getPendingEmailChange(PDO $pdo, $userId) {
        try {
            // The expiry label is formatted by MySQL so it matches the database
            // clock that decides when the code actually stops working.
            $stmt = $pdo->prepare("SELECT *, DATE_FORMAT(expires_at, '%l:%i %p') AS expires_label
                FROM email_change_requests
                WHERE user_id = ? AND used_at IS NULL AND expires_at > NOW()
                ORDER BY id DESC LIMIT 1");
            $stmt->execute([$userId]);
            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            // Table missing means the migration has not been run yet
            return null;
        }
    }

    /**
     * Handle the email-change POST actions on a profile page.
     * Redirects on success; returns an array of error strings otherwise.
     *
     * @param PDO    $pdo
     * @param array  $user          The logged-in user row
     * @param string $action        The submitted `action` value
     * @param string $redirectPath  Path relative to SITE_URL, e.g. 'admin/profile.php'
     * @return array
     */
    function processProfileEmailAction(PDO $pdo, array $user, $action, $redirectPath) {
        $errors = [];

        // ---- Step 1: request the change ----
        if ($action === 'request_email_change') {
            $newEmail = strtolower(trim(sanitize($_POST['new_email'] ?? '')));
            $password = $_POST['current_password_email'] ?? '';

            if (empty($password)) {
                $errors[] = 'Enter your current password to change your email address.';
            } elseif (!password_verify($password, $user['password'])) {
                $errors[] = 'The password you entered is incorrect.';
            }

            if (empty($newEmail)) {
                $errors[] = 'Please enter the new email address.';
            } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            } elseif (strtolower($newEmail) === strtolower($user['email'])) {
                $errors[] = 'That is already your current email address.';
            } else {
                $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
                $check->execute([$newEmail, $user['id']]);
                if ($check->fetch()) {
                    $errors[] = 'That email address is already registered to another account.';
                }
            }

            if (empty($errors)) {
                $result = issueEmailChangeCode($pdo, $user, $newEmail);

                if ($result['sent']) {
                    setFlash('info', 'We sent a 6-digit confirmation code to <strong>'
                        . htmlspecialchars($newEmail) . '</strong>. Enter it below to finish the change.');
                    redirect($redirectPath);
                }

                $errors[] = 'Could not send the confirmation email. ' . $result['error'];
            }
        }

        // ---- Step 2: confirm with the emailed code ----
        if ($action === 'confirm_email_change') {
            $code = trim($_POST['email_code'] ?? '');
            $result = confirmEmailChange($pdo, $user, $code);

            if ($result['ok']) {
                setFlash('success', 'Your email address has been updated to <strong>'
                    . htmlspecialchars($result['email']) . '</strong>. Use it the next time you log in.');
                redirect($redirectPath);
            }

            $errors[] = $result['error'];
        }

        // ---- Abandon the pending change ----
        if ($action === 'cancel_email_change') {
            try {
                $pdo->prepare("UPDATE email_change_requests SET used_at = NOW()
                    WHERE user_id = ? AND used_at IS NULL")->execute([$user['id']]);
                setFlash('info', 'Email change cancelled. Your address stays as ' . htmlspecialchars($user['email']) . '.');
            } catch (PDOException $e) {
                error_log('Cancel email change error: ' . $e->getMessage());
            }
            redirect($redirectPath);
        }

        return $errors;
    }

    /**
     * Render the "Email Address" card for a profile page.
     *
     * @param PDO   $pdo
     * @param array $user
     */
    function renderEmailChangeCard(PDO $pdo, array $user) {
        $pending = getPendingEmailChange($pdo, $user['id']);
        ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-envelope-at me-2 text-primary"></i>Email Address</h5>
                <?php if (!empty($user['email_verified_at'])): ?>
                    <span class="badge bg-success bg-opacity-10 text-success">
                        <i class="bi bi-patch-check-fill me-1"></i>Verified
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">

                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 p-3 bg-light rounded-3 mb-4">
                    <div>
                        <div class="text-muted small">Current email address</div>
                        <div class="fw-bold"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                    <div class="text-muted small">
                        <i class="bi bi-shield-lock me-1"></i>Used for login codes &amp; password resets
                    </div>
                </div>

                <?php if ($pending): ?>
                    <!-- A change is waiting for its confirmation code -->
                    <div class="alert alert-info d-flex align-items-start gap-2">
                        <i class="bi bi-hourglass-split mt-1"></i>
                        <div>
                            <strong>Confirmation pending</strong><br>
                            <span class="small">
                                We emailed a 6-digit code to <strong><?php echo htmlspecialchars($pending['new_email']); ?></strong>.
                                Enter it below to complete the change. The code expires at
                                <?php echo htmlspecialchars($pending['expires_label']); ?>.
                            </span>
                        </div>
                    </div>

                    <form method="POST" action="" class="row g-3 align-items-end">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="confirm_email_change">

                        <div class="col-md-5">
                            <label for="email_code" class="form-label fw-bold">Confirmation Code</label>
                            <input type="text" class="form-control text-center fw-bold"
                                   id="email_code" name="email_code"
                                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                                   autocomplete="one-time-code" placeholder="000000"
                                   style="font-size:1.4rem; letter-spacing:.6rem;" required>
                        </div>
                        <div class="col-md-7 d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-check-lg me-2"></i>Confirm New Email
                            </button>
                        </div>
                    </form>

                    <form method="POST" action="" class="mt-3">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="cancel_email_change">
                        <button type="submit" class="btn btn-link text-danger p-0 text-decoration-none small">
                            <i class="bi bi-x-circle me-1"></i>Cancel this email change
                        </button>
                    </form>

                <?php else: ?>
                    <!-- Request a change -->
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="request_email_change">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="new_email" class="form-label fw-bold">New Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="new_email" name="new_email"
                                           placeholder="you@gmail.com" required>
                                </div>
                                <div class="form-text">We will send a confirmation code to this address.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="current_password_email" class="form-label fw-bold">Current Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="current_password_email"
                                           name="current_password_email" placeholder="Confirm it's you" required>
                                </div>
                                <div class="form-text">Required for your security.</div>
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="bi bi-send me-2"></i>Send Confirmation Code
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="alert alert-light border small text-muted mt-4 mb-0">
                        <i class="bi bi-info-circle me-1 text-primary"></i>
                        Your email address is where we send your login verification codes, so make sure you
                        can access the new inbox before you change it. We will also email your old address
                        to let you know the change was requested.
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php
    }
}
