<?php
/**
 * The transactions page, shared by the admin and organizer portals. Award votes and
 * ticket sales are kept on separate tabs, each with its own totals and columns.
 *
 * The including page checks who is signed in, then sets:
 *   $txPortal      'admin' or 'organizer'
 *   $txOrganizerId The organizer's id, or null for an admin
 */

$isAdminView = $txOrganizerId === null;
$page_title = 'Transactions';
$pdo = getDBConnection();

$tab = ($_GET['tab'] ?? '') === 'tickets' ? 'tickets' : 'votes';
$isTickets = $tab === 'tickets';

// Ticket references always start TKT-, even after a ticket type has been deleted
$ticketSql = "(t.reference LIKE 'TKT-%' OR t.ticket_category_id IS NOT NULL)";
$cardSql = "(t.payment_method = 'card' OR LOWER(COALESCE(t.provider, '')) IN ('card', 'visa', 'mastercard'))";
$ussdSql = "t.metadata LIKE '%\"msisdn\"%'";
$joins = "
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN events e ON t.event_id = e.id
    LEFT JOIN nominees n ON t.nominee_id = n.id
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN ticket_categories tc ON t.ticket_category_id = tc.id
";

$filters = [
    'event'  => (int) ($_GET['event'] ?? 0),
    'status' => in_array($_GET['status'] ?? '', ['success', 'pending', 'failed', 'refunded'], true) ? $_GET['status'] : '',
    'method' => in_array($_GET['method'] ?? '', ['momo', 'card', 'ussd'], true) ? $_GET['method'] : '',
    'from'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? $_GET['from'] : '',
    'to'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? $_GET['to'] : '',
    'search' => trim((string) ($_GET['search'] ?? '')),
];

// The link for this view, with some values changed
$txLink = function (array $changes = []) use ($tab, $filters) {
    return 'transactions.php?' . http_build_query(array_filter(array_merge(['tab' => $tab], $filters, $changes)));
};

if ($isAdminView && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_delete') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Your session expired. Please try again.');
    } else {
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['transaction_ids'] ?? []))));
        if ($ids) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $deleted = $pdo->prepare("DELETE FROM transactions WHERE id IN ($marks)")->execute($ids);
            setFlash($deleted ? 'success' : 'danger', $deleted ? count($ids) . ' transactions deleted.' : 'Failed to delete transactions.');
        } else {
            setFlash('warning', 'No transactions selected.');
        }
    }
    redirect('admin/' . $txLink(['page' => (int) ($_GET['page'] ?? 0)]));
}

// Who may be seen, and which kind of payment this tab holds
$scope = [$isTickets ? $ticketSql : "NOT $ticketSql"];
$scopeParams = [];
if (!$isAdminView) {
    $scope[] = 'e.organizer_id = ?';
    $scopeParams[] = $txOrganizerId;
}

// Everything but status, so the totals can still count pending and failed payments
$where = $scope;
$params = $scopeParams;
if ($filters['event']) {
    $where[] = 't.event_id = ?';
    $params[] = $filters['event'];
}
if ($filters['method'] === 'card') {
    $where[] = $cardSql;
} elseif ($filters['method'] === 'momo') {
    $where[] = "NOT $cardSql";
} elseif ($filters['method'] === 'ussd') {
    $where[] = $ussdSql;
}
if ($filters['from']) {
    $where[] = 't.created_at >= ?';
    $params[] = $filters['from'] . ' 00:00:00';
}
if ($filters['to']) {
    $where[] = 't.created_at <= ?';
    $params[] = $filters['to'] . ' 23:59:59';
}
if ($filters['search'] !== '') {
    $like = '%' . $filters['search'] . '%';
    $fields = ['t.reference', 'u.fullname', 'e.title', $isTickets ? 'tc.name' : 'n.name'];
    if ($isAdminView) {
        array_push($fields, 't.external_id', 'u.email');
    }
    // Organizers cannot look up a voter by phone number
    if ($isAdminView || $isTickets) {
        $fields[] = 't.phone';
    }
    $parts = array_map(function ($field) { return "$field LIKE ?"; }, $fields);
    $params = array_merge($params, array_fill(0, count($fields), $like));
    if ($isTickets) {
        $parts[] = 'EXISTS (SELECT 1 FROM tickets k WHERE k.transaction_id = t.id AND k.ticket_code LIKE ?)';
        $params[] = $like;
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}
$totalsWhere = implode(' AND ', $where);
$totalsParams = $params;

if ($filters['status']) {
    $where[] = 't.status = ?';
    $params[] = $filters['status'];
}
$listWhere = implode(' AND ', $where);

$listSql = "
    SELECT t.*, u.fullname AS user_name, u.email AS user_email,
           e.title AS event_title, n.name AS nominee_name, n.shortcode,
           c.name AS category_name, tc.name AS ticket_name
    FROM transactions t
    $joins
    WHERE $listWhere
    ORDER BY t.created_at DESC, t.id DESC
";

// How a payment was made, for both the table and the export
$txChannel = function (array $tx) {
    $provider = strtolower((string) $tx['provider']);
    $meta = json_decode((string) $tx['metadata'], true) ?: [];
    $networks = ['mtn' => 'MTN', 'telecel' => 'Telecel', 'vodafone' => 'Telecel', 'airteltigo' => 'AirtelTigo', 'at' => 'AirtelTigo'];
    $isCard = $tx['payment_method'] === 'card' || in_array($provider, ['card', 'visa', 'mastercard'], true);

    return [
        'method'  => $isCard ? 'Card' : 'Mobile Money',
        'network' => $isCard ? ($provider === 'card' ? '' : ucfirst($provider)) : ($networks[$provider] ?? ''),
        'source'  => isset($meta['msisdn']) ? 'USSD' : 'Website',
        'meta'    => $meta,
    ];
};

// Voters' numbers stay private from organizers; ticket buyers' are already on their ticket lists
$txPhone = function ($phone) use ($isAdminView, $isTickets) {
    $phone = (string) $phone;
    if ($isAdminView || $isTickets) {
        return $phone;
    }
    return strlen($phone) < 8 ? '' : substr($phone, 0, 6) . '****' . substr($phone, -3);
};

$txItem = function (array $tx, array $meta) use ($isTickets) {
    return $isTickets
        ? ($tx['ticket_name'] ?: ($meta['category_name'] ?? 'Removed ticket type'))
        : ($tx['nominee_name'] ?: ($meta['nominee_name'] ?? 'Removed nominee'));
};

if (isset($_GET['export'])) {
    $stmt = $pdo->prepare($listSql);
    $stmt->execute($params);

    // A spreadsheet must not run a name as a formula
    $safe = function ($value) {
        $value = plainText($value);
        return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
    };

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . ($isTickets ? 'ticket' : 'voting') . '_transactions_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Reference', $isTickets ? 'Buyer' : 'Voter', 'Phone', 'Event', $isTickets ? 'Ticket Type' : 'Nominee', $isTickets ? 'Tickets' : 'Votes', 'Amount (GHS)', 'Method', 'Network', 'Source', 'Status', 'Date']);
    while ($tx = $stmt->fetch()) {
        $channel = $txChannel($tx);
        fputcsv($out, [
            $tx['reference'], $safe($tx['user_name'] ?: 'Guest'), $txPhone($tx['phone']), $safe($tx['event_title']),
            $safe($txItem($tx, $channel['meta'])), $tx['quantity'], $tx['amount'], $channel['method'], $channel['network'],
            $channel['source'], ucfirst((string) $tx['status']), $tx['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// Tab counts follow the portal, not the filters
$countStmt = $pdo->prepare("
    SELECT COALESCE(SUM($ticketSql), 0) AS tickets, COALESCE(SUM(NOT $ticketSql), 0) AS votes
    FROM transactions t
    LEFT JOIN events e ON t.event_id = e.id
    " . ($isAdminView ? '' : 'WHERE e.organizer_id = ?'));
$countStmt->execute($isAdminView ? [] : [$txOrganizerId]);
$tabCounts = $countStmt->fetch();

$totalsStmt = $pdo->prepare("
    SELECT COALESCE(SUM(CASE WHEN t.status = 'success' THEN t.amount END), 0) AS revenue,
           COALESCE(SUM(CASE WHEN t.status = 'success' THEN t.quantity END), 0) AS units,
           COALESCE(SUM(t.status = 'success'), 0) AS paid,
           COALESCE(SUM(t.status = 'pending'), 0) AS pending,
           COALESCE(SUM(t.status = 'failed'), 0) AS failed,
           COALESCE(SUM(CASE WHEN t.status = 'pending' THEN t.amount END), 0) AS pending_amount
    FROM transactions t
    $joins
    WHERE $totalsWhere
");
$totalsStmt->execute($totalsParams);
$totals = $totalsStmt->fetch();
$settled = (int) $totals['paid'] + (int) $totals['failed'];
$successRate = $settled ? round($totals['paid'] / $settled * 100) : 0;
$averagePaid = $totals['paid'] ? $totals['revenue'] / $totals['paid'] : 0;

$topName = $isTickets ? "COALESCE(tc.name, 'Removed ticket type')" : "COALESCE(n.name, 'Removed nominee')";
$topStmt = $pdo->prepare("
    SELECT $topName AS item_label, e.title AS event_title, SUM(t.quantity) AS units, SUM(t.amount) AS amount
    FROM transactions t
    $joins
    WHERE $totalsWhere AND t.status = 'success'
    GROUP BY item_label, e.id
    ORDER BY amount DESC
    LIMIT 5
");
$topStmt->execute($totalsParams);
$topItems = $topStmt->fetchAll();

$channelStmt = $pdo->prepare("
    SELECT CASE WHEN $cardSql THEN 'Card' WHEN $ussdSql THEN 'USSD' ELSE 'Mobile Money' END AS channel_label,
           COUNT(*) AS payments, SUM(t.amount) AS amount
    FROM transactions t
    $joins
    WHERE $totalsWhere AND t.status = 'success'
    GROUP BY channel_label
    ORDER BY amount DESC
");
$channelStmt->execute($totalsParams);
$channels = $channelStmt->fetchAll();

$eventsStmt = $pdo->prepare("
    SELECT DISTINCT e.id, e.title
    FROM transactions t
    JOIN events e ON t.event_id = e.id
    WHERE " . implode(' AND ', $scope) . "
    ORDER BY e.title
");
$eventsStmt->execute($scopeParams);
$eventOptions = $eventsStmt->fetchAll();

// This page of results
$limit = 20;
$listCountStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions t $joins WHERE $listWhere");
$listCountStmt->execute($params);
$matching = (int) $listCountStmt->fetchColumn();
$totalPages = max(1, (int) ceil($matching / $limit));
$page = min($totalPages, max(1, (int) ($_GET['page'] ?? 1)));
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("$listSql LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// What each payment produced: votes counted, or tickets issued
$issued = [];
$ids = array_map('intval', array_column($transactions, 'id'));
if ($ids) {
    $idList = implode(',', $ids);
    $sql = $isTickets
        ? "SELECT transaction_id, ticket_code AS item FROM tickets WHERE transaction_id IN ($idList)"
        : "SELECT transaction_id, vote_count AS item FROM votes WHERE transaction_id IN ($idList)";
    foreach ($pdo->query($sql) as $row) {
        $issued[$row['transaction_id']][] = $row['item'];
    }
}

$unit = $isTickets ? 'ticket' : 'vote';
$personLabel = $isTickets ? 'Buyer' : 'Voter';
$itemLabel = $isTickets ? 'Ticket' : 'Nominee';
$unitLabel = $isTickets ? 'Tickets' : 'Votes';
$statusColours = ['success' => 'success', 'pending' => 'warning', 'failed' => 'danger'];
$columnCount = $isAdminView ? 8 : 7;
$summaryCards = [
    ['bi-cash-stack', 'success', 'GHS ' . number_format($totals['revenue'], 2), '', $isTickets ? 'Ticket revenue' : 'Voting revenue'],
    [$isTickets ? 'bi-ticket-perforated' : 'bi-check2-square', 'primary', number_format($totals['units']), '', $isTickets ? 'Tickets sold' : 'Paid votes'],
    ['bi-receipt', 'info', number_format($totals['paid']), 'GHS ' . number_format($averagePaid, 2) . ' average', 'Successful payments'],
    ['bi-percent', 'secondary', $settled ? $successRate . '%' : '-', '', 'Payment success rate'],
    ['bi-clock-history', 'warning', number_format($totals['pending']), 'GHS ' . number_format($totals['pending_amount'], 2), 'Pending'],
    ['bi-x-circle', 'danger', number_format($totals['failed']), '', 'Failed'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title . ' - ' . SITE_NAME); ?></title>
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
    <?php include __DIR__ . "/../$txPortal/includes/sidebar.php"; ?>

    <main class="admin-main">
        <?php include __DIR__ . "/../$txPortal/includes/topbar.php"; ?>

        <div class="admin-content p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                <div>
                    <h2 class="mb-1">Transactions</h2>
                    <p class="text-muted mb-0 small">
                        <?php echo $isAdminView ? 'Every payment on the platform, with votes and tickets kept apart.' : 'Payments for your events, with votes and tickets kept apart.'; ?>
                    </p>
                </div>
                <div class="d-flex flex-row flex-wrap gap-2">
                    <a href="<?php echo e($txLink(['export' => 1])); ?>" class="btn btn-sm btn-success d-flex align-items-center">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                    <?php if ($isAdminView): ?>
                        <button type="button" class="btn btn-sm btn-danger d-flex align-items-center" id="bulkDeleteBtn" disabled>
                            <i class="bi bi-trash me-1"></i>Delete
                        </button>
                        <a href="sync_pending.php" class="btn btn-sm btn-warning d-flex align-items-center">
                            <i class="bi bi-arrow-clockwise me-1"></i>Sync
                        </a>
                        <a href="pending-notifications.php" class="btn btn-sm btn-primary d-flex align-items-center">
                            <i class="bi bi-bell me-1"></i>Notify
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?php echo e($flash['type']); ?> alert-dismissible fade show mb-4">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <nav class="tx-tabs mb-4" aria-label="Transaction type">
                <a href="transactions.php?tab=votes" class="tx-tab <?php echo $isTickets ? '' : 'is-on'; ?>" <?php echo $isTickets ? '' : 'aria-current="page"'; ?>>
                    <i class="bi bi-trophy"></i>Awards Voting <span><?php echo number_format($tabCounts['votes']); ?></span>
                </a>
                <a href="transactions.php?tab=tickets" class="tx-tab <?php echo $isTickets ? 'is-on' : ''; ?>" <?php echo $isTickets ? 'aria-current="page"' : ''; ?>>
                    <i class="bi bi-ticket-perforated"></i>Tickets <span><?php echo number_format($tabCounts['tickets']); ?></span>
                </a>
            </nav>

            <div class="row g-3 mb-4">
                <?php foreach ($summaryCards as [$icon, $colour, $value, $note, $label]): ?>
                    <div class="col-sm-6 col-xl-4">
                        <div class="card stat-card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon bg-<?php echo $colour; ?> bg-opacity-10 text-<?php echo $colour; ?> rounded-3 p-3 me-3">
                                        <i class="bi <?php echo $icon; ?> fs-4"></i>
                                    </div>
                                    <div>
                                        <h3 class="mb-0"><?php echo $value; ?></h3>
                                        <span class="text-muted"><?php echo $label; ?></span>
                                        <?php if ($note): ?>
                                            <small class="d-block text-muted"><?php echo $note; ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h3 class="tx-head"><?php echo $isTickets ? 'Top ticket types' : 'Top earning nominees'; ?></h3>
                            <?php if (empty($topItems)): ?>
                                <p class="text-muted small mb-0">No paid <?php echo $unit; ?>s for this view yet.</p>
                            <?php else: ?>
                                <ul class="tx-list">
                                    <?php foreach ($topItems as $item): ?>
                                        <li>
                                            <div>
                                                <strong><?php echo e($item['item_label']); ?></strong>
                                                <small><?php echo e($item['event_title'] ?: 'Removed event'); ?></small>
                                            </div>
                                            <div class="text-end">
                                                <strong class="text-nowrap">GHS <?php echo number_format($item['amount'], 2); ?></strong>
                                                <small><?php echo number_format($item['units']) . ' ' . $unit . ($item['units'] == 1 ? '' : 's'); ?></small>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h3 class="tx-head">How people paid</h3>
                            <?php if (empty($channels)): ?>
                                <p class="text-muted small mb-0">No successful payments for this view yet.</p>
                            <?php else: ?>
                                <ul class="tx-list">
                                    <?php foreach ($channels as $channel): ?>
                                        <li>
                                            <div>
                                                <strong><?php echo e($channel['channel_label']); ?></strong>
                                                <small><?php echo number_format($channel['payments']) . ' payment' . ($channel['payments'] == 1 ? '' : 's'); ?></small>
                                            </div>
                                            <div class="text-end">
                                                <strong class="text-nowrap">GHS <?php echo number_format($channel['amount'], 2); ?></strong>
                                                <small><?php echo $totals['revenue'] > 0 ? round($channel['amount'] / $totals['revenue'] * 100) : 0; ?>% of revenue</small>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                        <div class="col-md-6 col-xl-4">
                            <label class="form-label small" for="txSearch">Search</label>
                            <input type="search" id="txSearch" name="search" class="form-control" value="<?php echo e($filters['search']); ?>"
                                   placeholder="<?php echo $isTickets ? 'Reference, buyer, phone or ticket code' : ($isAdminView ? 'Reference, voter, phone or nominee' : 'Reference, voter or nominee'); ?>">
                        </div>
                        <div class="col-md-6 col-xl-4">
                            <label class="form-label small" for="txEvent">Event</label>
                            <select id="txEvent" name="event" class="form-select">
                                <option value="">All events</option>
                                <?php foreach ($eventOptions as $option): ?>
                                    <option value="<?php echo (int) $option['id']; ?>" <?php echo $filters['event'] === (int) $option['id'] ? 'selected' : ''; ?>><?php echo e($option['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-xl-2">
                            <label class="form-label small" for="txStatus">Status</label>
                            <select id="txStatus" name="status" class="form-select">
                                <option value="">All</option>
                                <?php foreach (['success' => 'Success', 'pending' => 'Pending', 'failed' => 'Failed', 'refunded' => 'Refunded'] as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo $filters['status'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-xl-2">
                            <label class="form-label small" for="txMethod">Paid by</label>
                            <select id="txMethod" name="method" class="form-select">
                                <option value="">All</option>
                                <?php foreach (['momo' => 'Mobile Money', 'card' => 'Card', 'ussd' => 'USSD'] as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo $filters['method'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-xl-3">
                            <label class="form-label small" for="txFrom">From</label>
                            <input type="date" id="txFrom" name="from" class="form-control" value="<?php echo e($filters['from']); ?>">
                        </div>
                        <div class="col-6 col-xl-3">
                            <label class="form-label small" for="txTo">To</label>
                            <input type="date" id="txTo" name="to" class="form-control" value="<?php echo e($filters['to']); ?>">
                        </div>
                        <div class="col-6 col-xl-3">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                        <div class="col-6 col-xl-3">
                            <a href="transactions.php?tab=<?php echo $tab; ?>" class="btn btn-outline-secondary w-100">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <form id="bulkDeleteForm" method="POST" action="<?php echo e($txLink(['page' => $page])); ?>">
                <?php if ($isAdminView): ?>
                    <input type="hidden" name="action" value="bulk_delete">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <?php endif; ?>
                <div class="card border-0 shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover table-stacked align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <?php if ($isAdminView): ?>
                                        <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAllCheckbox" aria-label="Select all"></th>
                                    <?php endif; ?>
                                    <th>Reference</th>
                                    <th><?php echo $personLabel; ?></th>
                                    <th><?php echo $itemLabel; ?></th>
                                    <th><?php echo $unitLabel; ?></th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th><span class="visually-hidden">Details</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="<?php echo $columnCount; ?>" class="text-center py-4 text-muted">No <?php echo $unit; ?> transactions found.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($transactions as $tx): ?>
                                    <?php
                                    $channel = $txChannel($tx);
                                    $produced = $issued[$tx['id']] ?? [];
                                    $quantity = max(1, (int) $tx['quantity']);
                                    $missing = $tx['status'] === 'success' && empty($produced);
                                    $when = strtotime($tx['created_at']);
                                    $phone = $txPhone($tx['phone']);
                                    $itemName = $txItem($tx, $channel['meta']);
                                    $eventName = $tx['event_title'] ?: 'Removed event';
                                    ?>
                                    <tr>
                                        <?php if ($isAdminView): ?>
                                            <td data-label="Select"><input type="checkbox" class="form-check-input tx-checkbox" name="transaction_ids[]" value="<?php echo (int) $tx['id']; ?>" aria-label="Select <?php echo e($tx['reference']); ?>"></td>
                                        <?php endif; ?>
                                        <td data-label="Reference">
                                            <div>
                                                <code class="text-primary fw-bold"><?php echo e($tx['reference']); ?></code>
                                                <small class="d-block text-muted"><?php echo date('M d, Y H:i', $when); ?></small>
                                            </div>
                                        </td>
                                        <td data-label="<?php echo $personLabel; ?>">
                                            <div>
                                                <div class="fw-bold"><?php echo e($tx['user_name'] ?: 'Guest'); ?></div>
                                                <?php if ($phone !== ''): ?>
                                                    <small class="d-block text-muted"><?php echo e($phone); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td data-label="<?php echo $itemLabel; ?>">
                                            <div>
                                                <div class="fw-bold"><?php echo e($itemName); ?></div>
                                                <small class="d-block text-muted"><?php echo e($eventName); ?></small>
                                            </div>
                                        </td>
                                        <td data-label="<?php echo $unitLabel; ?>"><?php echo number_format($tx['quantity']); ?></td>
                                        <td data-label="Amount"><span class="fw-bold text-nowrap">GHS <?php echo number_format($tx['amount'], 2); ?></span></td>
                                        <td data-label="Status">
                                            <div>
                                                <span class="badge bg-<?php echo $statusColours[$tx['status']] ?? 'secondary'; ?>"><?php echo e(ucfirst((string) $tx['status'])); ?></span>
                                                <?php if ($missing): ?>
                                                    <i class="bi bi-exclamation-triangle-fill text-warning ms-1" title="Paid, but no <?php echo $unit; ?> is linked to this payment"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td data-label="Actions">
                                            <button type="button" class="btn btn-sm btn-outline-secondary tx-toggle" aria-expanded="false" aria-controls="tx-<?php echo (int) $tx['id']; ?>">
                                                Details <i class="bi bi-chevron-down"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="tx-detail" id="tx-<?php echo (int) $tx['id']; ?>" hidden>
                                        <td colspan="<?php echo $columnCount; ?>">
                                            <dl class="tx-facts">
                                                <?php if ($isTickets): ?>
                                                    <div><dt>Ticket type</dt><dd><?php echo e($itemName); ?></dd></div>
                                                    <div><dt>Price per ticket</dt><dd>GHS <?php echo number_format($tx['amount'] / $quantity, 2); ?></dd></div>
                                                <?php else: ?>
                                                    <div><dt>Award category</dt><dd><?php echo e($tx['category_name'] ?: 'Removed category'); ?></dd></div>
                                                    <div><dt>Nominee code</dt><dd><?php echo e($tx['shortcode'] ?: '-'); ?></dd></div>
                                                    <div><dt>Price per vote</dt><dd>GHS <?php echo number_format($tx['amount'] / $quantity, 2); ?></dd></div>
                                                <?php endif; ?>
                                                <div><dt>Event</dt><dd><?php echo e($eventName); ?></dd></div>
                                                <div><dt>Payment method</dt><dd><?php echo e(trim($channel['method'] . ' ' . $channel['network'])); ?></dd></div>
                                                <div><dt>Paid through</dt><dd><?php echo e($channel['source']); ?></dd></div>
                                                <div><dt>Started</dt><dd><?php echo date('M d, Y H:i:s', $when); ?></dd></div>
                                                <div><dt>Last updated</dt><dd><?php echo date('M d, Y H:i:s', strtotime($tx['updated_at'])); ?></dd></div>
                                                <?php if ($isAdminView): ?>
                                                    <div><dt>Gateway reference</dt><dd><?php echo e($tx['external_id'] ?: '-'); ?></dd></div>
                                                    <div><dt>Account email</dt><dd><?php echo e($tx['user_email'] ?: '-'); ?></dd></div>
                                                <?php endif; ?>
                                                <?php if ($isTickets): ?>
                                                    <div class="tx-wide">
                                                        <dt>Ticket <?php echo count($produced) === 1 ? 'code' : 'codes'; ?></dt>
                                                        <dd>
                                                            <?php foreach ($produced as $code): ?>
                                                                <code class="me-2"><?php echo e($code); ?></code>
                                                            <?php endforeach; ?>
                                                            <?php if (!$produced): ?>
                                                                <?php echo $missing ? '<span class="text-danger">Paid, but no ticket is linked</span>' : 'Issued once payment succeeds'; ?>
                                                            <?php endif; ?>
                                                        </dd>
                                                    </div>
                                                <?php else: ?>
                                                    <div>
                                                        <dt>Votes counted</dt>
                                                        <dd>
                                                            <?php if ($produced): ?>
                                                                <?php echo number_format(array_sum($produced)); ?>
                                                            <?php else: ?>
                                                                <?php echo $missing ? '<span class="text-danger">Paid, but no vote is linked</span>' : 'Counted once payment succeeds'; ?>
                                                            <?php endif; ?>
                                                        </dd>
                                                    </div>
                                                <?php endif; ?>
                                            </dl>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer bg-white border-0 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <small class="text-muted"><?php echo number_format($matching); ?> matching <?php echo $matching === 1 ? 'payment' : 'payments'; ?></small>
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Pages">
                                <ul class="pagination mb-0 flex-wrap">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo e($txLink(['page' => $page - 1])); ?>">Previous</a>
                                    </li>
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="<?php echo e($txLink(['page' => $i])); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo e($txLink(['page' => $page + 1])); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
    <script>
    document.querySelectorAll('.tx-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            var panel = document.getElementById(button.getAttribute('aria-controls'));
            panel.hidden = !panel.hidden;
            button.setAttribute('aria-expanded', String(!panel.hidden));
        });
    });

    <?php if ($isAdminView): ?>
    (function () {
        var deleteButton = document.getElementById('bulkDeleteBtn');
        var boxes = document.querySelectorAll('.tx-checkbox');
        var refresh = function () {
            deleteButton.disabled = !document.querySelector('.tx-checkbox:checked');
        };

        boxes.forEach(function (box) { box.addEventListener('change', refresh); });
        document.getElementById('selectAllCheckbox').addEventListener('change', function () {
            var checked = this.checked;
            boxes.forEach(function (box) { box.checked = checked; });
            refresh();
        });
        deleteButton.addEventListener('click', function () {
            if (confirm('Delete the selected transactions? This cannot be undone.')) {
                document.getElementById('bulkDeleteForm').submit();
            }
        });
    })();
    <?php endif; ?>
    </script>
</body>
</html>
