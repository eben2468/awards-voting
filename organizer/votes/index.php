<?php
/**
 * Organizer - Votes Viewer
 * Awards Voting System
 */

require_once '../../config/database.php';

// Require organizer
if (!isLoggedIn() || !isOrganizer()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$page_title = 'View Votes';
$pdo = getDBConnection();
$organizer_id = $_SESSION['user_id'];

$eventId = intval($_GET['event'] ?? 0);

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Fetch events (only this organizer's)
$eventsStmt = $pdo->prepare("SELECT id, title FROM events WHERE organizer_id = ? ORDER BY title");
$eventsStmt->execute([$organizer_id]);
$events = $eventsStmt->fetchAll();

// Build query
$whereClause = "WHERE e.organizer_id = ?";
$params = [$organizer_id];

if ($eventId > 0) {
    $whereClause .= " AND v.event_id = ?";
    $params[] = $eventId;
}

// Count total
$countSql = "SELECT SUM(vote_count) FROM votes v JOIN events e ON v.event_id = e.id $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalVotes = $stmt->fetchColumn() ?: 0;

$countRecordsSql = "SELECT COUNT(*) FROM votes v JOIN events e ON v.event_id = e.id $whereClause";
$stmt = $pdo->prepare($countRecordsSql);
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Fetch votes
$sql = "
    SELECT v.*, 
           u.fullname as user_name, u.email as user_email,
           n.name as nominee_name, n.shortcode,
           c.name as category_name,
           e.title as event_title
    FROM votes v
    LEFT JOIN users u ON v.user_id = u.id
    JOIN nominees n ON v.nominee_id = n.id
    JOIN categories c ON v.category_id = c.id
    JOIN events e ON v.event_id = e.id
    $whereClause
    ORDER BY v.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$votes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/logo/favicon.png?v=2">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/logo/apple-touch-icon.png?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/portal.css'); ?>">
</head>
<body class="admin-body">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="admin-main">
        <?php include '../includes/topbar.php'; ?>
        
        <div class="admin-content p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Votes</h2>
                    <p class="text-muted mb-0">View voting records for your events</p>
                </div>
                <span class="badge bg-primary fs-6"><?php echo number_format($totalVotes); ?> Total Votes</span>
            </div>

            <!-- Filter -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Filter by Event</label>
                            <select name="event" class="form-select" onchange="this.form.submit()">
                                <option value="">All My Events</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event['id']; ?>" <?php echo $eventId == $event['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Votes Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if (empty($votes)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-1 text-muted mb-3"></i>
                            <h4>No Votes Yet</h4>
                            <p class="text-muted">Votes will appear here once users start voting.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Nominee</th>
                                        <th>Category</th>
                                        <th>Event</th>
                                        <th>Count</th>
                                        <th>IP Address</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($votes as $vote): ?>
                                        <tr>
                                            <td>#<?php echo $vote['id']; ?></td>
                                            <td>
                                                <?php if ($vote['user_name']): ?>
                                                    <strong><?php echo htmlspecialchars($vote['user_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo $vote['user_email']; ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Guest User</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($vote['nominee_name']); ?>
                                                <br><code class="small"><?php echo $vote['shortcode']; ?></code>
                                            </td>
                                            <td><?php echo htmlspecialchars($vote['category_name']); ?></td>
                                            <td><?php echo htmlspecialchars($vote['event_title']); ?></td>
                                            <td><span class="badge bg-info"><?php echo number_format($vote['vote_count']); ?></span></td>
                                            <td><small class="text-muted"><?php echo $vote['ip_address']; ?></small></td>
                                            <td>
                                                <small>
                                                    <?php echo date('M d, Y', strtotime($vote['created_at'])); ?>
                                                    <br><?php echo date('H:i:s', strtotime($vote['created_at'])); ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?event=<?php echo $eventId; ?>&page=<?php echo $page - 1; ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?event=<?php echo $eventId; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?event=<?php echo $eventId; ?>&page=<?php echo $page + 1; ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
</body>
</html>
