<?php
/**
 * GD Client Portal v2.7 - Advanced Client Communication Hub.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_communication_messages($limit=40) {
    $limit=max(1,min(100,absint($limit)));
    if(gd_client_portal_is_platform_admin()) return gdcp_message_service()->list_for_tenant(0,$limit);
    $tenant=absint(gd_client_portal_get_current_tenant_id()); if(!$tenant)return array();
    return gdcp_message_service()->list_for_tenant($tenant,$limit);
}

function gd_client_portal_communication_projects() {
    return function_exists('gd_client_portal_get_visible_projects') ? gd_client_portal_get_visible_projects() : [];
}
function gd_client_portal_render_communication_hub($atts=[],$admin=false) {
    if (!gd_client_portal_verify_request() || !gd_client_portal_verify_tenant_access()) return gd_client_portal_render_access_gate();
    wp_enqueue_style('gd-client-portal-communications'); wp_enqueue_script('gd-client-portal-communications');
    $notifications=function_exists('gd_client_portal_get_notifications')?gd_client_portal_get_notifications(gd_client_portal_cached_current_user_id(),30):[];
    $messages=gd_client_portal_communication_messages(40); $audit=function_exists('gd_client_portal_get_audit_events')?gd_client_portal_get_audit_events(['limit'=>30]):[];
    $projects=gd_client_portal_communication_projects(); $nonce=wp_create_nonce('gd_client_portal_project_message');
    ob_start(); ?>
    <div class="gd-communication-hub" data-message-nonce="<?php echo esc_attr($nonce); ?>">
      <header class="gd-communication-hero"><div><span class="gd-communication-eyebrow">GD CLIENT PORTAL · V2.7</span><h2><?php esc_html_e('Communication Hub','gd-client-portal'); ?></h2><p><?php esc_html_e('Messages, notifications and project activity brought together in one secure workspace.','gd-client-portal'); ?></p></div><div class="gd-communication-stats"><span><strong><?php echo count($notifications); ?></strong><?php esc_html_e('updates','gd-client-portal'); ?></span><span><strong><?php echo count($messages); ?></strong><?php esc_html_e('messages','gd-client-portal'); ?></span></div></header>
      <nav class="gd-communication-tabs" aria-label="Communication sections"><button class="is-active" data-tab="inbox">Inbox</button><button data-tab="messages">Project messages</button><button data-tab="activity">Activity</button></nav>
      <section class="gd-communication-tab is-active" data-panel="inbox"><div class="gd-communication-list">
      <?php if(!$notifications): ?><div class="gd-communication-empty"><strong><?php esc_html_e('No new updates','gd-client-portal'); ?></strong><span><?php esc_html_e('You are all caught up.','gd-client-portal'); ?></span></div><?php endif; ?>
      <?php foreach($notifications as $n): $p=$n->project_id?gd_client_portal_cached_project($n->project_id):null; ?><article class="gd-communication-card <?php echo !$n->is_read?'is-unread':''; ?>"><div class="gd-communication-icon">●</div><div><div class="gd-communication-meta"><strong><?php echo esc_html($n->title); ?></strong><time><?php echo esc_html($n->created_at); ?></time></div><p><?php echo nl2br(esc_html($n->body)); ?></p><?php if($p): ?><a href="<?php echo esc_url(add_query_arg('project_id',$p->id,gd_client_portal_get_dashboard_url())); ?>">Open project →</a><?php endif; ?></div></article><?php endforeach; ?>
      </div></section>
      <section class="gd-communication-tab" data-panel="messages"><div class="gd-communication-message-grid"><div class="gd-communication-list">
      <?php if(!$messages): ?><div class="gd-communication-empty"><strong><?php esc_html_e('No project messages yet','gd-client-portal'); ?></strong></div><?php endif; ?>
      <?php foreach($messages as $m): $u=gd_client_portal_cached_user($m->user_id); ?><article class="gd-communication-card"><div class="gd-communication-avatar"><?php echo esc_html(strtoupper(substr($u?$u->display_name:'U',0,1))); ?></div><div><div class="gd-communication-meta"><strong><?php echo esc_html($u?$u->display_name:__('Portal user','gd-client-portal')); ?></strong><time><?php echo esc_html($m->created_at); ?></time></div><span class="gd-communication-project"><?php echo esc_html($m->project_title); ?></span><p><?php echo nl2br(esc_html($m->message)); ?></p><?php if($m->file_url): ?><a href="<?php echo esc_url(gd_client_portal_private_message_file_url($m->id)); ?>" target="_blank" rel="noopener">Attachment →</a><?php endif; ?></div></article><?php endforeach; ?></div>
      <aside class="gd-communication-compose"><h3><?php esc_html_e('Send a project message','gd-client-portal'); ?></h3><p><?php esc_html_e('Choose an accessible project and send a secure message to its conversation.','gd-client-portal'); ?></p><select class="gd-communication-project"><?php foreach($projects as $p): ?><option value="<?php echo intval($p->id); ?>"><?php echo esc_html('#'.$p->id.' · '.$p->title); ?></option><?php endforeach; ?></select><textarea rows="6" placeholder="Write your message…"></textarea><button type="button" class="gd-communication-send">Send message</button><span class="gd-communication-status" aria-live="polite"></span></aside></div></section>
      <section class="gd-communication-tab" data-panel="activity"><div class="gd-communication-list">
      <?php foreach($audit as $e): $u=$e->actor_id?gd_client_portal_cached_user($e->actor_id):null; ?><article class="gd-communication-card"><div class="gd-communication-icon">↗</div><div><div class="gd-communication-meta"><strong><?php echo esc_html($e->summary); ?></strong><time><?php echo esc_html($e->created_at); ?></time></div><p><?php echo esc_html($e->details); ?></p><?php if($u): ?><small><?php echo esc_html__('By','gd-client-portal').' '.esc_html($u->display_name); ?></small><?php endif; ?></div></article><?php endforeach; ?>
      </div></section>
    </div>
    <?php return ob_get_clean();
}
function gd_client_portal_register_communication_assets(){
    wp_register_style('gd-client-portal-communications',GD_CLIENT_PORTAL_URL.'assets/communications.css',array('gd-client-portal'),GD_CLIENT_PORTAL_VERSION);
    wp_register_script('gd-client-portal-communications',GD_CLIENT_PORTAL_URL.'assets/communications.js',array('jquery'),GD_CLIENT_PORTAL_VERSION,true);
}
add_action('wp_enqueue_scripts','gd_client_portal_register_communication_assets'); add_action('admin_enqueue_scripts','gd_client_portal_register_communication_assets');
add_shortcode('gd_communication_hub','gd_client_portal_render_communication_hub');
add_action('init',function(){if(function_exists('gd_client_portal_register_dashboard_view'))gd_client_portal_register_dashboard_view('communication','gd_client_portal_render_communication_hub');});
function gd_client_portal_render_communication_admin(){if(!gd_client_portal_user_can_access_admin())wp_die(esc_html__('You do not have permission to access this page.','gd-client-portal'));echo '<div class="wrap gd-communication-admin-wrap">'.gd_client_portal_render_communication_hub([],true).'</div>';}
add_action('admin_menu',function(){add_submenu_page('gd-client-portal',__('Communication Hub','gd-client-portal'),__('Communication Hub','gd-client-portal'),'gd_client_portal_access_admin','gd-client-portal-communications','gd_client_portal_render_communication_admin');},20);
