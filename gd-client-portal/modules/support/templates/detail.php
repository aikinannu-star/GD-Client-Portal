<?php if (!defined('ABSPATH')) { exit; } ?>
<?php
$tickets = array();
if (class_exists('GDCP_Support_Repository') && function_exists('gd_client_portal_get_current_tenant_id')) {
    $repo = new GDCP_Support_Repository();
    $where = array('tenant_id=%d');
    $params = array(gd_client_portal_get_current_tenant_id());
    if (!function_exists('gd_client_portal_user_is_tenant_admin') || !gd_client_portal_user_is_tenant_admin()) { $where[]='user_id=%d'; $params[]=get_current_user_id(); }
    $tickets = $repo->list_tickets($where, $params, 20);
}
?>
<div class="gd-module-detail gd-support-detail">
    <div class="gd-module-summary-row">
        <div><p class="gd-module-eyebrow"><?php esc_html_e('Help desk', 'gd-client-portal'); ?></p><h2><?php esc_html_e('Support Centre', 'gd-client-portal'); ?></h2></div>
        <span class="gd-pill"><?php echo esc_html(sprintf(_n('%d ticket', '%d tickets', count($tickets), 'gd-client-portal'), count($tickets))); ?></span>
    </div>
    <?php if (empty($tickets)) : ?>
        <div class="gd-empty-state"><strong><?php esc_html_e('No support tickets', 'gd-client-portal'); ?></strong><p><?php esc_html_e('You have no support requests in this account yet.', 'gd-client-portal'); ?></p></div>
    <?php else : ?>
        <div class="gd-ticket-stack">
        <?php foreach ($tickets as $ticket) : ?>
            <article class="gd-ticket-item">
                <div class="gd-ticket-head"><strong><?php echo esc_html('#'.absint($ticket->id).' — '.($ticket->subject ?? __('Support request', 'gd-client-portal'))); ?></strong><span><?php echo esc_html(ucwords(str_replace('_',' ',(string)($ticket->status ?? 'open')))); ?></span></div>
                <p><?php echo esc_html($ticket->priority ?? __('Normal', 'gd-client-portal')); ?> · <?php echo esc_html($ticket->updated_at ?? ''); ?></p>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
