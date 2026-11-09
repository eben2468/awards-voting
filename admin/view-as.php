<?php
/**
 * Admin "View as organizer" — read-only impersonation.
 *
 * Starting:  swaps the session to the organizer, remembers the real admin, and
 *            logs it. Every page then shows what the organizer sees, but the
 *            read-only guard in config/database.php blocks any change.
 * Exiting:   restores the admin's own session and logs it.
 *
 * Both ends are written to the audit log against the real administrator, so
 * there is always a record of who looked at which account and when.
 */

require_once '../config/database.php';

$pdo = getDBConnection();

// ---- Exit: hand control back to the administrator ----
if (isset($_GET['exit'])) {
    if (!isImpersonating()) {
        redirect('login.php');
    }

    $admin = $_SESSION['impersonator'];
    $viewedId = getCurrentUserId();

    logAudit($admin['id'], 'impersonation_end', 'users', $viewedId, null, [
        'viewed_user' => $_SESSION['user_name'] ?? null,
    ]);

    // Rebuild the admin's own session from the database
    $stmt = $pdo->prepare("SELECT id, fullname, email, role, avatar FROM users WHERE id = ? AND role = 'admin'");
    $stmt->execute([$admin['id']]);
    $me = $stmt->fetch();

    unset($_SESSION['impersonator']);

    if (!$me) {
        // The admin account is gone — safest to end the session entirely
        session_destroy();
        redirect('login.php');
    }

    $_SESSION['user_id']     = $me['id'];
    $_SESSION['user_role']   = $me['role'];
    $_SESSION['user_name']   = $me['fullname'];
    $_SESSION['fullname']    = $me['fullname'];
    $_SESSION['user_email']  = $me['email'];
    $_SESSION['user_avatar'] = $me['avatar'] ?? null;

    setFlash('info', 'You have left the view-as session.');
    redirect('admin/users/');
}

// ---- Start: only a real admin, not someone already impersonating ----
if (!isLoggedIn() || !isAdmin() || isImpersonating()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

if (!verifyCSRFToken($_GET['token'] ?? '')) {
    setFlash('danger', 'Your session expired. Please try again.');
    redirect('admin/users/');
}

$organizerId = (int) ($_GET['start'] ?? 0);

$stmt = $pdo->prepare("SELECT id, fullname, email, role, avatar FROM users WHERE id = ? AND role = 'organizer'");
$stmt->execute([$organizerId]);
$organizer = $stmt->fetch();

if (!$organizer) {
    setFlash('danger', 'That organizer account could not be found.');
    redirect('admin/users/');
}

// Remember who the admin really is, then become the organizer
$_SESSION['impersonator'] = [
    'id'      => getCurrentUserId(),
    'name'    => $_SESSION['user_name'] ?? 'Administrator',
    'email'   => $_SESSION['user_email'] ?? null,
    'started' => time(),
];

logAudit($_SESSION['impersonator']['id'], 'impersonation_start', 'users', $organizer['id'], null, [
    'viewed_user'  => $organizer['fullname'],
    'viewed_email' => $organizer['email'],
]);

$_SESSION['user_id']     = $organizer['id'];
$_SESSION['user_role']   = 'organizer';
$_SESSION['user_name']   = $organizer['fullname'];
$_SESSION['fullname']    = $organizer['fullname'];
$_SESSION['user_email']  = $organizer['email'];
$_SESSION['user_avatar'] = $organizer['avatar'] ?? null;

setFlash('info', 'You are now viewing ' . $organizer['fullname'] . "'s account (read-only).");
redirect('organizer/');
