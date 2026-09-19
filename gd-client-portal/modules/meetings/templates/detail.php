<?php if (!defined('ABSPATH')) { exit; } ?>
<?php
$gd_meeting_start = current_time('mysql');
$gd_meeting_end = date('Y-m-d H:i:s', strtotime('+30 days', current_time('timestamp')));
$gd_events = function_exists('gd_client_portal_calendar_events') ? gd_client_portal_calendar_events($gd_meeting_start, $gd_meeting_end, 0) : array();
$gd_events = is_array($gd_events) ? $gd_events : array();
usort($gd_events, function ($a, $b) {
    $a_time = strtotime(is_array($a) ? ($a['start_at'] ?? '') : ($a->start_at ?? ''));
    $b_time = strtotime(is_array($b) ? ($b['start_at'] ?? '') : ($b->start_at ?? ''));
    return $a_time <=> $b_time;
});
?>
<div class="gd-module-detail gd-meetings-detail">
    <div class="gd-module-summary-row">
        <div>
            <p class="gd-module-eyebrow"><?php echo esc_html__('Planning', 'gd-client-portal'); ?></p>
            <h2><?php echo esc_html__('Meetings & Schedule', 'gd-client-portal'); ?></h2>
        </div>
        <span class="gd-pill"><?php echo esc_html(sprintf(_n('%d upcoming', '%d upcoming', count($gd_events), 'gd-client-portal'), count($gd_events))); ?></span>
    </div>

    <?php if (empty($gd_events)) : ?>
        <div class="gd-module-empty-state"><p><?php echo esc_html__('No meetings or project milestones are scheduled in the next 30 days.', 'gd-client-portal'); ?></p></div>
    <?php else : ?>
        <div class="gd-meeting-stack">
            <?php foreach (array_slice($gd_events, 0, 6) as $gd_event) :
                $gd_title = is_array($gd_event) ? ($gd_event['title'] ?? '') : ($gd_event->title ?? '');
                $gd_start = is_array($gd_event) ? ($gd_event['start_at'] ?? '') : ($gd_event->start_at ?? '');
                $gd_project_id = is_array($gd_event) ? absint($gd_event['project_id'] ?? 0) : absint($gd_event->project_id ?? 0);
                $gd_project = $gd_project_id ? gd_client_portal_get_project_by_id($gd_project_id) : null;
            ?>
                <article class="gd-meeting-item">
                    <div class="gd-meeting-head">
                        <strong><?php echo esc_html($gd_title ?: __('Scheduled event', 'gd-client-portal')); ?></strong>
                        <span><?php echo esc_html($gd_start ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($gd_start)) : __('Time to be confirmed', 'gd-client-portal')); ?></span>
                    </div>
                    <?php if ($gd_project) : ?><p><?php echo esc_html($gd_project->title); ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
