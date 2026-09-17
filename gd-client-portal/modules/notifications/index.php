<?php
/**
 * Notifications center for project and portal events.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_notifications_table() { global $wpdb; return $wpdb->prefix . 'gd_client_portal_notifications'; }

function gd_client_portal_notifications_activate() {
    global $wpdb;
    $table = gd_client_portal_notifications_table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        tenant_id bigint(20) unsigned NOT NULL DEFAULT 0,
        project_id bigint(20) unsigned NOT NULL DEFAULT 0,
        type varchar(50) NOT NULL DEFAULT 'general',
        title varchar(255) NOT NULL,
        body text NULL,
        is_read tinyint(1) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        event_key varchar(100) NULL,
        PRIMARY KEY (id), KEY user_id (user_id), KEY tenant_id (tenant_id), KEY project_id (project_id), KEY is_read (is_read), UNIQUE KEY user_event_key (user_id,event_key)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
add_action('init', 'gd_client_portal_notifications_activate', 5);

function gd_client_portal_create_notification($user_id, $title, $body = '', $type = 'general', $project_id = 0, $tenant_id = 0, $event_key = '') {
    return function_exists('gdcp_notification_service') ? gdcp_notification_service()->create($user_id,$title,$body,$type,$project_id,$tenant_id,$event_key) : false;
}

function gd_client_portal_notification_recipients($project, $exclude_user_id = 0) {
    $ids = array();
    if ($project && !empty($project->user_id)) $ids[] = absint($project->user_id);
    if ($project && !empty($project->tenant_id)) {
        $users = get_users(array('number'=>-1,'fields'=>'ID','meta_key'=>'gd_client_portal_tenant_id','meta_value'=>absint($project->tenant_id)));
        foreach ($users as $uid) {
            $u = get_userdata($uid);
            if ($u && !in_array('administrator', (array)$u->roles, true) && user_can($uid, 'gd_client_portal_tenant_admin')) $ids[] = absint($uid);
        }
    }
    $ids = array_unique(array_filter(array_map('absint',$ids)));
    if ($exclude_user_id) $ids = array_diff($ids, array(absint($exclude_user_id)));
    return $ids;
}

function gd_client_portal_notify_project($project, $title, $body, $type = 'project', $exclude_user_id = 0) {
    if (!$project) return;
    foreach (gd_client_portal_notification_recipients($project, $exclude_user_id) as $uid) {
        gd_client_portal_create_notification($uid, $title, $body, $type, $project->id, $project->tenant_id);
    }
}

function gd_client_portal_get_notifications($user_id = 0, $limit = 50) { return function_exists('gdcp_notification_service') ? gdcp_notification_service()->for_user($user_id,$limit) : array(); }
function gd_client_portal_get_unread_notification_count($user_id = 0) { return function_exists('gdcp_notification_service') ? gdcp_notification_service()->unread_count($user_id) : 0; }

function gd_client_portal_mark_notification_read_ajax() {
    if (!gd_client_portal_verify_request() || !gd_client_portal_verify_nonce_request('gd_client_portal_notifications')) wp_send_json_error(__('Invalid request.', 'gd-client-portal'));
    $id = absint($_POST['notification_id'] ?? 0);
    $ok = function_exists('gdcp_notification_service') ? gdcp_notification_service()->mark_read($id,get_current_user_id()) : false;
    wp_send_json_success(array('unread'=>gd_client_portal_get_unread_notification_count(),'changed'=>$ok));
}
function gd_client_portal_mark_all_notifications_read_ajax() {
    if (!gd_client_portal_verify_request() || !gd_client_portal_verify_nonce_request('gd_client_portal_notifications')) wp_send_json_error(__('Invalid request.', 'gd-client-portal'));
    $ok = function_exists('gdcp_notification_service') ? gdcp_notification_service()->mark_all_read(get_current_user_id()) : false;
    wp_send_json_success(array('unread'=>0,'changed'=>$ok));
}
add_action('wp_ajax_gd_client_portal_mark_notification_read','gd_client_portal_mark_notification_read_ajax');
add_action('wp_ajax_gd_client_portal_mark_all_notifications_read','gd_client_portal_mark_all_notifications_read_ajax');

function gd_client_portal_render_notification($atts = array()) {
    if (!gd_client_portal_verify_request() || !gd_client_portal_verify_tenant_access()) return gd_client_portal_render_access_gate();
    wp_enqueue_style('gd-client-portal-notifications');
    wp_enqueue_script('gd-client-portal-notifications');
    $items = gd_client_portal_get_notifications(); $nonce = wp_create_nonce('gd_client_portal_notifications');
    ob_start(); ?>
    <div class="gd-notifications-center" data-nonce="<?php echo esc_attr($nonce); ?>">
      <div class="gd-notifications-head"><div><span class="gd-notifications-eyebrow">Updates</span><h2><?php esc_html_e('Notifications','gd-client-portal'); ?></h2><p><?php esc_html_e('Important project and workspace activity, all in one place.','gd-client-portal'); ?></p></div><button class="gd-notifications-mark-all" type="button"><?php esc_html_e('Mark all as read','gd-client-portal'); ?></button></div>
      <div class="gd-notifications-list">
      <?php if (!$items): ?><div class="gd-notification-empty"><strong><?php esc_html_e('You’re all caught up.','gd-client-portal'); ?></strong><span><?php esc_html_e('New project updates and actions will appear here.','gd-client-portal'); ?></span></div><?php endif; ?>
      <?php foreach ($items as $item): $project = $item->project_id ? gd_client_portal_get_project_by_id($item->project_id) : null; ?>
        <article class="gd-notification-item <?php echo $item->is_read ? 'is-read' : 'is-unread'; ?>" data-id="<?php echo intval($item->id); ?>">
          <div class="gd-notification-dot"></div><div class="gd-notification-content"><div class="gd-notification-meta"><strong><?php echo esc_html($item->title); ?></strong><time><?php echo esc_html($item->created_at); ?></time></div><p><?php echo nl2br(esc_html($item->body)); ?></p><?php if ($project): ?><a href="<?php echo esc_url(add_query_arg('project_id', absint($project->id), gd_client_portal_get_dashboard_url())); ?>"><?php esc_html_e('Open project','gd-client-portal'); ?> →</a><?php endif; ?></div><?php if (!$item->is_read): ?><button class="gd-notification-read" type="button"><?php esc_html_e('Read','gd-client-portal'); ?></button><?php endif; ?>
        </article>
      <?php endforeach; ?></div>
    </div>
    <?php return ob_get_clean();
}

function gd_client_portal_register_notifications_assets() {
    wp_register_style('gd-client-portal-notifications', GD_CLIENT_PORTAL_URL.'modules/notifications/assets/notifications.css', array('gd-client-portal'), GD_CLIENT_PORTAL_VERSION);
    wp_register_script('gd-client-portal-notifications', GD_CLIENT_PORTAL_URL.'modules/notifications/assets/notifications.js', array('jquery'), GD_CLIENT_PORTAL_VERSION, true);
}
add_action('wp_enqueue_scripts','gd_client_portal_register_notifications_assets');
add_shortcode('gd_client_portal_notification','gd_client_portal_render_notification');

function gd_client_portal_register_notifications_dashboard_view() { if (function_exists('gd_client_portal_register_dashboard_view')) gd_client_portal_register_dashboard_view('notifications','gd_client_portal_render_notification'); }
add_action('init','gd_client_portal_register_notifications_dashboard_view');

/* Event hooks */
function gd_client_portal_notifications_stage_changed($project_id, $new_stage, $progress = null) {
    $project = gd_client_portal_get_project_by_id($project_id); if (!$project) return;
    gd_client_portal_notify_project($project, sprintf(__('Project updated: %s','gd-client-portal'), ucwords(str_replace('_',' ',$new_stage))), sprintf(__('Your project is now at the “%s” stage.','gd-client-portal'), ucwords(str_replace('_',' ',$new_stage))), 'stage');
}
add_action('gd_client_portal_project_stage_changed','gd_client_portal_notifications_stage_changed',10,3);

function gd_client_portal_notifications_requirements($project) { if ($project) gd_client_portal_notify_project($project, __('New project requirements submitted','gd-client-portal'), sprintf(__('Requirements have been submitted for %s and are ready for review.','gd-client-portal'), $project->title), 'requirements', $project->user_id); }
add_action('gd_client_portal_requirements_submitted','gd_client_portal_notifications_requirements');

function gd_client_portal_notifications_message($project_id, $author_id) { $p=gd_client_portal_get_project_by_id($project_id); if ($p) gd_client_portal_notify_project($p, __('New project message','gd-client-portal'), sprintf(__('A new message was posted on %s.','gd-client-portal'), $p->title), 'message', $author_id); }
add_action('gd_client_portal_project_message_added','gd_client_portal_notifications_message',10,2);

function gd_client_portal_notifications_approval_requested($project_id, $version) { $p=gd_client_portal_get_project_by_id($project_id); if ($p) gd_client_portal_create_notification($p->user_id, sprintf(__('Approval requested: Version %d','gd-client-portal'),$version), sprintf(__('%s is ready for your review.','gd-client-portal'),$p->title), 'approval', $p->id, $p->tenant_id); }
add_action('gd_client_portal_approval_requested','gd_client_portal_notifications_approval_requested',10,2);
function gd_client_portal_notifications_decision($project_id,$decision,$comment='') { $p=gd_client_portal_get_project_by_id($project_id); if ($p) gd_client_portal_notify_project($p, $decision==='approved'?__('Project approved','gd-client-portal'):__('Revision requested','gd-client-portal'), $comment ?: ($decision==='approved'?__('The client approved the current version.','gd-client-portal'):__('The client requested revisions.','gd-client-portal')), 'approval', $p->user_id); }
add_action('gd_client_portal_approval_decided','gd_client_portal_notifications_decision',10,3);

function gd_client_portal_notifications_project_created($project) {
    if (!$project) return;
    gd_client_portal_notify_project($project, __('New service project created','gd-client-portal'), sprintf(__('Your service purchase has been added as %s. Please submit your requirements to begin the workflow.','gd-client-portal'), $project->title), 'project_created');
}
add_action('gd_client_portal_project_created','gd_client_portal_notifications_project_created');

function gd_client_portal_notifications_project_claimed($project_id, $user_id) {
    $p = gd_client_portal_get_project_by_id($project_id);
    if ($p && absint($user_id) === absint($p->user_id)) {
        gd_client_portal_create_notification($user_id, __('Project linked to your account','gd-client-portal'), sprintf(__('Your previous service purchase is now available in your Client Portal: %s.','gd-client-portal'), $p->title), 'project_claimed', $p->id, $p->tenant_id);
    }
}
add_action('gd_client_portal_project_claimed','gd_client_portal_notifications_project_claimed',10,2);
