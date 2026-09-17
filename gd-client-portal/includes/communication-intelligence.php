<?php
/**
 * GD Client Portal v5.7 — Client Experience & Communication Intelligence.
 * Coordinates timely client-facing updates around major lifecycle events.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_comm_intel_enabled() { return (bool) get_option('gdcp_comm_intel_enabled', 1); }
function gd_client_portal_comm_intel_pref($user_id) {
    return array(
        'email' => get_user_meta($user_id,'gdcp_comm_email_updates',true) !== '0',
        'portal' => get_user_meta($user_id,'gdcp_comm_portal_updates',true) !== '0',
    );
}
function gd_client_portal_comm_intel_template($key,$project,$extra=array()) {
    $client=$project && $project->user_id ? gd_client_portal_cached_user($project->user_id) : false;
    $name=$client ? $client->display_name : __('Client','gd-client-portal');
    $title=$project ? $project->title : __('Your project','gd-client-portal');
    $tenant=false;
    if ($project && function_exists('gd_client_portal_get_all_tenants')) { $all=gd_client_portal_get_all_tenants(); $tenant=$all[absint($project->tenant_id)]??false; }
    $brand=$tenant['portal_title']??get_bloginfo('name');
    $templates=array(
      'project_created'=>array('Your project has been created','Hi %s, your project “%s” is now set up in the client portal. We’ll guide you through the next steps.'),
      'stage_changed'=>array('Your project has moved forward','Hi %s, there’s an update on “%s”. The project has moved to a new workflow stage: %s.'),
      'lifecycle_gap'=>array('A project step needs your attention','Hi %s, there’s a next step waiting for “%s”: %s.'),
      'payment_received'=>array('Payment received','Hi %s, we’ve received your payment for “%s”. Thank you.'),
      'delivery_finalized'=>array('Your final delivery is ready','Hi %s, the final delivery for “%s” has been finalized. Please sign in to review your project files.'),
      'support_ticket_created'=>array('Your support request is being handled','Hi %s, your support request “%s” has been received. Our team will follow up through the portal.'),
      'approval_requested'=>array('Your project needs your approval','Hi %s, “%s” is ready for your review. Please sign in to review and approve or request a revision.'),
    );
    $tpl=$templates[$key]??array('Project update','Hi %s, there is a new update on “%s”. Please sign in to your client portal for details.');
    $body=sprintf($tpl[1],$name,$title,$extra['value']??'');
    $subject=$tpl[0].' — '.$brand;
    return array('subject'=>$subject,'body'=>$body,'name'=>$name);
}
function gd_client_portal_comm_intel_project_url($project) {
    return $project ? add_query_arg('project_id',absint($project->id),gd_client_portal_get_dashboard_url()) : gd_client_portal_get_dashboard_url();
}
function gd_client_portal_comm_intel_send($project_id,$key,$extra=array(),$force=false) {
    if(!gd_client_portal_comm_intel_enabled()) return false;
    $project=function_exists('gd_client_portal_get_project_by_id')?gd_client_portal_cached_project($project_id):false;
    if(!$project || empty($project->user_id)) return false;
    $uid=absint($project->user_id); $pref=gd_client_portal_comm_intel_pref($uid);
    $cool='gdcp_ci_'.absint($project_id).'_'.sanitize_key($key);
    if(!$force && get_transient($cool)) return false;
    $data=gd_client_portal_comm_intel_template($key,$project,$extra); $url=gd_client_portal_comm_intel_project_url($project);
    $body=$data['body']."\n\n".sprintf(__('View project: %s','gd-client-portal'),$url);
    $sent=false;
    if($pref['portal'] && function_exists('gd_client_portal_create_notification')) {
        gd_client_portal_create_notification($uid,$data['subject'],$data['body'],'communication',absint($project->id),absint($project->tenant_id)); $sent=true;
    }
    if($pref['email'] && is_email($u=gd_client_portal_cached_user($uid)?gd_client_portal_cached_user($uid)->user_email:'')) {
        $sent=wp_mail($u,$data['subject'],$body) || $sent;
    }
    if($sent && !$force) set_transient($cool,1,12*HOUR_IN_SECONDS);
    if($sent && function_exists('gd_client_portal_audit_log')) gd_client_portal_audit_log('client_communication','project',absint($project->id),$data['subject'],array('template'=>$key,'email'=>(bool)$pref['email'],'portal'=>(bool)$pref['portal']));
    return $sent;
}
function gd_client_portal_comm_intel_stage_changed($project_id,$old_stage='',$new_stage='') {
    return gd_client_portal_comm_intel_send($project_id,'stage_changed',array('value'=>ucwords(str_replace('_',' ',(string)$new_stage))));
}
function gd_client_portal_comm_intel_event_project_created($project_id,$project=null){ return gd_client_portal_comm_intel_send($project_id,'project_created'); }
function gd_client_portal_comm_intel_event_payment($payment){ if(!$payment)return false; return gd_client_portal_comm_intel_send(absint($payment->project_id),'payment_received'); }
function gd_client_portal_comm_intel_event_delivery($delivery){ if(!$delivery)return false; return gd_client_portal_comm_intel_send(absint($delivery->project_id),'delivery_finalized'); }
function gd_client_portal_comm_intel_event_approval($approval){ if(!$approval)return false; return gd_client_portal_comm_intel_send(absint($approval->project_id),'approval_requested'); }
function gd_client_portal_comm_intel_event_support($ticket){ if(!$ticket)return false; return gd_client_portal_comm_intel_send(absint($ticket->project_id),'support_ticket_created',array('value'=>$ticket->subject??'Support request')); }
function gd_client_portal_comm_intel_event_gap($project_id,$gap=array()){ return gd_client_portal_comm_intel_send($project_id,'lifecycle_gap',array('value'=>$gap['label']??__('A lifecycle milestone needs attention.','gd-client-portal'))); }
add_action('gd_client_portal_project_created','gd_client_portal_comm_intel_event_project_created',35,2);
add_action('gd_client_portal_project_stage_changed','gd_client_portal_comm_intel_stage_changed',35,3);
add_action('gd_client_portal_payment_received','gd_client_portal_comm_intel_event_payment',35,1);
add_action('gd_client_portal_delivery_finalized','gd_client_portal_comm_intel_event_delivery',35,1);
add_action('gd_client_portal_approval_requested','gd_client_portal_comm_intel_event_approval',35,1);
add_action('gd_client_portal_support_ticket_created','gd_client_portal_comm_intel_event_support',35,1);
add_action('gd_client_portal_lifecycle_gap','gd_client_portal_comm_intel_event_gap',35,2);

function gd_client_portal_comm_intel_preferences_page(){
    if(!gd_client_portal_user_can_access_admin()) wp_die(__('Access denied.','gd-client-portal'));
    if(isset($_POST['gdcp_ci_save']) && check_admin_referer('gdcp_ci_settings')) { update_option('gdcp_comm_intel_enabled',empty($_POST['enabled'])?0:1); echo '<div class="notice notice-success"><p>Communication intelligence settings saved.</p></div>'; }
    $enabled=gd_client_portal_comm_intel_enabled();
    echo '<div class="wrap"><h1>Client Communication Intelligence</h1><p>Coordinate timely client-facing portal and email updates without replacing the existing notification and workflow systems.</p><form method="post">'; wp_nonce_field('gdcp_ci_settings'); echo '<label><input type="checkbox" name="enabled" value="1" '.checked($enabled,true,false).'> Enable intelligent client communications</label><p class="description">Updates are rate-limited per project/event to avoid duplicate messages.</p><p><button class="button button-primary" name="gdcp_ci_save" value="1">Save settings</button></p></form><h2>Supported communication triggers</h2><ul><li>Project created</li><li>Workflow stage changed</li><li>Lifecycle milestone needs attention</li><li>Payment received</li><li>Approval requested</li><li>Final delivery finalized</li><li>Support ticket created</li></ul></div>';
}
add_action('admin_menu',function(){ add_submenu_page('gd-client-portal','Communication Intelligence','Communication Intelligence','gd_client_portal_access_admin','gd-client-portal-communication-intelligence','gd_client_portal_comm_intel_preferences_page'); },20);

function gd_client_portal_comm_intel_journey($atts=array()) {
    if(!gd_client_portal_verify_request() || !gd_client_portal_verify_tenant_access()) return gd_client_portal_render_access_gate();
    $pid=absint($atts['project_id']??($_GET['project_id']??0)); if(!$pid)return '<div class="gdcp-ci-empty">Select a project to view its communication journey.</div>';
    $p=gd_client_portal_cached_project($pid); if(!$p || !gd_client_portal_verify_project_access($p))return gd_client_portal_render_access_gate();
    global $wpdb; $events=array();
    if(function_exists('gd_client_portal_get_audit_events')) $events=gd_client_portal_get_audit_events(array('project_id'=>$pid,'limit'=>40));
    $events=array_filter((array)$events,function($e){ return in_array($e->event_type,array('client_communication','project_created','stage_changed','payment_received','approval_requested','delivery_finalized','support_ticket_created','lifecycle_gap'),true); });
    ob_start(); echo '<div class="gdcp-ci-journey"><div class="gdcp-ci-journey-head"><div><span>CLIENT JOURNEY</span><h3>'.esc_html($p->title).'</h3></div><strong>'.count($events).' updates</strong></div><div class="gdcp-ci-timeline">';
    foreach($events as $e) echo '<article><i></i><div><strong>'.esc_html($e->summary).'</strong><time>'.esc_html($e->created_at).'</time>'.(!empty($e->details)?'<p>'.esc_html($e->details).'</p>':'').'</div></article>';
    if(!$events) echo '<div class="gdcp-ci-empty">No communication events recorded yet.</div>';
    echo '</div></div>'; return ob_get_clean();
}
add_shortcode('gd_client_communication_journey','gd_client_portal_comm_intel_journey');

function gd_client_portal_comm_intel_assets(){
    wp_register_style('gd-client-portal-communication-intelligence',GD_CLIENT_PORTAL_URL.'assets/communication-intelligence.css',array('gd-client-portal'),GD_CLIENT_PORTAL_VERSION);
    if(!is_admin()) wp_enqueue_style('gd-client-portal-communication-intelligence');
}
add_action('wp_enqueue_scripts','gd_client_portal_comm_intel_assets');
add_action('admin_enqueue_scripts','gd_client_portal_comm_intel_assets');
