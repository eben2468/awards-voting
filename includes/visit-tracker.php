<?php
/**
 * Visitor tracking for the public site.
 *
 * Each page view is stored with the page, where the visitor came from, and what
 * the browser reports about the device. A first-party cookie (vh_vid) tells new
 * visitors from returning ones. Nothing is sent to any outside service here;
 * locations are looked up later, only when an admin views the dashboard.
 *
 * Skipped: bots and preview tools, link prefetches, admins, and admins viewing
 * an organizer's account, so the numbers reflect real visitors.
 */

require_once __DIR__ . '/device-info.php';

define('VISIT_RETENTION_DAYS', 180);
define('VISIT_SESSION_IDLE', 1800); // A new session starts after 30 idle minutes

// Query parameters worth keeping. Others, such as results access codes, are dropped.
define('VISIT_SAFE_PARAMS', ['slug', 'event', 'category', 'page', 'type', 'status', 'search', 'q']);

function visitTableSql() {
    return "CREATE TABLE IF NOT EXISTS site_visits (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        visitor_id CHAR(32) NOT NULL,
        session_id CHAR(32) NOT NULL,
        user_id INT NULL,
        is_new TINYINT(1) NOT NULL DEFAULT 0,
        path VARCHAR(255) NOT NULL,
        referrer VARCHAR(255) NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(500) NULL,
        device_type VARCHAR(20) NULL,
        device_name VARCHAR(80) NULL,
        os VARCHAR(40) NULL,
        browser VARCHAR(40) NULL,
        language VARCHAR(20) NULL,
        screen VARCHAR(20) NULL,
        timezone VARCHAR(50) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_created (created_at),
        KEY idx_visitor (visitor_id),
        KEY idx_session (session_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}

// Run a statement, creating the table the first time it is needed
function visitQuery($sql, array $params = []) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S02') {
            throw $e;
        }
        $pdo->exec(visitTableSql());
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}

function visitOperatingSystem($ua) {
    $systems = [
        '/iPhone.*?OS (\d+)[_.](\d+)/' => 'iOS %s.%s',
        '/iPad.*?OS (\d+)[_.](\d+)/'   => 'iPadOS %s.%s',
        '/Android ([\d.]+)/'           => 'Android %s',
        '/Windows NT/'                 => 'Windows',
        '/CrOS/'                       => 'ChromeOS',
        '/Mac OS X/'                   => 'macOS',
        '/Linux/'                      => 'Linux',
    ];
    foreach ($systems as $pattern => $label) {
        if (preg_match($pattern, $ua, $m)) {
            array_shift($m);
            return vsprintf($label, $m);
        }
    }
    return 'Unknown';
}

// A signature so the browser can add screen size to its own visit, and no other
function visitSignature($visitId) {
    return hash_hmac('sha256', 'visit:' . $visitId, DB_NAME . '|' . DB_PASS . '|' . DB_USER);
}

function visitCleanPath() {
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) parse_url($uri, PHP_URL_PATH);
    $query = [];
    parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
    $kept = array_intersect_key($query, array_flip(VISIT_SAFE_PARAMS));
    $kept = array_filter($kept, 'is_scalar');

    $clean = ($path === '' ? '/' : $path) . ($kept ? '?' . http_build_query($kept) : '');
    return substr($clean, 0, 255);
}

// Another site's address, without its query string, or null for internal links
function visitCleanReferrer() {
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $host = strtolower((string) parse_url($ref, PHP_URL_HOST));
    if ($host === '' || $host === strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''))) {
        return null;
    }
    $host = preg_replace('/^www\./', '', $host);
    $path = rtrim((string) parse_url($ref, PHP_URL_PATH), '/');
    return substr($host . $path, 0, 255);
}

/**
 * Record this page view. Called from the public page header.
 *
 * @return int|null The new visit's id, so the page can add screen details
 */
function trackVisit() {
    if (PHP_SAPI === 'cli' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return null;
    }
    if (isImpersonating() || isAdmin()) {
        return null;
    }

    $purpose = strtolower(($_SERVER['HTTP_SEC_PURPOSE'] ?? '') . ' ' . ($_SERVER['HTTP_PURPOSE'] ?? '') . ' ' . ($_SERVER['HTTP_X_MOZ'] ?? ''));
    if (strpos($purpose, 'prefetch') !== false || strpos($purpose, 'prerender') !== false) {
        return null;
    }

    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    if ($ua === '' || preg_match('/bot|crawl|spider|slurp|curl|wget|python|okhttp|java\/|go-http|facebookexternalhit|whatsapp|telegram|preview|headless|lighthouse|monitor|uptime|pingdom|scan|axios|node-fetch|httpclient/i', $ua)) {
        return null;
    }

    $device = describeDevice($ua);
    if ($device['type'] === 'Automated') {
        return null;
    }

    // Who: the same browser keeps the same id for over a year
    $visitorId = (string) ($_COOKIE['vh_vid'] ?? '');
    $isNew = 0;
    if (!preg_match('/^[a-f0-9]{32}$/', $visitorId)) {
        $visitorId = bin2hex(random_bytes(16));
        $isNew = 1;
    }
    if (!headers_sent()) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        setcookie('vh_vid', $visitorId, [
            'expires'  => time() + 400 * 86400,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    // A session: views with less than 30 idle minutes between them
    $session = $_SESSION['vh_visit'] ?? null;
    if (!is_array($session) || empty($session['id']) || time() - ($session['last'] ?? 0) > VISIT_SESSION_IDLE) {
        $session = ['id' => bin2hex(random_bytes(16))];
    }
    $session['last'] = time();
    $_SESSION['vh_visit'] = $session;

    $browser = $device['browser'];
    if (strpos($browser, ', ') !== false) {
        $browser = substr($browser, strrpos($browser, ', ') + 2);
    }
    $language = preg_match('/^\s*([a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})?)/', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), $m) ? $m[1] : null;

    try {
        visitQuery(
            "INSERT INTO site_visits (visitor_id, session_id, user_id, is_new, path, referrer, ip_address, user_agent,
                                      device_type, device_name, os, browser, language)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $visitorId, $session['id'], getCurrentUserId(), $isNew, visitCleanPath(), visitCleanReferrer(),
                substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null, $ua,
                $device['type'], substr($device['name'], 0, 80), visitOperatingSystem($ua),
                substr($browser ?: 'Unknown', 0, 40), $language,
            ]
        );
        $visitId = (int) getDBConnection()->lastInsertId();
        visitPruneOldRows();
        return $visitId;
    } catch (Exception $e) {
        // Tracking must never break a page
        error_log('Visit tracking error: ' . $e->getMessage());
        return null;
    }
}

// Keep the table small: drop visits older than the retention period, about once a day
function visitPruneOldRows() {
    $last = (int) getSetting('visits_pruned_at', '0');
    if (time() - $last < 86400) {
        return;
    }
    saveSetting('visits_pruned_at', (string) time());
    visitQuery("DELETE FROM site_visits WHERE created_at < DATE_SUB(NOW(), INTERVAL " . (int) VISIT_RETENTION_DAYS . " DAY) LIMIT 20000");
}

/**
 * The small script that adds screen size and time zone to this visit. Printed
 * near the end of public pages; empty when the view was not tracked.
 */
function renderVisitBeacon() {
    $visitId = $GLOBALS['vh_visit_id'] ?? null;
    if (!$visitId) {
        return '';
    }
    $payload = json_encode([
        'url' => SITE_URL . '/api/visit.php',
        'id'  => $visitId,
        'sig' => visitSignature($visitId),
    ]);

    return "<script>(function(d){try{var f=new FormData();f.append('id',d.id);f.append('sig',d.sig);"
        . "f.append('screen',screen.width+'x'+screen.height);"
        . "f.append('tz',(Intl.DateTimeFormat().resolvedOptions().timeZone||''));"
        . "if(navigator.sendBeacon){navigator.sendBeacon(d.url,f);}else{fetch(d.url,{method:'POST',body:f,keepalive:true});}"
        . "}catch(e){}})(" . $payload . ");</script>";
}
