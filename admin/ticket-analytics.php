<?php
/**
 * Admin Ticket Analytics Dashboard
 * Awards Voting System
 */

require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied. Admin privileges required.');
    redirect('login.php');
}

$page_title = 'Ticket Analytics';
$pdo = getDBConnection();

// --- Key Metrics ---
$ticketStats = [
    'total_revenue' => $pdo->query("SELECT COALESCE(SUM(t2.amount), 0) FROM transactions t2 WHERE t2.ticket_category_id IS NOT NULL AND t2.status = 'success'")->fetchColumn(),
    'total_tickets_sold' => $pdo->query("SELECT COALESCE(SUM(tk.quantity), 0) FROM tickets tk")->fetchColumn(),
    'total_orders' => $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
    'unique_buyers' => $pdo->query("SELECT COUNT(DISTINCT phone) FROM tickets WHERE phone IS NOT NULL AND phone != ''")->fetchColumn(),
    'revenue_today' => $pdo->query("SELECT COALESCE(SUM(t2.amount), 0) FROM transactions t2 WHERE t2.ticket_category_id IS NOT NULL AND t2.status = 'success' AND DATE(t2.created_at) = CURDATE()")->fetchColumn(),
    'tickets_today' => $pdo->query("SELECT COALESCE(SUM(tk.quantity), 0) FROM tickets tk WHERE DATE(tk.created_at) = CURDATE()")->fetchColumn(),
    'pending_payments' => $pdo->query("SELECT COUNT(*) FROM transactions WHERE ticket_category_id IS NOT NULL AND status = 'pending'")->fetchColumn(),
];

// --- Revenue by Event ---
$revenueByEventStmt = $pdo->query("
    SELECT e.id, e.title, e.event_code, e.start_date, e.end_date, e.status,
           COALESCE(SUM(CASE WHEN t2.status = 'success' THEN t2.amount ELSE 0 END), 0) as revenue,
           COALESCE(SUM(CASE WHEN t2.status = 'success' THEN t2.quantity ELSE 0 END), 0) as tickets_sold,
           COUNT(DISTINCT CASE WHEN t2.status = 'success' THEN t2.id END) as order_count
    FROM events e
    LEFT JOIN transactions t2 ON t2.event_id = e.id AND t2.ticket_category_id IS NOT NULL
    WHERE e.event_type IN ('ticketing', 'both')
    GROUP BY e.id
    ORDER BY revenue DESC
    LIMIT 10
");
$revenueByEvent = $revenueByEventStmt->fetchAll();

// --- Revenue by Ticket Category ---
$revenueByCategoryStmt = $pdo->query("
    SELECT tc.name as category_name, e.title as event_title,
           tc.price as unit_price,
           COALESCE(SUM(CASE WHEN t2.status = 'success' THEN t2.amount ELSE 0 END), 0) as revenue,
           COALESCE(SUM(CASE WHEN t2.status = 'success' THEN t2.quantity ELSE 0 END), 0) as qty_sold
    FROM ticket_categories tc
    JOIN events e ON tc.event_id = e.id
    LEFT JOIN transactions t2 ON t2.ticket_category_id = tc.id
    GROUP BY tc.id
    ORDER BY revenue DESC
    LIMIT 15
");
$revenueByCategory = $revenueByCategoryStmt->fetchAll();

// --- Daily Sales Trend (last 30 days) ---
$dailyTrendStmt = $pdo->query("
    SELECT DATE(t2.created_at) as sale_date,
           COALESCE(SUM(t2.amount), 0) as revenue,
           COALESCE(SUM(t2.quantity), 0) as tickets_sold,
           COUNT(*) as order_count
    FROM transactions t2
    WHERE t2.ticket_category_id IS NOT NULL AND t2.status = 'success'
      AND t2.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(t2.created_at)
    ORDER BY sale_date ASC
");
$dailyTrend = $dailyTrendStmt->fetchAll();

// Prepare chart data
$chartLabels = [];
$chartRevenue = [];
$chartTickets = [];
// Fill in all 30 days (including days with 0 sales)
$trendMap = [];
foreach ($dailyTrend as $row) { $trendMap[$row['sale_date']] = $row; }
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = date('M d', strtotime($date));
    $chartRevenue[] = floatval($trendMap[$date]['revenue'] ?? 0);
    $chartTickets[] = intval($trendMap[$date]['tickets_sold'] ?? 0);
}

// --- Monthly Revenue (last 6 months) ---
$monthlyTrendStmt = $pdo->query("
    SELECT DATE_FORMAT(t2.created_at, '%Y-%m') as sale_month,
           DATE_FORMAT(t2.created_at, '%b %Y') as month_label,
           COALESCE(SUM(t2.amount), 0) as revenue,
           COALESCE(SUM(t2.quantity), 0) as tickets_sold
    FROM transactions t2
    WHERE t2.ticket_category_id IS NOT NULL AND t2.status = 'success'
      AND t2.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY sale_month
    ORDER BY sale_month ASC
");
$monthlyTrend = $monthlyTrendStmt->fetchAll();

$monthLabels = array_column($monthlyTrend, 'month_label');
$monthRevenue = array_map('floatval', array_column($monthlyTrend, 'revenue'));
$monthTickets = array_map('intval', array_column($monthlyTrend, 'tickets_sold'));

// --- Recent Ticket Purchases ---
$recentTicketsStmt = $pdo->query("
    SELECT tk.*, tc.name as category_name, tc.price as unit_price,
           e.title as event_title, e.start_date as event_date,
           t2.amount, t2.reference, t2.status as tx_status, t2.payment_method, t2.created_at as purchase_date,
           u.fullname as buyer_name, u.email as buyer_email
    FROM tickets tk
    JOIN ticket_categories tc ON tk.ticket_category_id = tc.id
    JOIN events e ON tk.event_id = e.id
    LEFT JOIN transactions t2 ON tk.transaction_id = t2.id
    LEFT JOIN users u ON t2.user_id = u.id
    ORDER BY tk.created_at DESC
    LIMIT 20
");
$recentTickets = $recentTicketsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/logo/favicon.png?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/portal.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        .metric-card { border: none; border-radius: 1rem; transition: transform 0.2s; }
        .metric-card:hover { transform: translateY(-4px); }
        .metric-icon { width: 56px; height: 56px; border-radius: 0.875rem; display: flex; align-items: center; justify-content: center; }
        .metric-value { font-size: 1.75rem; font-weight: 700; line-height: 1.2; }
        .metric-label { font-size: 0.8rem; color: #6b7280; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .metric-sub { font-size: 0.78rem; }
        .chart-container { position: relative; height: 300px; }
        .ticket-code-badge { font-family: 'Courier New', monospace; font-weight: 700; font-size: 0.85rem; letter-spacing: 1px; }
    </style>
</head>
<body class="admin-body">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-main">
        <?php include 'includes/topbar.php'; ?>

        <div class="admin-content p-4">
            <!-- Header -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                <div>
                    <h2 class="mb-1"><i class="bi bi-ticket-perforated me-2"></i>Ticket Analytics</h2>
                    <p class="text-muted mb-0 small">Comprehensive overview of ticket sales, revenue, and trends.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="transactions.php?search=TKT" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-credit-card me-1"></i>All Ticket Transactions
                    </a>
                </div>
            </div>

            <!-- Key Metric Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card metric-card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2">
                                <div class="metric-icon bg-success bg-opacity-10 text-success me-3">
                                    <i class="bi bi-currency-exchange fs-4"></i>
                                </div>
                                <div>
                                    <div class="metric-value text-success">GHS <?php echo number_format($ticketStats['total_revenue'], 2); ?></div>
                                    <div class="metric-label">Total Revenue</div>
                                </div>
                            </div>
                            <div class="metric-sub text-muted">
                                <i class="bi bi-arrow-up text-success"></i> GHS <?php echo number_format($ticketStats['revenue_today'], 2); ?> today
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card metric-card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2">
                                <div class="metric-icon bg-primary bg-opacity-10 text-primary me-3">
                                    <i class="bi bi-ticket-perforated-fill fs-4"></i>
                                </div>
                                <div>
                                    <div class="metric-value text-primary"><?php echo number_format($ticketStats['total_tickets_sold']); ?></div>
                                    <div class="metric-label">Tickets Sold</div>
                                </div>
                            </div>
                            <div class="metric-sub text-muted">
                                <i class="bi bi-arrow-up text-success"></i> <?php echo number_format($ticketStats['tickets_today']); ?> today
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card metric-card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2">
                                <div class="metric-icon bg-info bg-opacity-10 text-info me-3">
                                    <i class="bi bi-cart-check fs-4"></i>
                                </div>
                                <div>
                                    <div class="metric-value text-info"><?php echo number_format($ticketStats['total_orders']); ?></div>
                                    <div class="metric-label">Total Orders</div>
                                </div>
                            </div>
                            <div class="metric-sub text-muted">
                                <?php echo number_format($ticketStats['unique_buyers']); ?> unique buyers
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card metric-card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2">
                                <div class="metric-icon bg-warning bg-opacity-10 text-warning me-3">
                                    <i class="bi bi-hourglass-split fs-4"></i>
                                </div>
                                <div>
                                    <div class="metric-value text-warning"><?php echo number_format($ticketStats['pending_payments']); ?></div>
                                    <div class="metric-label">Pending Payments</div>
                                </div>
                            </div>
                            <div class="metric-sub">
                                <a href="transactions.php?status=pending&search=TKT" class="text-warning text-decoration-none">Review pending <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <!-- Daily Sales Trend -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Daily Sales (Last 30 Days)</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="dailySalesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Revenue -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-bar-chart-fill me-2"></i>Monthly Revenue</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="monthlyRevenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Breakdowns -->
            <div class="row g-4 mb-4">
                <!-- By Event -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Revenue by Event</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Event</th>
                                        <th>Code</th>
                                        <th class="text-center">Tickets Sold</th>
                                        <th class="text-end">Revenue</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($revenueByEvent)): ?>
                                        <tr><td colspan="5" class="text-center py-4 text-muted">No ticketed events yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($revenueByEvent as $evt): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?php echo e($evt['title']); ?></div>
                                                    <small class="text-muted"><?php echo date('M d', strtotime($evt['start_date'])); ?> - <?php echo date('M d, Y', strtotime($evt['end_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($evt['event_code']): ?>
                                                        <code class="text-primary"><?php echo e($evt['event_code']); ?></code>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-primary bg-opacity-10 text-primary"><?php echo number_format($evt['tickets_sold']); ?></span>
                                                </td>
                                                <td class="text-end fw-bold">GHS <?php echo number_format($evt['revenue'], 2); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $evt['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($evt['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- By Category -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-tags me-2"></i>Revenue by Category</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Category</th>
                                        <th class="text-center">Sold</th>
                                        <th class="text-end">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($revenueByCategory)): ?>
                                        <tr><td colspan="3" class="text-center py-4 text-muted">No categories yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($revenueByCategory as $cat): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?php echo e($cat['category_name']); ?></div>
                                                    <small class="text-muted"><?php echo e($cat['event_title']); ?> · GHS <?php echo number_format($cat['unit_price'], 2); ?>/ea</small>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-info bg-opacity-10 text-info"><?php echo number_format($cat['qty_sold']); ?></span>
                                                </td>
                                                <td class="text-end fw-bold">GHS <?php echo number_format($cat['revenue'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Ticket Purchases -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Ticket Purchases</h5>
                    <span class="badge bg-primary bg-opacity-10 text-primary"><?php echo count($recentTickets); ?> latest</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Ticket Code</th>
                                <th>Buyer</th>
                                <th>Event</th>
                                <th>Category</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Amount</th>
                                <th>Method</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentTickets)): ?>
                                <tr><td colspan="8" class="text-center py-4 text-muted">No ticket purchases yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentTickets as $tk): ?>
                                    <tr>
                                        <td>
                                            <span class="ticket-code-badge badge bg-dark bg-opacity-10 text-dark px-2 py-1">
                                                <i class="bi bi-qr-code me-1"></i><?php echo e($tk['ticket_code']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($tk['buyer_name']): ?>
                                                <div class="fw-bold small"><?php echo e($tk['buyer_name']); ?></div>
                                                <small class="text-muted"><?php echo e($tk['buyer_email']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted small">Guest</span>
                                            <?php endif; ?>
                                            <?php if ($tk['phone']): ?>
                                                <div class="small text-primary"><i class="bi bi-phone me-1"></i><?php echo e($tk['phone']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small fw-bold"><?php echo e($tk['event_title']); ?></div>
                                            <small class="text-muted"><?php echo date('M d, Y', strtotime($tk['event_date'])); ?></small>
                                        </td>
                                        <td><span class="badge bg-primary bg-opacity-10 text-primary"><?php echo e($tk['category_name']); ?></span></td>
                                        <td class="text-center"><?php echo $tk['quantity']; ?></td>
                                        <td class="text-end fw-bold">GHS <?php echo number_format($tk['amount'] ?? 0, 2); ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?php echo strtoupper($tk['payment_method'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($tk['purchase_date'] ?? $tk['created_at'])); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
    <script>
    // Daily Sales Chart
    new Chart(document.getElementById('dailySalesChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [
                {
                    label: 'Revenue (GHS)',
                    data: <?php echo json_encode($chartRevenue); ?>,
                    borderColor: '#00693C',
                    backgroundColor: 'rgba(0, 105, 60, 0.08)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2.5,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    yAxisID: 'y'
                },
                {
                    label: 'Tickets Sold',
                    data: <?php echo json_encode($chartTickets); ?>,
                    borderColor: '#0F2C7A',
                    backgroundColor: 'rgba(15, 44, 122, 0.08)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2.5,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { type: 'linear', position: 'left', title: { display: true, text: 'Revenue (GHS)' }, grid: { color: 'rgba(0,0,0,0.04)' } },
                y1: { type: 'linear', position: 'right', title: { display: true, text: 'Tickets' }, grid: { drawOnChartArea: false } },
                x: { grid: { display: false }, ticks: { maxRotation: 45, font: { size: 10 } } }
            },
            plugins: { legend: { position: 'top' } }
        }
    });

    // Monthly Revenue Chart
    new Chart(document.getElementById('monthlyRevenueChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($monthLabels); ?>,
            datasets: [{
                label: 'Revenue (GHS)',
                data: <?php echo json_encode($monthRevenue); ?>,
                backgroundColor: 'rgba(0, 105, 60, 0.7)',
                borderColor: '#00693C',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
                x: { grid: { display: false } }
            },
            plugins: { legend: { display: false } }
        }
    });
    </script>
</body>
</html>
