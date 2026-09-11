<?php
/**
 * Organizer - Reports & Export
 * Awards Voting System
 */

require_once '../../config/database.php';

// Require organizer
if (!isLoggedIn() || !isOrganizer()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$page_title = 'Reports & Export';
$pdo = getDBConnection();
$organizer_id = $_SESSION['user_id'];

// Handle CSV Export
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    $eventId = intval($_GET['event'] ?? 0);
    
    // Verify event ownership if specified
    if ($eventId > 0) {
        $checkStmt = $pdo->prepare("SELECT id FROM events WHERE id = ? AND organizer_id = ?");
        $checkStmt->execute([$eventId, $organizer_id]);
        if (!$checkStmt->fetch()) {
            setFlash('danger', 'Access denied.');
            redirect('organizer/reports/');
        }
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $type . '_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    switch ($type) {
        case 'votes':
            fputcsv($output, ['Vote ID', 'User', 'Email', 'Nominee', 'Category', 'Event', 'Date']);
            $sql = "
                SELECT v.id, u.fullname, u.email, n.name as nominee, c.name as category, e.title as event, v.created_at
                FROM votes v
                LEFT JOIN users u ON v.user_id = u.id
                JOIN nominees n ON v.nominee_id = n.id
                JOIN categories c ON v.category_id = c.id
                JOIN events e ON v.event_id = e.id
                WHERE e.organizer_id = ?
            ";
            $params = [$organizer_id];
            if ($eventId > 0) {
                $sql .= " AND v.event_id = ?";
                $params[] = $eventId;
            }
            $sql .= " ORDER BY v.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $votes = $stmt->fetchAll();
            foreach ($votes as $v) {
                fputcsv($output, [$v['id'], $v['fullname'] ?? 'Guest', $v['email'] ?? 'N/A', $v['nominee'], $v['category'], $v['event'], $v['created_at']]);
            }
            break;
            
        case 'results':
            fputcsv($output, ['Rank', 'Nominee', 'Shortcode', 'Category', 'Event', 'Votes', 'Percentage']);
            $sql = "
                SELECT n.*, c.name as category, e.title as event,
                       (SELECT SUM(vote_count) FROM nominees WHERE category_id = n.category_id) as total_category_votes
                FROM nominees n
                JOIN categories c ON n.category_id = c.id
                JOIN events e ON c.event_id = e.id
                WHERE e.organizer_id = ?
            ";
            $params = [$organizer_id];
            if ($eventId > 0) {
                $sql .= " AND e.id = ?";
                $params[] = $eventId;
            }
            $sql .= " ORDER BY e.title, c.name, n.vote_count DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $nominees = $stmt->fetchAll();
            $rank = 0;
            $lastCategory = '';
            foreach ($nominees as $n) {
                if ($lastCategory !== $n['category']) { $rank = 1; $lastCategory = $n['category']; } else { $rank++; }
                $pct = $n['total_category_votes'] > 0 ? round(($n['vote_count'] / $n['total_category_votes']) * 100, 2) : 0;
                fputcsv($output, [$rank, $n['name'], $n['shortcode'], $n['category'], $n['event'], $n['vote_count'], $pct . '%']);
            }
            break;
            
        case 'tickets':
            fputcsv($output, ['Ticket ID', 'Ticket Code', 'Event', 'Category', 'Quantity', 'Phone', 'Amount (GHS)', 'Date']);
            $sql = "
                SELECT t.id, t.ticket_code, e.title as event, tc.name as category, t.quantity, t.phone, tr.amount, t.created_at
                FROM tickets t
                JOIN events e ON t.event_id = e.id
                JOIN ticket_categories tc ON t.ticket_category_id = tc.id
                LEFT JOIN transactions tr ON t.transaction_id = tr.id
                WHERE e.organizer_id = ?
            ";
            $params = [$organizer_id];
            if ($eventId > 0) {
                $sql .= " AND t.event_id = ?";
                $params[] = $eventId;
            }
            $sql .= " ORDER BY t.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $tickets = $stmt->fetchAll();
            foreach ($tickets as $t) {
                fputcsv($output, [$t['id'], $t['ticket_code'], $t['event'], $t['category'], $t['quantity'], $t['phone'], $t['amount'], $t['created_at']]);
            }
            break;
    }
    
    fclose($output);
    exit;
}

// Fetch events for filter (only this organizer's)
$eventsStmt = $pdo->prepare("SELECT id, title FROM events WHERE organizer_id = ? ORDER BY title");
$eventsStmt->execute([$organizer_id]);
$events = $eventsStmt->fetchAll();
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
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/admin.css'); ?>">
</head>
<body class="admin-body">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="admin-main">
        <?php include '../includes/topbar.php'; ?>
        
        <div class="admin-content p-4">
            <div class="mb-4">
                <h2 class="mb-1">Reports & Export</h2>
                <p class="text-muted mb-0">Generate and download reports for your events</p>
            </div>

            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Votes Report -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-5">
                            <div class="icon-box bg-primary bg-opacity-10 rounded-circle p-4 mx-auto mb-3" style="width: 80px; height: 80px;">
                                <i class="bi bi-check2-square text-primary fs-3"></i>
                            </div>
                            <h5>Votes Report</h5>
                            <p class="text-muted small mb-4">Export all votes with user details, nominees, and timestamps.</p>
                            <form action="" method="GET" class="d-grid gap-2">
                                <select name="event" class="form-select form-select-sm">
                                    <option value="">All My Events</option>
                                    <?php foreach ($events as $e): ?>
                                        <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="export" value="votes" class="btn btn-primary">
                                    <i class="bi bi-download me-2"></i>Export CSV
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Results Report -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-5">
                            <div class="icon-box bg-success bg-opacity-10 rounded-circle p-4 mx-auto mb-3" style="width: 80px; height: 80px;">
                                <i class="bi bi-graph-up text-success fs-3"></i>
                            </div>
                            <h5>Results Report</h5>
                            <p class="text-muted small mb-4">Export nominee rankings with vote counts and percentages.</p>
                            <form action="" method="GET" class="d-grid gap-2">
                                <select name="event" class="form-select form-select-sm">
                                    <option value="">All My Events</option>
                                    <?php foreach ($events as $e): ?>
                                        <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="export" value="results" class="btn btn-success">
                                    <i class="bi bi-download me-2"></i>Export CSV
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Tickets Report -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-5">
                            <div class="icon-box bg-warning bg-opacity-10 rounded-circle p-4 mx-auto mb-3" style="width: 80px; height: 80px;">
                                <i class="bi bi-ticket-perforated text-warning fs-3"></i>
                            </div>
                            <h5>Tickets Report</h5>
                            <p class="text-muted small mb-4">Export purchased tickets with categories, codes, quantity and phone.</p>
                            <form action="" method="GET" class="d-grid gap-2">
                                <select name="event" class="form-select form-select-sm">
                                    <option value="">All My Events</option>
                                    <?php foreach ($events as $e): ?>
                                        <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="export" value="tickets" class="btn btn-warning text-white">
                                    <i class="bi bi-download me-2"></i>Export CSV
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
</body>
</html>
