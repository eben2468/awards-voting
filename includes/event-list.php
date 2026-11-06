<?php
/**
 * What the admin and organizer event lists show for each kind of event. An awards
 * event is measured in nominees and votes; a ticketed event in ticket types and sales.
 */

// Added to an event list query, which must call the events table e
function eventListTicketColumns() {
    $sold = "FROM transactions t
             WHERE t.event_id = e.id AND t.status = 'success'
               AND (t.reference LIKE 'TKT-%' OR t.ticket_category_id IS NOT NULL)";

    return "(SELECT COUNT(*) FROM ticket_categories tc WHERE tc.event_id = e.id) AS ticket_type_count,
            (SELECT COALESCE(SUM(t.quantity), 0) $sold) AS tickets_sold,
            (SELECT COALESCE(SUM(t.amount), 0) $sold) AS ticket_revenue";
}

function eventHasVoting(array $event) {
    return ($event['event_type'] ?? 'voting') !== 'ticketing';
}

function eventHasTickets(array $event) {
    return in_array($event['event_type'] ?? 'voting', ['ticketing', 'both'], true);
}

function eventTypeBadge(array $event) {
    $types = [
        'voting'    => ['Awards Voting', 'bi-trophy', 'primary', 'primary'],
        'ticketing' => ['Ticket Sales', 'bi-ticket-perforated', 'success', 'success'],
        'both'      => ['Voting + Tickets', 'bi-stars', 'warning', 'dark'],
    ];
    [$label, $icon, $colour, $text] = $types[$event['event_type'] ?? 'voting'] ?? $types['voting'];

    return '<span class="badge text-nowrap bg-' . $colour . ' bg-opacity-10 text-' . $text . '">'
        . '<i class="bi ' . $icon . ' me-1"></i>' . $label . '</span>';
}

function eventCount($number, $singular, $plural = null) {
    return number_format($number) . ' ' . ($number == 1 ? $singular : ($plural ?? $singular . 's'));
}

function eventDeleteWarning(array $event) {
    $lost = [];
    if (eventHasVoting($event)) {
        $lost[] = 'categories, nominees and votes';
    }
    if (eventHasTickets($event)) {
        $lost[] = 'ticket types and tickets';
    }
    return 'Are you sure you want to delete this event? This will also delete all its ' . implode(', and ', $lost) . '.';
}
