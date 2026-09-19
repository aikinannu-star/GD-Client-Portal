<?php if (!defined('ABSPATH')) { exit; } ?>
<?php
$gd_messages = function_exists('gdcp_message_repository')
    ? gdcp_message_repository()->list_for_user(get_current_user_id(), gd_client_portal_get_current_tenant_id(), gd_client_portal_user_is_tenant_admin(), 20)
    : array();
$gd_messages = is_array($gd_messages) ? $gd_messages : array();
?>
<div class="gd-module-detail gd-messages-detail">
    <div class="gd-module-summary-row">
        <div>
            <p class="gd-module-eyebrow"><?php echo esc_html__('Inbox', 'gd-client-portal'); ?></p>
            <h2><?php echo esc_html__('Messages', 'gd-client-portal'); ?></h2>
        </div>
        <span class="gd-pill"><?php echo esc_html(sprintf(_n('%d recent', '%d recent', count($gd_messages), 'gd-client-portal'), count($gd_messages))); ?></span>
    </div>

    <?php if (empty($gd_messages)) : ?>
        <div class="gd-module-empty-state"><p><?php echo esc_html__('No project messages yet. New client and team communication will appear here.', 'gd-client-portal'); ?></p></div>
    <?php else : ?>
        <div class="gd-message-list">
            <?php foreach (array_slice($gd_messages, 0, 5) as $gd_message) :
                $author = !empty($gd_message->user_id) ? get_userdata(absint($gd_message->user_id)) : null;
                $body = isset($gd_message->body) ? wp_trim_words(wp_strip_all_tags($gd_message->body), 28) : '';
                $created = !empty($gd_message->created_at) ? strtotime($gd_message->created_at) : 0;
            ?>
                <article class="gd-message-item">
                    <div class="gd-message-head">
                        <strong><?php echo esc_html($author ? $author->display_name : __('Project team', 'gd-client-portal')); ?></strong>
                        <span><?php echo esc_html($created ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $created) : __('Recently', 'gd-client-portal')); ?></span>
                    </div>
                    <?php if (!empty($gd_message->project_title)) : ?><small><?php echo esc_html($gd_message->project_title); ?></small><?php endif; ?>
                    <p><?php echo esc_html($body); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
