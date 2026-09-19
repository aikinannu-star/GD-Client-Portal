<?php if (!defined('ABSPATH')) { exit; } ?>
<?php
$messages = array();
if (function_exists('gdcp_message_service') && function_exists('gd_client_portal_get_current_tenant_id')) {
    $messages = gdcp_message_service()->list_for_user(get_current_user_id(), gd_client_portal_get_current_tenant_id(), function_exists('gd_client_portal_user_is_tenant_admin') && gd_client_portal_user_is_tenant_admin(), 20);
}
?>
<div class="gd-module-detail gd-messages-detail">
    <div class="gd-module-summary-row">
        <div>
            <p class="gd-module-eyebrow"><?php esc_html_e('Inbox', 'gd-client-portal'); ?></p>
            <h2><?php esc_html_e('Messages', 'gd-client-portal'); ?></h2>
        </div>
        <span class="gd-pill"><?php echo esc_html(sprintf(_n('%d message', '%d messages', count($messages), 'gd-client-portal'), count($messages))); ?></span>
    </div>
    <?php if (empty($messages)) : ?>
        <div class="gd-empty-state"><strong><?php esc_html_e('No messages yet', 'gd-client-portal'); ?></strong><p><?php esc_html_e('Updates from your service team will appear here.', 'gd-client-portal'); ?></p></div>
    <?php else : ?>
        <div class="gd-message-list">
            <?php foreach ($messages as $message) :
                $author = get_userdata(absint($message->user_id ?? 0));
                $date = !empty($message->created_at) ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $message->created_at) : '';
            ?>
                <article class="gd-message-item">
                    <div class="gd-message-head">
                        <strong><?php echo esc_html($author ? $author->display_name : __('Service team', 'gd-client-portal')); ?></strong>
                        <span><?php echo esc_html($date); ?></span>
                    </div>
                    <?php if (!empty($message->project_title)) : ?><p class="gd-project-tag"><?php echo esc_html($message->project_title); ?></p><?php endif; ?>
                    <p><?php echo esc_html($message->message); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
