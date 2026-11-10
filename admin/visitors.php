<?php
/**
 * Admin - Website Visitors
 * Awards Voting System
 *
 * Totals for the public site: visitors and page views per day, plus which pages,
 * sources and kinds of device they came through. Counts only, no individual list.
 */

require_once '../config/database.php';
require_once '../includes/visit-tracker.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied. Admin privileges required.');
    redirect('login.php');
}

$page_title = 'Website Visitors';

$ranges = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', '180' => 'Last 180 days'];
$range = array_key_exists($_GET['range'] ?? '', $ranges) ? $_GET['range'] : '30';
$days = (int) $range;
$from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

// One row per day, newest first, with empty days filled in
$daily = [];
for ($i = 0; $i < $days; $i++) {
    $daily[date('Y-m-d', strtotime("-$i days"))] = ['visitors' => 0, 'views' => 0, 'new' => 0];
}
$rows = visitQuery("
    SELECT DATE(created_at) AS day, COUNT(DISTINCT visitor_id) AS visitors, COUNT(*) AS views, SUM(is_new) AS new_visitors
    FROM site_visits WHERE created_at >= ? GROUP BY DATE(created_at)
", [$from . ' 00:00:00'])->fetchAll();
foreach ($rows as $row) {
    if (isset($daily[$row['day']])) {
        $daily[$row['day']] = ['visitors' => (int) $row['visitors'], 'views' => (int) $row['views'], 'new' => (int) $row['new_visitors']];
    }
}

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="daily_visitors_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Visitors', 'New visitors', 'Page views']);
    foreach ($daily as $day => $count) {
        fputcsv($out, [$day, $count['visitors'], $count['new'], $count['views']]);
    }
    fclose($out);
    exit;
}

$totals = visitQuery("
    SELECT COUNT(*) AS views, COUNT(DISTINCT visitor_id) AS visitors,
           COUNT(DISTINCT session_id) AS visits, COALESCE(SUM(is_new), 0) AS new_visitors
    FROM site_visits WHERE created_at >= ?
", [$from . ' 00:00:00'])->fetch();
$today = $daily[date('Y-m-d')];
$online = (int) visitQuery("SELECT COUNT(DISTINCT visitor_id) FROM site_visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
$perVisit = $totals['visits'] ? $totals['views'] / $totals['visits'] : 0;
$returning = max(0, (int) $totals['visitors'] - (int) $totals['new_visitors']);

// Counts grouped by one column, largest first
$breakdown = function ($column, $limit = 6) use ($from) {
    return visitQuery("
        SELECT COALESCE(NULLIF($column, ''), 'Unknown') AS label, COUNT(DISTINCT visitor_id) AS visitors
        FROM site_visits WHERE created_at >= ?
        GROUP BY label ORDER BY visitors DESC LIMIT $limit
    ", [$from . ' 00:00:00'])->fetchAll();
};

$pages = visitQuery("
    SELECT path AS label, COUNT(*) AS views, COUNT(DISTINCT visitor_id) AS visitors
    FROM site_visits WHERE created_at >= ?
    GROUP BY path ORDER BY views DESC LIMIT 8
", [$from . ' 00:00:00'])->fetchAll();

// Where each visit started: the referrer on the first page of the visit
$sources = visitQuery("
    SELECT COALESCE(SUBSTRING_INDEX(v.referrer, '/', 1), 'Direct or typed in') AS label, COUNT(*) AS visitors
    FROM site_visits v
    JOIN (SELECT MIN(id) AS first_id FROM site_visits WHERE created_at >= ? GROUP BY session_id) f ON f.first_id = v.id
    GROUP BY label ORDER BY visitors DESC LIMIT 6
", [$from . ' 00:00:00'])->fetchAll();

$panels = [
    ['How people found you (visits)', 'bi-signpost-split', $sources],
    ['Device type', 'bi-phone', $breakdown('device_type')],
    ['Operating system', 'bi-windows', $breakdown('os')],
    ['Browser', 'bi-browser-chrome', $breakdown('browser')],
    ['Phone or computer model', 'bi-cpu', $breakdown('device_name')],
    ['Screen size', 'bi-aspect-ratio', $breakdown('screen')],
];

$chartDays = array_reverse($daily, true);
$chartMax = max(1, max(array_column($chartDays, 'visitors')));
$cards = [
    ['bi-people', 'primary', number_format($totals['visitors']), 'Visitors', $ranges[$range]],
    ['bi-eye', 'info', number_format($totals['views']), 'Page views', number_format($perVisit, 1) . ' pages per visit'],
    ['bi-calendar-day', 'success', number_format($today['visitors']), 'Visitors today', number_format($today['views']) . ' page views'],
    ['bi-broadcast', 'danger', number_format($online), 'On the site now', 'In the last 5 minutes'],
    ['bi-person-plus', 'warning', number_format($totals['new_visitors']), 'New visitors', 'First time on this browser'],
    ['bi-arrow-repeat', 'secondary', number_format($returning), 'Returning visitors', 'Came back again'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title . ' - ' . SITE_NAME); ?></title>
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/logo/favicon.png?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/portal.css'); ?>">
    <style>
        .vs-chart { display: flex; align-items: flex-end; gap: 3px; height: 180px; padding-top: 0.5rem; }
        .vs-bar { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; justify-content: flex-end; height: 100%; }
        .vs-bar span { display: block; min-height: 2px; border-radius: 3px 3px 0 0; background: var(--navy); }
        .vs-bar:hover span { background: var(--gold); }
        .vs-axis { display: flex; justify-content: space-between; margin-top: 0.4rem; font-size: 0.75rem; color: var(--ink-soft); }
        .vs-meter { height: 6px; margin-top: 0.3rem; border-radius: 999px; background: var(--paper-deep); overflow: hidden; }
        .vs-meter span { display: block; height: 100%; background: var(--navy); }
        .vs-panel li { display: block; }
        .vs-row { display: flex; justify-content: space-between; gap: 1rem; font-size: 0.9375rem; }
        .vs-row span:first-child { overflow-wrap: anywhere; }
    </style>
</head>
<body class="admin-body">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-main">
        <?php include 'includes/topbar.php'; ?>

        <div class="admin-content p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                <div>
                    <h2 class="mb-1">Website Visitors</h2>
                    <p class="text-muted mb-0 small">How many people visit the public site each day, and how they get there.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <form method="GET">
                        <select name="range" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Period">
                            <?php foreach ($ranges as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $range === (string) $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <a href="visitors.php?range=<?php echo $range; ?>&export=1" class="btn btn-sm btn-success d-flex align-items-center">
                        <i class="bi bi-download me-1"></i>Daily CSV
                    </a>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <?php foreach ($cards as [$icon, $colour, $value, $label, $note]): ?>
                    <div class="col-sm-6 col-xl-4">
                        <div class="card stat-card border-0 shadow-sm h-100">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-<?php echo $colour; ?> bg-opacity-10 text-<?php echo $colour; ?> rounded-3 p-3 me-3">
                                    <i class="bi <?php echo $icon; ?> fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo $value; ?></h3>
                                    <span class="text-muted"><?php echo $label; ?></span>
                                    <small class="d-block text-muted"><?php echo e($note); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h3 class="tx-head">Visitors per day</h3>
                    <div class="vs-chart" role="img" aria-label="Visitors per day for the <?php echo strtolower($ranges[$range]); ?>">
                        <?php foreach ($chartDays as $day => $count): ?>
                            <div class="vs-bar" title="<?php echo date('D, M j', strtotime($day)) . ': ' . $count['visitors'] . ' visitors, ' . $count['views'] . ' page views'; ?>">
                                <span style="height: <?php echo round($count['visitors'] / $chartMax * 100, 1); ?>%;"></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="vs-axis">
                        <span><?php echo date('M j', strtotime(array_key_first($chartDays))); ?></span>
                        <span>Busiest day: <?php echo number_format($chartMax); ?> visitors</span>
                        <span><?php echo date('M j', strtotime(array_key_last($chartDays))); ?></span>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h3 class="tx-head">Day by day</h3>
                            <div class="table-responsive" style="max-height: 420px;">
                                <table class="table table-sm align-middle mb-0 table-no-stack">
                                    <thead class="table-light">
                                        <tr><th>Date</th><th class="text-end">Visitors</th><th class="text-end">New</th><th class="text-end">Views</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($daily as $day => $count): ?>
                                            <tr>
                                                <td class="text-nowrap"><?php echo $day === date('Y-m-d') ? 'Today' : date('D, M j', strtotime($day)); ?></td>
                                                <td class="text-end fw-bold"><?php echo number_format($count['visitors']); ?></td>
                                                <td class="text-end"><?php echo number_format($count['new']); ?></td>
                                                <td class="text-end"><?php echo number_format($count['views']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h3 class="tx-head">Most visited pages</h3>
                            <?php if (empty($pages)): ?>
                                <p class="text-muted small mb-0">No visits recorded for this period yet.</p>
                            <?php else: ?>
                                <ul class="tx-list">
                                    <?php foreach ($pages as $item): ?>
                                        <li>
                                            <div>
                                                <strong><?php echo e($item['label']); ?></strong>
                                                <small><?php echo number_format($item['visitors']) . ($item['visitors'] == 1 ? ' visitor' : ' visitors'); ?></small>
                                            </div>
                                            <div class="text-end">
                                                <strong class="text-nowrap"><?php echo number_format($item['views']); ?></strong>
                                                <small><?php echo $item['views'] == 1 ? 'page view' : 'page views'; ?></small>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <?php foreach ($panels as [$title, $icon, $items]): ?>
                    <?php $panelMax = max(1, (int) ($items[0]['visitors'] ?? 1)); ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h3 class="tx-head"><i class="bi <?php echo $icon; ?> me-2"></i><?php echo $title; ?></h3>
                                <?php if (empty($items)): ?>
                                    <p class="text-muted small mb-0">Nothing yet.</p>
                                <?php else: ?>
                                    <ul class="list-unstyled mb-0 vs-panel">
                                        <?php foreach ($items as $item): ?>
                                            <li class="mb-2">
                                                <div class="vs-row">
                                                    <span><?php echo e($item['label']); ?></span>
                                                    <strong><?php echo number_format($item['visitors']); ?></strong>
                                                </div>
                                                <div class="vs-meter"><span style="width: <?php echo round($item['visitors'] / $panelMax * 100); ?>%;"></span></div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="text-muted small mt-4 mb-0">
                Counts cover the public site only. Admins and automated tools are not counted, and visits are kept for
                <?php echo VISIT_RETENTION_DAYS; ?> days. A visitor is one browser, so one person on two devices counts twice.
            </p>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
</body>
</html>
