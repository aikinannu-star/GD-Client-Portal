<?php if (!defined('ABSPATH')) { exit; } ?>
<?php
$events = array();
if (function_exists('gdcp_calendar_service')) {
    $start = current_time('mysql');
    $end = gmdate('Y-m-d H:i:s', current_time('timestamp') + 30 * DAY_IN_SECONDS);
    $events = gdcp_calendar_service()->custom_events($start, $end);
}
?>
<div class="gd-module-detail gd-meetings-detail">
    <div class="gd-module-summary-row">
        <div><p class="gd-module-eyebrow"><?php esc_html_e('Planning', 'gd-client-portal'); ?></p><h2><?php esc_html_e('Meetings', 'gd-client-portal'); ?></h2></div>
        <span class="gd-pill"><?php echo esc_html(sprintf(_n('%d upcoming', '%d upcoming', count($events), 'gd-client-portal'), count($events))); ?></span>
    </div>
    <?php if (empty($events)) : ?>
        <div class="gd-empty-state"><strong><?php esc_html_e('No upcoming meetings', 'gd-client-portal'); ?></strong><p><?php esc_html_e('Scheduled project milestones and meetings will appear here.', 'gd-client-portal'); ?></p></div>
    <?php else : ?>
        <div class="gd-meeting-stack">
        <?php foreach ($events as $event) : ?>
            <article class="gd-meeting-item">
                <div class="gd-meeting-head"><strong><?php echo esc_html($event->title ?? __('Scheduled meeting', 'gd-client-portal')); ?></strong><span><?php echo esc_html($event->start_at ?? ''); ?></span></div>
                <?php if (!empty($event->description)) : ?><p><?php echo esc_html($event->description); ?></p><?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
