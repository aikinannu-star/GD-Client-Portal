<?php
/**
 * GD Client Portal v2.6 - Activity & Audit Center.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_audit_table() { global $wpdb; return $wpdb->prefix . 'gd_portal_audit_log'; }
function gd_client_portal_activate_audit_table() {
    global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table=gd_client_portal_audit_table(); $charset=$wpdb->get_charset_collate();
    $sql="CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        tenant_id bigint(20) unsigned NOT NULL DEFAULT 0,
        project_id bigint(20) unsigned NOT NULL DEFAULT 0,
        actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
        event_type varchar(60) NOT NULL,
        object_type varchar(40) NOT NULL DEFAULT 'project',
        object_id bigint(20) unsigned NOT NULL DEFAULT 0,
        summary varchar(255) NOT NULL,
        details text NULL,
        metadata longtext NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id), KEY tenant_id(tenant_id), KEY project_id(project_id), KEY actor_id(actor_id), KEY event_type(event_type), KEY object_type(object_type), KEY created_at(created_at)
    ) {$charset};";
    dbDelta($sql);
}
// Schema lifecycle is managed centrally by GDCP_Migration_Manager (v6.8+).

function gd_client_portal_audit_actor_id($actor_id=0) { return absint($actor_id ?: gd_client_portal_cached_current_user_id()); }
function gd_client_portal_audit_log($event_type,$summary,$project_id=0,$tenant_id=0,$details='',$object_type='project',$object_id=0,$metadata=array(),$actor_id=0) {
    global $wpdb;
    $event_type=sanitize_key($event_type); if(!$event_type || !$summary) return false;
    $project_id=absint($project_id); $tenant_id=absint($tenant_id);
    if(!$tenant_id && $project_id && function_exists('gd_client_portal_get_project_by_id')) { $p=gd_client_portal_get_project_by_id($project_id); if($p) $tenant_id=absint($p->tenant_id); }
    $data=array('tenant_id'=>$tenant_id,'project_id'=>$project_id,'actor_id'=>gd_client_portal_audit_actor_id($actor_id),'event_type'=>$event_type,'object_type'=>sanitize_key($object_type ?: 'project'),'object_id'=>absint($object_id),'summary'=>sanitize_text_field($summary),'details'=>sanitize_textarea_field($details),'metadata'=>!empty($metadata)?wp_json_encode($metadata):null,'created_at'=>current_time('mysql'));
    $ok=$wpdb->insert(gd_client_portal_audit_table(),$data,array('%d','%d','%d','%s','%s','%d','%s','%s','%s','%s'));
    return $ok ? absint($wpdb->insert_id) : false;
}

function gd_client_portal_audit_event($event_type,$project_id,$summary,$details='',$metadata=array()) {
    $p=function_exists('gd_client_portal_get_project_by_id')?gd_client_portal_get_project_by_id($project_id):false;
    return gd_client_portal_audit_log($event_type,$summary,$project_id,$p?$p->tenant_id:0,$details,'project',$project_id,$metadata);
}

function gd_client_portal_get_audit_events($args=array()) {
    global $wpdb; $defaults=array('limit'=>50,'offset'=>0,'project_id'=>0,'event_type'=>'','actor_id'=>0,'date_from'=>'','date_to'=>''); $args=wp_parse_args($args,$defaults);
    $params=array(); $where='WHERE 1=1';
    if(!gd_client_portal_is_platform_admin()) { $tenant=absint(gd_client_portal_get_current_tenant_id()); if(!$tenant) return array(); $where.=' AND a.tenant_id=%d'; $params[]=$tenant; }
    elseif(!empty($args['tenant_id'])) { $where.=' AND a.tenant_id=%d'; $params[]=absint($args['tenant_id']); }
    if(absint($args['project_id'])) { $where.=' AND a.project_id=%d'; $params[]=absint($args['project_id']); }
    if(!empty($args['event_type'])) { $where.=' AND a.event_type=%s'; $params[]=sanitize_key($args['event_type']); }
    if(absint($args['actor_id'])) { $where.=' AND a.actor_id=%d'; $params[]=absint($args['actor_id']); }
    if(!empty($args['date_from'])) { $where.=' AND a.created_at >= %s'; $params[]=sanitize_text_field($args['date_from']).' 00:00:00'; }
    if(!empty($args['date_to'])) { $where.=' AND a.created_at <= %s'; $params[]=sanitize_text_field($args['date_to']).' 23:59:59'; }
    $limit=max(1,min(200,absint($args['limit']))); $offset=max(0,absint($args['offset'])); $params[]=$limit; $params[]=$offset;
    $sql="SELECT a.* FROM ".gd_client_portal_audit_table()." a {$where} ORDER BY a.created_at DESC, a.id DESC LIMIT %d OFFSET %d";
    return $wpdb->get_results($wpdb->prepare($sql,$params));
}
function gd_client_portal_audit_event_types() { return array('project_created'=>'Project created','project_claimed'=>'Project claimed','stage_changed'=>'Stage changed','requirements_submitted'=>'Requirements submitted','message_added'=>'Message added','approval_requested'=>'Approval requested','approval_decided'=>'Approval decided','delivery_published'=>'Delivery published','delivery_finalized'=>'Delivery finalized','project_assigned'=>'Team assignment','milestone_created'=>'Milestone created','milestone_deleted'=>'Milestone deleted','sla_updated'=>'SLA updated','sla_escalation'=>'SLA escalation','client_reminder'=>'Client reminder','revision_started'=>'Revision started','service_request_created'=>'Service request created','service_request_updated'=>'Service request updated'); }

function gd_client_portal_audit_on_project_created($project) { if($project) gd_client_portal_audit_event('project_created',$project->id,sprintf(__('Project created: %s','gd-client-portal'),$project->title),__('A service project was created.','gd-client-portal'),array('service_type'=>$project->service_type,'order_id'=>$project->order_id)); }
function gd_client_portal_audit_on_project_claimed($project_id,$user_id) { $u=gd_client_portal_cached_user($user_id); gd_client_portal_audit_event('project_claimed',$project_id,sprintf(__('Project claimed by %s','gd-client-portal'),$u?$u->display_name:__('client','gd-client-portal')),'',array('user_id'=>absint($user_id))); }
function gd_client_portal_audit_on_stage_changed($project_id,$stage,$progress=0) { $p=gd_client_portal_get_project_by_id($project_id); gd_client_portal_audit_event('stage_changed',$project_id,sprintf(__('Stage changed to %s','gd-client-portal'),ucwords(str_replace('_',' ',$stage))),$p?sprintf(__('Project progress is now %d%%.','gd-client-portal'),absint($progress)):'',array('stage'=>$stage,'progress'=>absint($progress))); }
function gd_client_portal_audit_on_requirements($project) { if($project) gd_client_portal_audit_event('requirements_submitted',$project->id,__('Project requirements submitted','gd-client-portal')); }
function gd_client_portal_audit_on_message($project_id,$user_id) { $u=gd_client_portal_cached_user($user_id); gd_client_portal_audit_event('message_added',$project_id,sprintf(__('Message added by %s','gd-client-portal'),$u?$u->display_name:__('portal user','gd-client-portal')),'',array('user_id'=>absint($user_id))); }
function gd_client_portal_audit_on_approval_requested($project_id,$version) { gd_client_portal_audit_event('approval_requested',$project_id,sprintf(__('Version %s submitted for approval','gd-client-portal'),$version)); }
function gd_client_portal_audit_on_approval_decided($project_id,$decision,$comment='') { gd_client_portal_audit_event('approval_decided',$project_id,sprintf(__('Client approval decision: %s','gd-client-portal'),ucwords(str_replace('_',' ',$decision))),$comment?__('Client provided a decision comment.','gd-client-portal'):'',array('decision'=>sanitize_key($decision))); }
function gd_client_portal_audit_on_delivery_published($project_id,$version) { gd_client_portal_audit_event('delivery_published',$project_id,sprintf(__('Deliverable version %s published','gd-client-portal'),$version)); }
function gd_client_portal_audit_on_delivery_finalized($project_id,$version) { gd_client_portal_audit_event('delivery_finalized',$project_id,sprintf(__('Final delivery version %s completed','gd-client-portal'),$version)); }
function gd_client_portal_audit_on_assignment($project_id,$user_id,$role) { $u=gd_client_portal_cached_user($user_id); gd_client_portal_audit_event('project_assigned',$project_id,sprintf(__('Project assigned to %s as %s','gd-client-portal'),$u?$u->display_name:__('team member','gd-client-portal'),$role),'',array('user_id'=>absint($user_id),'role'=>sanitize_key($role))); }
function gd_client_portal_audit_on_milestone_created($project_id,$title,$event_id=0) { gd_client_portal_audit_event('milestone_created',$project_id,sprintf(__('Milestone scheduled: %s','gd-client-portal'),$title),'',array('event_id'=>absint($event_id))); }
function gd_client_portal_audit_on_milestone_deleted($project_id,$title,$event_id=0) { gd_client_portal_audit_event('milestone_deleted',$project_id,sprintf(__('Milestone removed: %s','gd-client-portal'),$title),'',array('event_id'=>absint($event_id))); }
function gd_client_portal_audit_on_sla_escalation($project_id) { gd_client_portal_audit_event('sla_escalation',$project_id,__('SLA escalation sent','gd-client-portal'),__('An overdue project triggered an escalation notification.','gd-client-portal')); }
function gd_client_portal_audit_on_client_reminder($project_id) { gd_client_portal_audit_event('client_reminder',$project_id,__('Client reminder sent','gd-client-portal')); }

add_action('gd_client_portal_project_created','gd_client_portal_audit_on_project_created',90);
add_action('gd_client_portal_project_claimed','gd_client_portal_audit_on_project_claimed',90,2);
add_action('gd_client_portal_project_stage_changed','gd_client_portal_audit_on_stage_changed',90,3);
add_action('gd_client_portal_requirements_submitted','gd_client_portal_audit_on_requirements',90,1);
add_action('gd_client_portal_project_message_added','gd_client_portal_audit_on_message',90,2);
add_action('gd_client_portal_approval_requested','gd_client_portal_audit_on_approval_requested',90,2);
add_action('gd_client_portal_approval_decided','gd_client_portal_audit_on_approval_decided',90,3);
add_action('gd_client_portal_delivery_published','gd_client_portal_audit_on_delivery_published',90,2);
add_action('gd_client_portal_delivery_finalized','gd_client_portal_audit_on_delivery_finalized',90,2);
add_action('gd_client_portal_project_assigned','gd_client_portal_audit_on_assignment',90,3);
add_action('gd_client_portal_project_milestone_created','gd_client_portal_audit_on_milestone_created',90,3);
add_action('gd_client_portal_project_milestone_deleted','gd_client_portal_audit_on_milestone_deleted',90,3);
add_action('gd_client_portal_sla_escalated','gd_client_portal_audit_on_sla_escalation',90,1);
add_action('gd_client_portal_client_reminder_sent','gd_client_portal_audit_on_client_reminder',90,1);

function gd_client_portal_render_audit_center($client_view=false) {
    if(!gd_client_portal_verify_request()) return gd_client_portal_render_access_gate(__('Activity & Audit Center','gd-client-portal'),__('Please sign in to view portal activity.','gd-client-portal'));
    if(!$client_view && !gd_client_portal_user_can_access_admin()) return gd_client_portal_render_access_gate(__('Activity & Audit Center','gd-client-portal'),__('You do not have access to the audit workspace.','gd-client-portal'));
    $project_id=isset($_GET['project_id'])?absint($_GET['project_id']):0; $event_type=isset($_GET['event_type'])?sanitize_key(wp_unslash($_GET['event_type'])):'';
    $events=gd_client_portal_get_audit_events(array('limit'=>100,'project_id'=>$project_id,'event_type'=>$event_type));
    if($client_view){ $events=array_values(array_filter($events,function($e) use($project_id){ $p=gd_client_portal_get_project_by_id($e->project_id); return $p && gd_client_portal_verify_project_access($p) && (!$project_id || $p->id===$project_id); })); }
    ob_start(); wp_enqueue_style('gd-client-portal-audit');
    echo '<div class="gd-audit-center"><div class="gd-audit-hero"><div><span class="gd-audit-eyebrow">GD CLIENT PORTAL · V2.6</span><h2>'.esc_html__('Activity & Audit Center','gd-client-portal').'</h2><p>'.esc_html__('A chronological record of project, team, workflow, SLA, approval and delivery activity.','gd-client-portal').'</p></div><span class="gd-audit-count">'.intval(count($events)).' ' . esc_html__('events','gd-client-portal').'</span></div>';
    if(!$client_view){ echo '<form class="gd-audit-filters" method="get"><input type="hidden" name="page" value="gd-client-portal-audit"><input type="number" min="1" name="project_id" placeholder="'.esc_attr__('Project ID','gd-client-portal').'" value="'.esc_attr($project_id).'"> <select name="event_type"><option value="">'.esc_html__('All activity types','gd-client-portal').'</option>'; foreach(gd_client_portal_audit_event_types() as $k=>$v) echo '<option value="'.esc_attr($k).'" '.selected($event_type,$k,false).'>'.esc_html($v).'</option>'; echo '</select> <button class="button button-primary">'.esc_html__('Filter activity','gd-client-portal').'</button> <a class="button" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-audit')).'">'.esc_html__('Reset','gd-client-portal').'</a></form>'; }
    echo '<div class="gd-audit-list">';
    if(!$events) echo '<div class="gd-audit-empty"><strong>'.esc_html__('No activity found.','gd-client-portal').'</strong><p>'.esc_html__('New project and workflow events will appear here automatically.','gd-client-portal').'</p></div>';
    foreach($events as $e){ $actor=$e->actor_id?gd_client_portal_cached_user($e->actor_id):false; $p=$e->project_id?gd_client_portal_get_project_by_id($e->project_id):false; $type=gd_client_portal_audit_event_types()[$e->event_type]??ucwords(str_replace('_',' ',$e->event_type)); echo '<article class="gd-audit-event"><span class="gd-audit-dot"></span><div class="gd-audit-main"><div class="gd-audit-top"><span class="gd-audit-type">'.esc_html($type).'</span><time>'.esc_html(wp_date(get_option('date_format').' '.get_option('time_format'),strtotime($e->created_at))).'</time></div><h3>'.esc_html($e->summary).'</h3>'; if($e->details) echo '<p>'.esc_html($e->details).'</p>'; echo '<div class="gd-audit-meta">'.($p?'<span>Project #'.intval($p->id).' · '.esc_html($p->title).'</span>':'').($actor?'<span>'.esc_html__('By','gd-client-portal').' '.esc_html($actor->display_name).'</span>':'').'</div></div></article>'; }
    echo '</div></div>'; return ob_get_clean();
}
add_shortcode('gd_activity_audit','gd_client_portal_render_audit_center');
add_action('init',function(){if(function_exists('gd_client_portal_register_dashboard_view')) gd_client_portal_register_dashboard_view('activity','gd_client_portal_render_audit_center');});
function gd_client_portal_register_audit_assets(){wp_register_style('gd-client-portal-audit',GD_CLIENT_PORTAL_URL.'assets/audit.css',array('gd-client-portal'),GD_CLIENT_PORTAL_VERSION);}
add_action('wp_enqueue_scripts','gd_client_portal_register_audit_assets'); add_action('admin_enqueue_scripts','gd_client_portal_register_audit_assets');
function gd_client_portal_register_audit_menu(){add_submenu_page('gd-client-portal',__('Activity & Audit','gd-client-portal'),__('Activity & Audit','gd-client-portal'),'gd_client_portal_access_admin','gd-client-portal-audit','gd_client_portal_render_audit_admin');}
add_action('admin_menu','gd_client_portal_register_audit_menu',20);
function gd_client_portal_render_audit_admin(){if(!gd_client_portal_user_can_access_admin()) wp_die(esc_html__('You do not have permission to access this page.','gd-client-portal')); echo '<div class="wrap gd-audit-admin-wrap">'.gd_client_portal_render_audit_center(false).'</div>';}
