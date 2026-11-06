<?php
/**
 * VoteHub - Home Page
 * Awards Voting System
 */

require_once 'config/database.php';

$page_title = 'Home';
$page_description = 'VoteHubGH - Your trusted online awards voting platform. Vote for your favorite nominees in various award categories.';

// Fetch featured events (voting component only)
$pdo = getDBConnection();
$show_vote_counts = getSetting('show_vote_counts', '1') === '1';
$stmt = $pdo->prepare("
    SELECT e.*, 
           COUNT(DISTINCT c.id) as category_count,
           COUNT(DISTINCT n.id) as nominee_count,
           COALESCE(SUM(n.vote_count), 0) as total_votes
    FROM events e
    LEFT JOIN categories c ON e.id = c.event_id
    LEFT JOIN nominees n ON c.id = n.category_id
    WHERE e.event_type != 'ticketing'
    GROUP BY e.id
    ORDER BY (e.status = 'active' AND e.end_date >= NOW()) DESC, e.is_featured DESC, e.created_at DESC
    LIMIT 8
");
$stmt->execute();
$events = $stmt->fetchAll();

// Fetch active ticketed events for ticketing section
$tktStmt = $pdo->prepare("
    SELECT e.*, 
           u.fullname as organizer_name,
           (SELECT MIN(tc.price) FROM ticket_categories tc WHERE tc.event_id = e.id) as min_price,
           (SELECT MAX(tc.price) FROM ticket_categories tc WHERE tc.event_id = e.id) as max_price,
           (SELECT COUNT(*) FROM ticket_categories tc WHERE tc.event_id = e.id) as category_count,
           (SELECT COALESCE(SUM(t.quantity), 0) FROM tickets t WHERE t.event_id = e.id) as tickets_sold
    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.id
    WHERE e.status = 'active' AND e.end_date >= NOW() AND e.event_type IN ('ticketing', 'both')
    GROUP BY e.id
    ORDER BY e.is_featured DESC, e.created_at DESC
    LIMIT 3
");
$tktStmt->execute();
$ticketEvents = $tktStmt->fetchAll();

// Fetch site statistics
$statsStmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM events) as total_events,
        (SELECT COUNT(*) FROM nominees) as total_nominees,
        (SELECT COALESCE(SUM(vote_count), 0) FROM nominees) as total_votes,
        (SELECT COUNT(*) FROM categories) as total_categories
");
$stats = $statsStmt->fetch();

// Fetch top nominees for live results preview
$topNomineesStmt = $pdo->prepare("
    SELECT n.*, c.name as category_name, e.title as event_title
    FROM nominees n
    JOIN categories c ON n.category_id = c.id
    JOIN events e ON c.event_id = e.id
    WHERE e.status = 'active' AND n.status = 'active'
    ORDER BY n.vote_count DESC
    LIMIT 5
");
$topNomineesStmt->execute();
$topNominees = $topNomineesStmt->fetchAll();

// Event posters for the hero wall
$posterStmt = $pdo->query("
    SELECT title, slug, banner
    FROM events
    WHERE banner IS NOT NULL AND banner != ''
    ORDER BY (status = 'active' AND end_date >= NOW()) DESC, is_featured DESC, created_at DESC
    LIMIT 8
");
$posters = $posterStmt->fetchAll();
$posterColumns = [[], []];
foreach ($posters as $i => $poster) {
    $posterColumns[$i % 2][] = $poster;
}

$openEvents = array_values(array_filter($events, function ($event) {
    return $event['status'] === 'active'
        && strtotime($event['start_date']) <= time()
        && strtotime($event['end_date']) >= time();
}));

$eventImage = function ($event) {
    return $event['banner'] ? 'uploads/events/' . $event['banner'] : 'https://picsum.photos/800/1000?random=' . $event['id'];
};
$nomineeImage = function ($nominee) {
    return $nominee['photo']
        ? 'uploads/nominees/' . $nominee['photo']
        : 'https://ui-avatars.com/api/?name=' . urlencode($nominee['name']) . '&background=0F2C7A&color=fff&size=160';
};
$plural = function ($count, $one, $many) {
    return number_format($count) . ' ' . ($count == 1 ? $one : $many);
};

include 'includes/header.php';
?>

<!-- Hero -->
<section class="hx">
    <div class="container hx-grid">
        <div class="hx-copy">
            <p class="kicker hx-in" style="--i:0">
                <?php if (!empty($openEvents)): ?>
                    <span class="live-dot"></span><?php echo $plural(count($openEvents), 'event', 'events'); ?> open for voting
                <?php else: ?>
                    Ghana's home for awards voting
                <?php endif; ?>
            </p>
            <h1 class="hx-title">
                <span class="hx-line hx-in" style="--i:1">Every vote</span>
                <span class="hx-line hx-in" style="--i:2">puts someone</span>
                <span class="hx-line hx-in" style="--i:3"><em class="hx-stage">on stage.</em></span>
            </h1>
            <p class="hx-lead hx-in" style="--i:4">Back your nominee with Mobile Money or card, watch the count move, and book your seat for the big night.</p>

            <form action="vote" method="GET" class="code-box hx-in" style="--i:5">
                <label for="heroShortcode">Have a nominee code?</label>
                <div class="code-box-row">
                    <input type="text" id="heroShortcode" name="shortcode" placeholder="e.g. ABC12" autocomplete="off" required>
                    <button type="submit">Vote <i class="bi bi-arrow-right"></i></button>
                </div>
            </form>

            <div class="hx-links hx-in" style="--i:6">
                <a href="events" class="link-arrow">Browse all events <i class="bi bi-arrow-right"></i></a>
                <span class="hx-ussd"><i class="bi bi-phone"></i> No data? Dial <strong>*920*137#</strong></span>
            </div>
        </div>

        <?php if (!empty($posters)): ?>
        <div class="hx-wall" aria-hidden="true">
            <?php foreach ($posterColumns as $c => $column): ?>
                <?php if (empty($column)) continue; ?>
                <div class="wall-col wall-col-<?php echo $c; ?>">
                    <div class="wall-track">
                        <?php foreach (array_merge($column, $column) as $poster): ?>
                            <img src="uploads/events/<?php echo htmlspecialchars($poster['banner']); ?>" alt="" loading="lazy">
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!empty($topNominees) && $show_vote_counts): $leader = $topNominees[0]; ?>
                <div class="lead-card">
                    <img src="<?php echo $nomineeImage($leader); ?>" alt="">
                    <div>
                        <span class="lead-card-label">Leading now</span>
                        <strong><?php echo htmlspecialchars($leader['name']); ?></strong>
                        <span class="lead-card-votes"><?php echo $plural($leader['vote_count'], 'vote', 'votes'); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Event ticker -->
<?php if (!empty($events)): ?>
<div class="ticker" aria-hidden="true">
    <div class="ticker-track">
        <?php for ($pass = 0; $pass < 2; $pass++): ?>
            <div class="ticker-group">
                <?php foreach ($events as $event):
                    $isEnded = strtotime($event['end_date']) < time();
                    $isUpcoming = strtotime($event['start_date']) > time();
                    $daysLeft = (int) floor((strtotime($event['end_date']) - time()) / 86400);
                    if ($isEnded) {
                        $state = 'Voting closed';
                    } elseif ($isUpcoming) {
                        $state = 'Opens ' . date('j M', strtotime($event['start_date']));
                    } elseif ($daysLeft < 1) {
                        $state = 'Last day to vote';
                    } else {
                        $state = $plural($daysLeft, 'day', 'days') . ' left';
                    }
                ?>
                    <span class="ticker-item"><?php echo htmlspecialchars($event['title']); ?> <small><?php echo $state; ?></small></span>
                <?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

<!-- The count so far -->
<section class="figures">
    <div class="container">
        <dl class="figures-row rv">
            <?php if ($show_vote_counts): ?>
            <div>
                <dd class="counter" data-target="<?php echo (int) $stats['total_votes']; ?>"><?php echo number_format($stats['total_votes']); ?></dd>
                <dt>votes cast</dt>
            </div>
            <?php endif; ?>
            <div>
                <dd class="counter" data-target="<?php echo (int) $stats['total_nominees']; ?>"><?php echo number_format($stats['total_nominees']); ?></dd>
                <dt>nominees</dt>
            </div>
            <div>
                <dd class="counter" data-target="<?php echo (int) $stats['total_categories']; ?>"><?php echo number_format($stats['total_categories']); ?></dd>
                <dt>categories</dt>
            </div>
            <div>
                <dd class="counter" data-target="<?php echo (int) $stats['total_events']; ?>"><?php echo number_format($stats['total_events']); ?></dd>
                <dt>events hosted</dt>
            </div>
        </dl>
    </div>
</section>

<!-- Events -->
<section class="sec" id="featured-events">
    <div class="container">
        <header class="sec-head rv">
            <div>
                <p class="kicker">On the ballot</p>
                <h2>Pick your event,<br>back your favourite.</h2>
            </div>
            <a href="events" class="link-arrow">All events <i class="bi bi-arrow-right"></i></a>
        </header>

        <?php if (empty($events)): ?>
            <p class="quiet-empty">New events are on their way. Check back soon.</p>
        <?php else: ?>
            <div class="ev-grid">
                <?php foreach ($events as $index => $event) { include 'includes/event-card.php'; } ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Tickets -->
<section class="sec sec-tint">
    <div class="container">
        <header class="sec-head rv">
            <div>
                <p class="kicker">Tickets</p>
                <h2>Don't just vote.<br>Be in the room.</h2>
            </div>
            <a href="ticketing" class="link-arrow">All tickets <i class="bi bi-arrow-right"></i></a>
        </header>

        <?php if (empty($ticketEvents)): ?>
            <p class="quiet-empty">No tickets on sale right now. New shows are added here first.</p>
        <?php else: ?>
            <div class="stub-list">
                <?php foreach ($ticketEvents as $i => $event):
                    $daysLeft = (int) floor((strtotime($event['end_date']) - time()) / 86400);
                    $catStmt = $pdo->prepare("SELECT * FROM ticket_categories WHERE event_id = ? ORDER BY price ASC");
                    $catStmt->execute([$event['id']]);
                    $categories = $catStmt->fetchAll();
                ?>
                    <article class="stub rv" style="--d:<?php echo $i * 80; ?>ms">
                        <img class="stub-img" src="<?php echo $eventImage($event); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" loading="lazy">
                        <div class="stub-main">
                            <p class="stub-date">On sale until <?php echo date('j M Y', strtotime($event['end_date'])); ?></p>
                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                            <p class="stub-meta">
                                <?php echo $plural($event['category_count'], 'ticket type', 'ticket types'); ?>
                                <?php if ($event['event_code']): ?>&middot; Code <?php echo htmlspecialchars($event['event_code']); ?><?php endif; ?>
                            </p>
                        </div>
                        <div class="stub-side">
                            <?php if ((float) $event['min_price'] > 0): ?>
                                <span class="stub-from">From</span>
                                <span class="stub-price"><small>GHS</small> <?php echo number_format((float) $event['min_price'], 2); ?></span>
                            <?php endif; ?>
                            <button type="button" class="btn-ticket-buy"
                                    data-event-id="<?php echo $event['id']; ?>"
                                    data-event-title="<?php echo htmlspecialchars($event['title']); ?>"
                                    data-event-banner="<?php echo $eventImage($event); ?>"
                                    data-categories='<?php echo htmlspecialchars(json_encode($categories), ENT_QUOTES); ?>'>
                                Buy ticket
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Live race -->
<?php if (!empty($topNominees)): ?>
<section class="sec sec-night live-results">
    <div class="container">
        <header class="sec-head rv">
            <div>
                <p class="kicker"><span class="live-dot"></span>Live</p>
                <h2>The race<br>right now.</h2>
            </div>
            <a href="results" class="link-arrow">Full results <i class="bi bi-arrow-right"></i></a>
        </header>

        <ol class="race">
            <?php
            $maxVotes = max(1, (int) $topNominees[0]['vote_count']);
            foreach ($topNominees as $index => $nominee):
                $percentage = ($nominee['vote_count'] / $maxVotes) * 100;
            ?>
                <li class="result-item animate-on-scroll rv" style="--d:<?php echo $index * 70; ?>ms" data-nominee-id="<?php echo $nominee['id']; ?>">
                    <span class="race-rank"><?php echo $index + 1; ?></span>
                    <img src="<?php echo $nomineeImage($nominee); ?>" alt="" class="race-photo" loading="lazy">
                    <div class="race-who">
                        <strong><?php echo htmlspecialchars($nominee['name']); ?></strong>
                        <span><?php echo htmlspecialchars($nominee['category_name']); ?> &middot; <?php echo htmlspecialchars($nominee['event_title']); ?></span>
                    </div>
                    <?php if ($show_vote_counts): ?>
                        <span class="vote-count"><?php echo number_format($nominee['vote_count']); ?></span>
                        <div class="race-bar"><div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div></div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>

        <?php if (!$show_vote_counts): ?>
            <p class="race-note">Vote counts are hidden for now. Nominees can follow their numbers in the <a href="nominee-results">results portal</a>.</p>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- How it works -->
<section class="sec">
    <div class="container how">
        <div class="how-intro rv">
            <p class="kicker">How it works</p>
            <h2>Four steps.<br>About a minute.</h2>
            <div class="how-switch" id="how-pills-tab" role="tablist">
                <button class="active" id="pills-voting-tab" data-bs-toggle="pill" data-bs-target="#pills-voting" type="button" role="tab" aria-controls="pills-voting" aria-selected="true">Voting</button>
                <button id="pills-ticketing-tab" data-bs-toggle="pill" data-bs-target="#pills-ticketing" type="button" role="tab" aria-controls="pills-ticketing" aria-selected="false">Tickets</button>
            </div>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="pills-voting" role="tabpanel" aria-labelledby="pills-voting-tab">
                <ol class="steps">
                    <li class="rv"><h3>Find your event</h3><p>Open an event, or type your nominee's code to go straight there.</p></li>
                    <li class="rv"><h3>Choose your nominee</h3><p>Pick the category and the person you're backing.</p></li>
                    <li class="rv"><h3>Pay for your votes</h3><p>Choose how many votes, then pay with Mobile Money or card.</p></li>
                    <li class="rv"><h3>Watch the count</h3><p>Your votes are added once payment is confirmed.</p></li>
                </ol>
            </div>
            <div class="tab-pane fade" id="pills-ticketing" role="tabpanel" aria-labelledby="pills-ticketing-tab">
                <ol class="steps">
                    <li><h3>Find the show</h3><p>Browse the events with tickets on sale.</p></li>
                    <li><h3>Choose your ticket</h3><p>Pick Regular, VIP or another type, and how many you need.</p></li>
                    <li><h3>Pay your way</h3><p>Use Mobile Money or a debit or credit card.</p></li>
                    <li><h3>Get your code</h3><p>Your ticket code arrives by SMS. Show it at the door.</p></li>
                </ol>
            </div>
        </div>
    </div>
</section>

<!-- Why -->
<section class="sec sec-tint">
    <div class="container why">
        <h2 class="rv">Made for the way Ghana pays, votes and shows up.</h2>
        <dl class="why-list">
            <div class="rv"><dt>Mobile Money or card</dt><dd>Pay the way you already do. No account needed to vote.</dd></div>
            <div class="rv"><dt>Works without data</dt><dd>Dial *920*137# and vote from any phone.</dd></div>
            <div class="rv"><dt>Results you can follow</dt><dd>Organizers can show live counts as votes come in.</dd></div>
            <div class="rv"><dt>Tickets by SMS</dt><dd>Your code reaches your phone as soon as you've paid.</dd></div>
        </dl>
    </div>
</section>

<!-- Organizers -->
<section class="host">
    <div class="container host-inner rv">
        <div>
            <p class="kicker">For organizers</p>
            <h2>Running an awards night?</h2>
            <p>Set up your categories and nominees, sell tickets, and let your audience decide.</p>
            <div class="host-actions">
                <a href="register" class="btn-solid">Create an organizer account</a>
                <a href="contact" class="link-arrow">Talk to us <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
        <img src="<?php echo assetUrl('assets/logo/emblem.png'); ?>" alt="" class="host-emblem" loading="lazy">
    </div>
</section>

<?php include 'includes/vote-modal.php'; ?>
<?php include 'includes/ticket-modal.php'; ?>
<?php include 'includes/footer.php'; ?>
