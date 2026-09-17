<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="gd-module-detail gd-meetings-detail">
    <div class="gd-module-summary-row">
        <div>
            <p class="gd-module-eyebrow"><?php echo esc_html__('Planning', 'gd-client-portal'); ?></p>
            <h2><?php echo esc_html__('Meetings', 'gd-client-portal'); ?></h2>
        </div>
        <span class="gd-pill"><?php echo esc_html__('2 this week', 'gd-client-portal'); ?></span>
    </div>

    <div class="gd-meeting-stack">
        <article class="gd-meeting-item">
            <div class="gd-meeting-head">
                <strong><?php echo esc_html__('Strategy review', 'gd-client-portal'); ?></strong>
                <span><?php echo esc_html__('Thu · 11:00', 'gd-client-portal'); ?></span>
            </div>
            <p><?php echo esc_html__('Review the latest milestones, open items, and priorities for the next sprint.', 'gd-client-portal'); ?></p>
        </article>
        <article class="gd-meeting-item">
            <div class="gd-meeting-head">
                <strong><?php echo esc_html__('Client check-in', 'gd-client-portal'); ?></strong>
                <span><?php echo esc_html__('Fri · 15:30', 'gd-client-portal'); ?></span>
            </div>
            <p><?php echo esc_html__('Confirm visual direction, discuss outstanding questions, and confirm launch readiness.', 'gd-client-portal'); ?></p>
        </article>
    </div>
</div>
