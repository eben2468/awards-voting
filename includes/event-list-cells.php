<?php
/**
 * The Type & Status, Setup, Activity and End Date cells of one event list row.
 * The including page sets $event, from a query that has eventListTicketColumns().
 */
?>
<td>
    <div>
        <div class="mb-1"><?php echo eventTypeBadge($event); ?></div>
        <span class="badge bg-<?php echo match($event['status']) {
            'active' => 'success',
            'completed' => 'secondary',
            'draft' => 'warning',
            default => 'danger'
        }; ?>"><?php echo e(ucfirst($event['status'])); ?></span>
    </div>
</td>
<td>
    <div>
        <?php if (eventHasVoting($event)): ?>
            <div class="text-nowrap"><?php echo eventCount($event['category_count'], 'category', 'categories'); ?></div>
            <div class="text-nowrap"><?php echo eventCount($event['nominee_count'], 'nominee'); ?></div>
        <?php endif; ?>
        <?php if (eventHasTickets($event)): ?>
            <div class="text-nowrap"><?php echo eventCount($event['ticket_type_count'], 'ticket type'); ?></div>
        <?php endif; ?>
    </div>
</td>
<td>
    <div>
        <?php if (eventHasVoting($event)): ?>
            <div class="text-nowrap"><strong class="text-primary"><?php echo eventCount($event['total_votes'], 'vote'); ?></strong></div>
        <?php endif; ?>
        <?php if (eventHasTickets($event)): ?>
            <div class="text-nowrap"><strong class="text-success"><?php echo eventCount($event['tickets_sold'], 'ticket'); ?> sold</strong></div>
            <small class="d-block text-muted text-nowrap">GHS <?php echo number_format($event['ticket_revenue'], 2); ?></small>
        <?php endif; ?>
    </div>
</td>
<td class="text-nowrap"><?php echo date('M d, Y', strtotime($event['end_date'])); ?></td>
