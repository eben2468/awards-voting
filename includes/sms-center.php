<?php
/**
 * Shared logic for the admin Messages page: who can be messaged, and what
 * gets sent to them. Everything is stored in settings and audit_logs, so the
 * feature needs no new database tables.
 */

define('SMS_MAX_MESSAGE_LENGTH', 1000);
define('SMS_MAX_BATCH', 25);
define('SMS_MAX_SCAN', 20000);
define('SMS_MAX_TEMPLATES', 30);

function smsAudiences() {
    return [
        'pending'   => 'Pending payments',
        'failed'    => 'Failed payments',
        'voters'    => 'Past voters',
        'tickets'   => 'Ticket buyers',
        'users'     => 'Registered users',
        'organizers' => 'Organizers',
    ];
}

/**
 * Turn any phone format into 233XXXXXXXXX. Returns null if it cannot be a number.
 */
function smsNormalizePhone($raw) {
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }
    $phone = formatPhoneNumberForNalo($raw);
    return preg_match('/^\d{10,15}$/', $phone) ? $phone : null;
}

function smsReadJsonSetting($key) {
    $value = json_decode((string) getSetting($key, '[]'), true);
    return is_array($value) ? $value : [];
}

function smsBlockedNumbers() {
    return smsReadJsonSetting('sms_blocked_numbers');
}

function smsTransactionPhone($row) {
    if (!empty($row['phone'])) {
        return $row['phone'];
    }
    $metadata = is_string($row['metadata'] ?? null) ? json_decode($row['metadata'], true) : null;
    return $metadata['phone'] ?? $metadata['msisdn'] ?? null;
}

function smsEventLink($slug) {
    return $slug ? SITE_URL . '/event-details?slug=' . urlencode($slug) : SITE_URL;
}

/**
 * Everyone in an audience, one entry per phone number, newest activity first.
 *
 * Payment audiences leave out anyone who has since paid for the same event, so
 * nobody who already completed their vote gets a "please try again" message.
 */
function smsContacts($pdo, $audience, $eventId = 0) {
    $blocked = array_flip(smsBlockedNumbers());
    $contacts = [];
    $leftOutPaid = 0;
    $leftOutBlocked = 0;
    $seenBlocked = [];
    $truncated = false;

    if ($audience === 'users' || $audience === 'organizers') {
        $stmt = $pdo->prepare("
            SELECT fullname, phone, created_at FROM users
            WHERE role = ? AND status = 'active' AND phone IS NOT NULL AND phone != ''
            ORDER BY created_at DESC
            LIMIT " . SMS_MAX_SCAN);
        $stmt->execute([$audience === 'users' ? 'user' : 'organizer']);

        foreach ($stmt->fetchAll() as $row) {
            $phone = smsNormalizePhone($row['phone']);
            if (!$phone || isset($contacts[$phone])) {
                continue;
            }
            if (isset($blocked[$phone])) {
                $seenBlocked[$phone] = true;
                continue;
            }
            $contacts[$phone] = [
                'phone' => $phone, 'name' => $row['fullname'] ?: '', 'event' => '', 'nominee' => '', 'code' => '',
                'amount' => '', 'link' => SITE_URL, 'last' => $row['created_at'], 'count' => 1,
            ];
        }

        return ['contacts' => array_values($contacts), 'left_out_paid' => 0, 'left_out_blocked' => count($seenBlocked), 'truncated' => false];
    }

    $conditions = [
        'pending' => "t.status = 'pending'",
        'failed'  => "t.status = 'failed'",
        'voters'  => "t.status = 'success' AND t.nominee_id IS NOT NULL",
        'tickets' => "t.status = 'success' AND t.ticket_category_id IS NOT NULL",
    ];
    if (!isset($conditions[$audience])) {
        return ['contacts' => [], 'left_out_paid' => 0, 'left_out_blocked' => 0, 'truncated' => false];
    }

    $where = $conditions[$audience];
    $params = [];
    if ($eventId > 0) {
        $where .= ' AND t.event_id = ?';
        $params[] = $eventId;
    }

    $stmt = $pdo->prepare("
        SELECT t.phone, t.metadata, t.amount, t.event_id, t.created_at,
               u.fullname, e.title AS event_title, e.slug, n.name AS nominee_name, n.shortcode
        FROM transactions t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN events e ON t.event_id = e.id
        LEFT JOIN nominees n ON t.nominee_id = n.id
        WHERE $where
        ORDER BY t.created_at DESC, t.id DESC
        LIMIT " . (SMS_MAX_SCAN + 1));
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    if (count($rows) > SMS_MAX_SCAN) {
        $truncated = true;
        array_pop($rows);
    }

    // Latest successful payment per phone and event
    $paid = [];
    if ($audience === 'pending' || $audience === 'failed') {
        $paidRows = $pdo->query("
            SELECT phone, event_id, MAX(created_at) AS paid_at FROM transactions
            WHERE status = 'success' AND phone IS NOT NULL AND phone != ''
            GROUP BY phone, event_id
        ")->fetchAll();
        foreach ($paidRows as $row) {
            $phone = smsNormalizePhone($row['phone']);
            if ($phone) {
                $key = $phone . '|' . $row['event_id'];
                if (!isset($paid[$key]) || $row['paid_at'] > $paid[$key]) {
                    $paid[$key] = $row['paid_at'];
                }
            }
        }
    }

    $paidLater = [];
    foreach ($rows as $row) {
        $phone = smsNormalizePhone(smsTransactionPhone($row));
        if (!$phone) {
            continue;
        }
        if (isset($contacts[$phone])) {
            $contacts[$phone]['count']++;
            continue;
        }
        if (isset($blocked[$phone])) {
            $seenBlocked[$phone] = true;
            continue;
        }
        $key = $phone . '|' . $row['event_id'];
        if (isset($paid[$key]) && $paid[$key] >= $row['created_at']) {
            $paidLater[$phone] = true;
            continue;
        }

        $contacts[$phone] = [
            'phone'   => $phone,
            'name'    => $row['fullname'] ?: '',
            'event'   => $row['event_title'] ?: '',
            'nominee' => $row['nominee_name'] ?: '',
            'code'    => $row['shortcode'] ?: '',
            'amount'  => 'GHS ' . number_format((float) $row['amount'], 2),
            'link'    => smsEventLink($row['slug']),
            'last'    => $row['created_at'],
            'count'   => 1,
        ];
    }

    $leftOutPaid = count(array_diff_key($paidLater, $contacts));
    $leftOutBlocked = count($seenBlocked);

    return ['contacts' => array_values($contacts), 'left_out_paid' => $leftOutPaid, 'left_out_blocked' => $leftOutBlocked, 'truncated' => $truncated];
}

/**
 * What each merge tag becomes when a contact has no value for it.
 */
function smsTagFallbacks() {
    return [
        'name'    => 'there',
        'event'   => 'the event',
        'nominee' => 'your nominee',
        'code'    => 'your nominee code',
        'amount'  => 'your payment',
        'link'    => SITE_URL,
    ];
}

/**
 * Turn a failed send into something an admin can act on.
 */
function smsFailureReason($result) {
    $detail = (string) ($result['message'] ?? '');
    if (preg_match('/resolve host|connect|timed out|timeout|network/i', $detail)) {
        return 'Could not reach the SMS service, so nothing was sent. Check the internet connection and try again.';
    }
    if ($detail === 'API Key not set') {
        return 'The SMS key is missing from the .env file.';
    }
    return 'The SMS service refused this message' . ($detail !== '' ? ': ' . mb_substr($detail, 0, 160) : '.');
}

function smsFirstName($name) {
    $parts = preg_split('/\s+/', trim((string) $name));
    return $parts[0] ?? '';
}

function smsFillMessage($message, $contact) {
    $fallbacks = smsTagFallbacks();
    $values = [];
    foreach ($fallbacks as $tag => $fallback) {
        $value = $tag === 'name' ? smsFirstName($contact['name'] ?? '') : ($contact[$tag] ?? '');
        $values['{' . $tag . '}'] = $value !== '' ? $value : $fallback;
    }
    return strtr($message, $values);
}

/**
 * Ready-made messages. "for" lists the audiences each one suits best.
 */
function smsSuggestedMessages() {
    $results = preg_replace('#^https?://#', '', SITE_URL) . '/results';

    return [
        ['title' => 'Payment reminder', 'for' => ['pending'],
         'text' => "Hi {name}, your {amount} payment for {nominee} in {event} has not completed yet. Please try again at {link} or dial *920*137#. Thank you."],
        ['title' => 'Failed payment', 'for' => ['failed', 'pending'],
         'text' => "Hi {name}, your vote for {nominee} did not go through, and this can happen with network delays. Please try again at {link} or dial *920*137#."],
        ['title' => 'Thank you', 'for' => ['voters'],
         'text' => "Thank you for voting for {nominee} in {event}, {name}. Every vote counts. Keep the support going at {link} or dial *920*137#."],
        ['title' => 'Voting closes soon', 'for' => ['voters', 'pending', 'failed', 'users'],
         'text' => "Voting for {event} closes soon. There is still time to back {nominee}. Use code {code} on *920*137# or vote at {link}."],
        ['title' => 'Voting is open', 'for' => ['voters', 'users'],
         'text' => "Voting for {event} is now open. Support your favourite at {link} or dial *920*137#."],
        ['title' => 'Results are out', 'for' => ['voters', 'users'],
         'text' => "Voting for {event} has closed. Thank you for taking part, {name}. See the results at $results"],
        ['title' => 'Event reminder', 'for' => ['tickets'],
         'text' => "Hi {name}, {event} is coming up. Keep your ticket SMS close and show your ticket code at the entrance. See you there."],
        ['title' => 'Tickets on sale', 'for' => ['voters', 'tickets', 'users'],
         'text' => "Tickets for {event} are on sale now. Get yours at {link} or dial *920*137#."],
        ['title' => 'Organizer update', 'for' => ['organizers'],
         'text' => "Hello {name}, this is a quick update from VoteHubGH. Please sign in to your organizer dashboard for details."],
    ];
}

/**
 * Past sends, newest first. A send is logged in chunks, so rows are regrouped here.
 */
function smsHistory($pdo, $limit = 12) {
    $rows = $pdo->query("
        SELECT a.new_values, a.created_at, u.fullname
        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.action = 'sms_campaign'
        ORDER BY a.id DESC
        LIMIT 400
    ")->fetchAll();

    $campaigns = [];
    foreach ($rows as $row) {
        $data = json_decode((string) $row['new_values'], true);
        if (!is_array($data) || empty($data['campaign'])) {
            continue;
        }
        $id = $data['campaign'];
        if (!isset($campaigns[$id])) {
            if (count($campaigns) >= $limit) {
                continue;
            }
            $campaigns[$id] = [
                'id' => $id, 'when' => $row['created_at'], 'by' => $row['fullname'] ?: 'Admin',
                'audience' => $data['audience'] ?? '', 'message' => $data['message'] ?? '',
                'test' => !empty($data['test']), 'sent' => 0, 'failed' => [],
            ];
        }
        $campaigns[$id]['sent'] += (int) ($data['sent'] ?? 0);
        $campaigns[$id]['failed'] = array_merge($campaigns[$id]['failed'], (array) ($data['failed'] ?? []));
    }

    return array_values($campaigns);
}
