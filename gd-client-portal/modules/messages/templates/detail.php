<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="gd-module-detail gd-messages-detail">
    <div class="gd-module-summary-row">
        <div>
            <p class="gd-module-eyebrow"><?php echo esc_html__('Inbox', 'gd-client-portal'); ?></p>
            <h2><?php echo esc_html__('Messages', 'gd-client-portal'); ?></h2>
        </div>
        <span class="gd-pill"><?php echo esc_html__('3 unread', 'gd-client-portal'); ?></span>
    </div>

    <div class="gd-message-list">
        <article class="gd-message-item">
            <div class="gd-message-head">
                <strong><?php echo esc_html__('Alicia — Project Manager', 'gd-client-portal'); ?></strong>
                <span><?php echo esc_html__('Today, 10:42 AM', 'gd-client-portal'); ?></span>
            </div>
            <p><?php echo esc_html__('We have finalised the review notes and are preparing the next milestone pack.', 'gd-client-portal'); ?></p>
        </article>
        <article class="gd-message-item">
            <div class="gd-message-head">
                <strong><?php echo esc_html__('James — Design Lead', 'gd-client-portal'); ?></strong>
                <span><?php echo esc_html__('Yesterday', 'gd-client-portal'); ?></span>
            </div>
            <p><?php echo esc_html__('The updated homepage direction is ready for sign-off and can be shared in the next briefing.', 'gd-client-portal'); ?></p>
        </article>
    </div>
</div>
