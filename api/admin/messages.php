<?php
/**
 * API for the admin Messages page.
 * Every change needs an admin session and the CSRF token.
 */

require_once '../../config/database.php';
require_once '../../config/nalo.php';
require_once '../../includes/sms-center.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);

function respond($payload, $code = 200) {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

if (!isLoggedIn() || !isAdmin()) {
    respond(['status' => 'error', 'message' => 'Unauthorized access'], 403);
}

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'contacts') {
        $audience = $_GET['audience'] ?? '';
        if (!array_key_exists($audience, smsAudiences())) {
            respond(['status' => 'error', 'message' => 'Unknown audience'], 400);
        }
        respond(['status' => 'success'] + smsContacts($pdo, $audience, (int) ($_GET['event_id'] ?? 0)));
    }

    if ($action === 'history') {
        respond(['status' => 'success', 'history' => smsHistory($pdo)]);
    }

    respond(['status' => 'error', 'message' => 'Unknown action'], 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['status' => 'error', 'message' => 'Invalid request method'], 405);
}

if (!verifyCSRFToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    respond(['status' => 'error', 'message' => 'Your session has expired. Refresh the page and try again.'], 403);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    respond(['status' => 'error', 'message' => 'Invalid request'], 400);
}

switch ($data['action'] ?? '') {
    case 'vote_sms':
        $enabled = !empty($data['enabled']);
        if (!saveSetting('sms_vote_confirmation_enabled', $enabled ? '1' : '0')) {
            respond(['status' => 'error', 'message' => 'The setting could not be saved. Check Diagnostics.'], 500);
        }
        logAudit(getCurrentUserId(), 'sms_vote_confirmation_' . ($enabled ? 'on' : 'off'), 'settings');
        respond(['status' => 'success', 'enabled' => $enabled]);

    case 'save_template':
        $title = trim((string) ($data['title'] ?? ''));
        $text = trim((string) ($data['text'] ?? ''));
        if ($title === '' || $text === '') {
            respond(['status' => 'error', 'message' => 'Give the message a name and some text.'], 400);
        }
        $templates = smsReadJsonSetting('sms_custom_templates');
        if (count($templates) >= SMS_MAX_TEMPLATES) {
            respond(['status' => 'error', 'message' => 'You can keep up to ' . SMS_MAX_TEMPLATES . ' saved messages. Delete one first.'], 400);
        }
        $templates[] = ['id' => bin2hex(random_bytes(6)), 'title' => mb_substr($title, 0, 60), 'text' => mb_substr($text, 0, SMS_MAX_MESSAGE_LENGTH)];
        if (!saveSetting('sms_custom_templates', json_encode($templates))) {
            respond(['status' => 'error', 'message' => 'The message could not be saved.'], 500);
        }
        respond(['status' => 'success', 'templates' => $templates]);

    case 'delete_template':
        $id = (string) ($data['id'] ?? '');
        $templates = array_values(array_filter(smsReadJsonSetting('sms_custom_templates'), function ($template) use ($id) {
            return ($template['id'] ?? '') !== $id;
        }));
        if (!saveSetting('sms_custom_templates', json_encode($templates))) {
            respond(['status' => 'error', 'message' => 'The message could not be deleted.'], 500);
        }
        respond(['status' => 'success', 'templates' => $templates]);

    case 'save_blocked':
        $numbers = [];
        $invalid = [];
        foreach (preg_split('/[\s,;]+/', (string) ($data['numbers'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $raw) {
            $phone = smsNormalizePhone($raw);
            if ($phone) {
                $numbers[$phone] = true;
            } else {
                $invalid[] = $raw;
            }
        }
        $numbers = array_keys($numbers);
        if (!saveSetting('sms_blocked_numbers', json_encode(array_map('strval', $numbers)))) {
            respond(['status' => 'error', 'message' => 'The list could not be saved. It may be too long.'], 500);
        }
        respond(['status' => 'success', 'numbers' => array_map('strval', $numbers), 'invalid' => $invalid]);

    case 'send':
        sendBatch($pdo, $data);

    default:
        respond(['status' => 'error', 'message' => 'Unknown action'], 400);
}

/**
 * Send one small batch. The page loops over batches so a large send never hits
 * the host's time limit, and can be stopped part way.
 *
 * Recipients are checked against the audience here, and names and event details
 * come from the database, so the browser only ever chooses who, never what data.
 */
function sendBatch($pdo, $data) {
    $message = trim((string) ($data['message'] ?? ''));
    $audience = (string) ($data['audience'] ?? '');
    $campaign = (string) ($data['campaign'] ?? '');
    $test = !empty($data['test']);
    $phones = is_array($data['phones'] ?? null) ? $data['phones'] : [];
    $manual = is_array($data['manual'] ?? null) ? $data['manual'] : [];

    if ($message === '') {
        respond(['status' => 'error', 'message' => 'Write a message first.'], 400);
    }
    if (mb_strlen($message) > SMS_MAX_MESSAGE_LENGTH) {
        respond(['status' => 'error', 'message' => 'That message is too long.'], 400);
    }
    if (!preg_match('/^[a-f0-9]{8,32}$/', $campaign)) {
        respond(['status' => 'error', 'message' => 'Invalid send reference.'], 400);
    }
    if (count($phones) + count($manual) > SMS_MAX_BATCH) {
        respond(['status' => 'error', 'message' => 'Too many recipients in one batch.'], 400);
    }

    $blocked = array_flip(smsBlockedNumbers());
    $recipients = [];

    if ($phones) {
        if (!array_key_exists($audience, smsAudiences())) {
            respond(['status' => 'error', 'message' => 'Unknown audience'], 400);
        }
        $known = [];
        foreach (smsContacts($pdo, $audience, (int) ($data['event_id'] ?? 0))['contacts'] as $contact) {
            $known[$contact['phone']] = $contact;
        }
        foreach ($phones as $phone) {
            if (isset($known[$phone])) {
                $recipients[$phone] = $known[$phone];
            }
        }
    }

    $invalid = [];
    foreach ($manual as $raw) {
        $phone = smsNormalizePhone((string) $raw);
        if (!$phone) {
            $invalid[] = (string) $raw;
        } elseif (!isset($recipients[$phone]) && ($test || !isset($blocked[$phone]))) {
            $recipients[$phone] = ['phone' => $phone];
        }
    }

    // Release the session so the rest of the admin stays responsive while sending
    session_write_close();
    set_time_limit(180);

    $sent = [];
    $failed = [];
    $reason = '';
    foreach ($recipients as $phone => $contact) {
        $result = sendNaloSMS($phone, smsFillMessage($message, $contact));
        if (($result['status'] ?? '') === 'success') {
            $sent[] = (string) $phone;
        } else {
            $failed[] = (string) $phone;
            $reason = $reason ?: smsFailureReason($result);
            error_log('SMS failed for ' . $phone . ': ' . json_encode($result));
        }
    }

    logAudit(getCurrentUserId(), 'sms_campaign', null, null, null, [
        'campaign' => $campaign,
        'audience' => $audience,
        'message'  => $message,
        'test'     => $test,
        'sent'     => count($sent),
        'failed'   => $failed,
    ]);

    respond(['status' => 'success', 'sent' => $sent, 'failed' => $failed, 'invalid' => $invalid, 'reason' => $reason]);
}
