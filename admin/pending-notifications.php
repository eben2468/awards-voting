<?php
/**
 * Admin Messages
 * Send SMS to chosen contacts and control automatic vote messages.
 */

require_once '../config/database.php';
require_once '../config/nalo.php';
require_once '../includes/sms-center.php';

// Require admin
if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied. Admin privileges required.');
    redirect('login.php');
}

$page_title = 'Messages';
$pdo = getDBConnection();

$events = $pdo->query("SELECT id, title FROM events ORDER BY created_at DESC")->fetchAll();
$voteSmsOn = getSetting('sms_vote_confirmation_enabled', '1') === '1';
$blockedNumbers = smsBlockedNumbers();

$messageConfig = [
    'api'         => SITE_URL . '/api/admin/messages.php',
    'siteUrl'     => SITE_URL,
    'audiences'   => smsAudiences(),
    'suggestions' => smsSuggestedMessages(),
    'templates'   => smsReadJsonSetting('sms_custom_templates'),
    'fallbacks'   => smsTagFallbacks(),
    'maxLength'   => SMS_MAX_MESSAGE_LENGTH,
    'batchSize'   => SMS_MAX_BATCH,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/logo/favicon.png?v=2">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/portal.css'); ?>">
</head>
<body class="admin-body">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-main">
        <?php include 'includes/topbar.php'; ?>

        <div class="admin-content p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Messages</h2>
                    <p class="text-muted mb-0">Choose who to reach, write your message and send it by SMS.</p>
                </div>
                <a href="transactions.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Transactions
                </a>
            </div>

            <?php if (preg_match('#//(localhost|127\.)#', SITE_URL)): ?>
                <div class="alert alert-warning">
                    <strong>You are on your computer's copy of the site.</strong>
                    Links in messages sent from here point to this computer, so they will not open for anyone else. Send real messages from votehubgh.com.
                </div>
            <?php endif; ?>

            <!-- Automatic messages -->
            <div class="card mb-4">
                <div class="card-body msg-auto">
                    <div>
                        <h5 class="mb-1">SMS after every vote</h5>
                        <p class="text-muted mb-0" id="voteSmsStatus"></p>
                    </div>
                    <div class="form-check form-switch msg-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="voteSmsToggle" aria-label="Send an SMS after every successful vote" <?php echo $voteSmsOn ? 'checked' : ''; ?>>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Contacts -->
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Who should get this?</h5>
                        </div>
                        <div class="card-body">
                            <div class="msg-filters">
                                <div>
                                    <label class="form-label" for="audience">Group</label>
                                    <select class="form-select" id="audience">
                                        <?php foreach (smsAudiences() as $key => $label): ?>
                                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label" for="eventFilter">Event</label>
                                    <select class="form-select" id="eventFilter">
                                        <option value="0">All events</option>
                                        <?php foreach ($events as $event): ?>
                                            <option value="<?php echo (int) $event['id']; ?>"><?php echo htmlspecialchars($event['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="msg-search">
                                    <label class="form-label" for="contactSearch">Search</label>
                                    <input type="search" class="form-control" id="contactSearch" placeholder="Name, phone, nominee...">
                                </div>
                            </div>

                            <p class="msg-note" id="contactNote" hidden></p>

                            <div class="msg-listbar">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="selectAll">
                                    <label class="form-check-label" for="selectAll" id="selectAllLabel">Select all</label>
                                </div>
                                <span class="text-muted"><strong id="selectedCount">0</strong> selected</span>
                            </div>

                            <div class="msg-list" id="contactList" aria-live="polite"></div>
                            <button type="button" class="btn btn-outline-secondary w-100 mt-2" id="showMore" hidden>Show more</button>

                            <details class="msg-more">
                                <summary>Add numbers yourself</summary>
                                <label class="form-label mt-2" for="manualNumbers">Phone numbers</label>
                                <textarea class="form-control" id="manualNumbers" rows="3" placeholder="0241234567, 0551234567"></textarea>
                                <div class="form-text" id="manualInfo">Separate numbers with commas, spaces or new lines.</div>
                            </details>
                        </div>
                    </div>
                </div>

                <!-- Composer -->
                <div class="col-lg-5">
                    <div class="card msg-composer">
                        <div class="card-header">
                            <h5 class="mb-0">Your message</h5>
                        </div>
                        <div class="card-body">
                            <span class="form-label d-block">Suggestions</span>
                            <div class="msg-chips" id="suggestions"></div>

                            <label class="form-label mt-3" for="message">Message</label>
                            <textarea class="form-control" id="message" rows="6" maxlength="<?php echo SMS_MAX_MESSAGE_LENGTH; ?>" placeholder="Pick a suggestion or write your own"></textarea>

                            <div class="msg-tags" id="tags" aria-label="Insert personal details">
                                <button type="button" data-tag="{name}">Name</button>
                                <button type="button" data-tag="{event}">Event</button>
                                <button type="button" data-tag="{nominee}">Nominee</button>
                                <button type="button" data-tag="{code}">Code</button>
                                <button type="button" data-tag="{amount}">Amount</button>
                                <button type="button" data-tag="{link}">Link</button>
                            </div>
                            <div class="msg-counter">
                                <span id="charCount">0 characters</span>
                                <button type="button" class="btn btn-link btn-sm p-0" id="saveToggle">Save this message</button>
                            </div>

                            <div class="msg-inline" id="saveRow" hidden>
                                <input type="text" class="form-control" id="saveTitle" maxlength="60" placeholder="Name this message" aria-label="Message name">
                                <button type="button" class="btn btn-outline-primary" id="saveTemplate">Save</button>
                            </div>

                            <div class="msg-preview">
                                <span class="msg-preview-label" id="previewLabel">Preview</span>
                                <p id="preview"></p>
                            </div>

                            <div class="msg-inline">
                                <input type="tel" class="form-control" id="testNumber" placeholder="Your number" aria-label="Test phone number">
                                <button type="button" class="btn btn-outline-secondary" id="sendTest">Send test</button>
                            </div>

                            <div class="alert mb-3 mt-3" id="notice" role="status" hidden></div>

                            <button type="button" class="btn btn-primary w-100 mt-3" id="sendBtn" disabled>
                                <i class="bi bi-send me-2"></i><span id="sendLabel">Choose contacts to send</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- History -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Recent sends</h5>
                </div>
                <div class="msg-history" id="history"></div>
            </div>

            <!-- Do not contact -->
            <div class="card mt-4">
                <div class="card-body">
                    <details class="msg-more msg-more-flush">
                        <summary>Do not contact list (<span id="blockedCount"><?php echo count($blockedNumbers); ?></span>)</summary>
                        <p class="text-muted mt-2 mb-2">Numbers here never receive messages sent from this page. Add anyone who asks to stop hearing from you.</p>
                        <label class="form-label" for="blockedNumbers">Phone numbers</label>
                        <textarea class="form-control" id="blockedNumbers" rows="3"><?php echo htmlspecialchars(implode("\n", $blockedNumbers)); ?></textarea>
                        <div class="msg-inline mt-2">
                            <span class="form-text flex-grow-1 mt-0" id="blockedInfo"></span>
                            <button type="button" class="btn btn-outline-primary" id="saveBlocked">Save list</button>
                        </div>
                    </details>
                </div>
            </div>
        </div>
    </main>

    <!-- Confirm and progress -->
    <div class="modal fade" id="sendModal" tabindex="-1" aria-labelledby="sendTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sendTitle">Send this message?</h5>
                </div>
                <div class="modal-body">
                    <div id="confirmStep">
                        <dl class="msg-facts">
                            <div><dt>Recipients</dt><dd id="factRecipients"></dd></div>
                            <div><dt>SMS credits, about</dt><dd id="factUnits"></dd></div>
                        </dl>
                        <span class="msg-preview-label" id="factPreviewLabel"></span>
                        <p class="msg-confirm-text" id="factPreview"></p>
                        <p class="text-muted mb-0">This goes out to real phones straight away and cannot be undone.</p>
                    </div>
                    <div id="progressStep" hidden>
                        <div class="progress mb-2" role="progressbar" aria-label="Sending progress">
                            <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                        </div>
                        <p class="mb-0" id="progressText"></p>
                        <p class="text-danger mt-2 mb-0" id="failedList" hidden></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="modalCancel">Cancel</button>
                    <button type="button" class="btn btn-primary" id="modalConfirm">Send now</button>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="messageConfig"><?php echo json_encode($messageConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
    <script src="<?php echo assetUrl('assets/js/messages.js'); ?>"></script>
</body>
</html>
