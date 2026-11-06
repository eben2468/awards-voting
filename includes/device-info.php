<?php
/**
 * Turns the raw details in the audit log into something readable: which device
 * was used and roughly where from.
 *
 * Browsers limit both on purpose. iPhones never reveal their model, and an IP
 * address only places someone near a town or their network's location.
 */

define('IP_LOCATION_CACHE_SIZE', 400);
define('IP_LOCATION_URL', env('IP_LOCATION_URL', 'https://api.db-ip.com/v2/free/'));

/**
 * @return array type (Phone, Tablet, Computer, Automated), name, browser, icon
 */
function describeDevice($userAgent) {
    $ua = (string) $userAgent;
    if ($ua === '') {
        return ['type' => 'Unknown', 'name' => 'Unknown device', 'browser' => '', 'icon' => 'bi-question-circle'];
    }

    // The model the browser reported, added in config/database.php
    $model = preg_match('/\[Model: ([^\]]+)\]/', $ua, $m) ? $m[1] : '';

    if (preg_match('/bot|crawler|spider|curl|wget|python|okhttp/i', $ua)) {
        return ['type' => 'Automated', 'name' => 'Automated tool', 'browser' => '', 'icon' => 'bi-robot'];
    }

    if (preg_match('/iPhone.*?OS (\d+)[_.](\d+)/', $ua, $m)) {
        $type = 'Phone'; $name = 'iPhone'; $system = "iOS {$m[1]}.{$m[2]}"; $icon = 'bi-phone';
    } elseif (preg_match('/iPad.*?OS (\d+)[_.](\d+)/', $ua, $m)) {
        $type = 'Tablet'; $name = 'iPad'; $system = "iPadOS {$m[1]}.{$m[2]}"; $icon = 'bi-tablet';
    } elseif (preg_match('/Android ([\d.]+)(?:; ([^;)]+))?/', $ua, $m)) {
        $isPhone = stripos($ua, 'Mobile') !== false;
        $type = $isPhone ? 'Phone' : 'Tablet';
        $icon = $isPhone ? 'bi-phone' : 'bi-tablet';
        // Current Chrome replaces the model with a plain "K" in this string
        $inline = trim(preg_replace('/ Build\/.*$/', '', $m[2] ?? ''));
        if ($model === '' && $inline !== '' && $inline !== 'K' && stripos($inline, 'wv') === false) {
            $model = $inline;
        }
        $name = 'Android ' . ($isPhone ? 'phone' : 'tablet');
        $system = "Android {$m[1]}";
    } elseif (preg_match('/Windows NT/', $ua)) {
        $type = 'Computer'; $name = 'Windows computer'; $system = ''; $icon = 'bi-laptop';
    } elseif (preg_match('/Mac OS X/', $ua)) {
        $type = 'Computer'; $name = 'Mac'; $system = ''; $icon = 'bi-laptop';
    } elseif (preg_match('/CrOS/', $ua)) {
        $type = 'Computer'; $name = 'Chromebook'; $system = ''; $icon = 'bi-laptop';
    } elseif (preg_match('/Linux/', $ua)) {
        $type = 'Computer'; $name = 'Linux computer'; $system = ''; $icon = 'bi-laptop';
    } else {
        $type = 'Unknown'; $name = 'Unknown device'; $system = ''; $icon = 'bi-question-circle';
    }

    if ($model !== '') {
        $name = $model;
    }

    $browsers = [
        '/SamsungBrowser\/(\d+)/' => 'Samsung Internet',
        '/OPR\/(\d+)|Opera\/(\d+)/' => 'Opera',
        '/Edg(?:e|A|iOS)?\/(\d+)/' => 'Edge',
        '/CriOS\/(\d+)|Chrome\/(\d+)/' => 'Chrome',
        '/FxiOS\/(\d+)|Firefox\/(\d+)/' => 'Firefox',
        '/Version\/(\d+).*Safari/' => 'Safari',
    ];
    $browser = '';
    foreach ($browsers as $pattern => $label) {
        if (preg_match($pattern, $ua)) {
            $browser = $label;
            break;
        }
    }

    return [
        'type' => $type,
        'name' => $name,
        'browser' => trim($system . ($system !== '' && $browser !== '' ? ', ' : '') . $browser),
        'icon' => $icon,
    ];
}

/**
 * Only public addresses can be placed on a map.
 */
function isPublicIp($ip) {
    return filter_var((string) $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

function cachedIpLocations() {
    $cache = json_decode((string) getSetting('ip_location_cache', '{}'), true);
    return is_array($cache) ? $cache : [];
}

/**
 * Look up addresses not seen before. Only the IP address leaves the site, and the
 * answer is kept so each address uses one of the daily free lookups.
 *
 * @return array ip => "City, Region, Country"
 */
function lookupIpLocations(array $ips) {
    $cache = cachedIpLocations();
    $found = [];
    $changed = false;

    foreach (array_unique($ips) as $ip) {
        if (!isPublicIp($ip)) {
            continue;
        }
        if (isset($cache[$ip])) {
            $found[$ip] = $cache[$ip];
            continue;
        }

        $ch = curl_init(IP_LOCATION_URL . rawurlencode($ip));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 6]);
        $reply = json_decode((string) curl_exec($ch), true);
        curl_close($ch);

        // Over the daily limit, the reply carries an error and no country
        if (!is_array($reply) || empty($reply['countryName'])) {
            continue;
        }

        $place = implode(', ', array_filter([$reply['city'] ?? '', $reply['stateProv'] ?? '', $reply['countryName']]));
        $cache[$ip] = $found[$ip] = $place;
        $changed = true;
    }

    if ($changed) {
        saveSetting('ip_location_cache', json_encode(array_slice($cache, -IP_LOCATION_CACHE_SIZE, null, true)));
    }

    return $found;
}
