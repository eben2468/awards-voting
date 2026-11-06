<?php
/**
 * Compact voting event card, used on the homepage and the events page.
 * Expects $event, $index and $show_vote_counts from the page that includes it.
 */
$cardIsEnded = strtotime($event['end_date']) < time();
$cardIsUpcoming = strtotime($event['start_date']) > time();
$cardDaysLeft = (int) floor((strtotime($event['end_date']) - time()) / 86400);

if ($cardIsEnded) {
    $cardState = 'Voting closed';
    $cardStateClass = 'is-closed';
    $cardAction = 'See results';
} elseif ($cardIsUpcoming) {
    $cardState = 'Opens ' . date('j M', strtotime($event['start_date']));
    $cardStateClass = 'is-soon';
    $cardAction = 'View details';
} else {
    $cardState = $cardDaysLeft < 1 ? 'Last day' : number_format($cardDaysLeft) . ($cardDaysLeft == 1 ? ' day left' : ' days left');
    $cardStateClass = 'is-open';
    $cardAction = 'Vote now';
}

$cardImage = $event['banner'] ? 'uploads/events/' . $event['banner'] : 'https://picsum.photos/800/1000?random=' . $event['id'];
$cardCategories = (int) $event['category_count'];
$cardNominees = (int) $event['nominee_count'];
$cardVotes = (int) $event['total_votes'];
?>
<div class="rv" style="--d:<?php echo ($index % 4) * 70; ?>ms">
    <a class="ev" href="event-details?slug=<?php echo urlencode($event['slug']); ?>">
        <span class="ev-img">
            <img src="<?php echo $cardImage; ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" loading="lazy">
            <?php if (!empty($event['is_featured'])): ?>
                <span class="ev-star" title="Featured"><i class="bi bi-star-fill" aria-hidden="true"></i><span class="visually-hidden">Featured</span></span>
            <?php endif; ?>
        </span>
        <span class="ev-body">
            <span class="ev-state <?php echo $cardStateClass; ?>"><?php echo $cardState; ?></span>
            <span class="ev-title"><?php echo htmlspecialchars($event['title']); ?></span>
            <span class="ev-meta">
                <span><?php echo number_format($cardCategories) . ($cardCategories == 1 ? ' category' : ' categories'); ?></span>
                <span><?php echo number_format($cardNominees) . ($cardNominees == 1 ? ' nominee' : ' nominees'); ?></span>
                <?php if ($show_vote_counts && $cardVotes > 0): ?>
                    <span><?php echo number_format($cardVotes) . ($cardVotes == 1 ? ' vote' : ' votes'); ?></span>
                <?php endif; ?>
            </span>
            <span class="ev-action"><?php echo $cardAction; ?> <i class="bi bi-arrow-right"></i></span>
        </span>
    </a>
</div>
