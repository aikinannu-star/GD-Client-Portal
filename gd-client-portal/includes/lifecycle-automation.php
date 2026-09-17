<?php
/**
 * GD Client Portal v5.6 — Client Lifecycle Automation.
 * Detects lifecycle gaps, recommends next actions and safely executes approved actions.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_lifecycle_gap($row) {
    if (empty($row) || empty($row['project'])) return array();
    $p = $row['project'];
    $stages = (array)($row['stages'] ?? array());
    foreach ($stages as $s) {
        if (($s['tone'] ?? '') === 'blocked') {
            return array('key'=>'blocked_'.$s['label'], 'label'=>$s['label'].' requires attention', 'reason'=>gd_client_portal_lifecycle_status_label($s['status']), 'action'=>'notify_team');
        }
    }
    $next = (string)($row['next'] ?? '');
    if (!$next) return array();
    $map = array(
        'Complete client onboarding'=>array('key'=>'onboarding','action'=>'notify_client'),
        'Complete service intake'=>array('key'=>'intake','action'=>'notify_client'),
        'Prepare or send a quote'=>array('key'=>'quote','action'=>'notify_team'),
        'Await client quote decision'=>array('key'=>'quote_wait','action'=>'notify_client'),
        'Create or send the agreement'=>array('key'=>'agreement','action'=>'create_agreement'),
        'Await client agreement decision'=>array('key'=>'agreement_wait','action'=>'notify_client'),
        'Follow up on invoice payment'=>array('key'=>'payment','action'=>'notify_client'),
        'Publish the first deliverable'=>array('key'=>'delivery','action'=>'notify_team'),
        'Await client approval'=>array('key'=>'approval','action'=>'notify_client'),
        'Start the requested revision'=>array('key'=>'revision','action'=>'start_revision'),
        'Collect client feedback'=>array('key'=>'feedback','action'=>'notify_client'),
        'Continue project delivery'=>array('key'=>'delivery_continue','action'=>'notify_team'),
    );
    $m = $map[$next] ?? array('key'=>'general','action'=>'notify_team');
    return array_merge($m,array('label'=>$next,'reason'=>'Lifecycle milestone needs attention.'));
}

function gd_client_portal_lifecycle_intelligence($project_id) {
    $p = function_exists('gd_client_portal_get_project_by_id') ? gd_client_portal_get_project_by_id($project_id) : false;
    if (!$p || !function_exists('gd_client_portal_lifecycle_project')) return array();
    $row = gd_client_portal_lifecycle_project($p);
    $gap = gd_client_portal_lifecycle_gap($row);
    $row['gap'] = $gap;
    return $row;
}

function gd_client_portal_lifecycle_notify_project($project, $audience='team') {
    if (!$project || !function_exists('gd_client_portal_create_notification')) return false;
    $ids = array();
    if ($audience === 'client' && !empty($project->user_id)) $ids[] = absint($project->user_id);
    if ($audience === 'team' && function_exists('gd_client_portal_notification_recipients')) $ids = gd_client_portal_notification_recipients($project,0);
    if ($audience === 'team' && function_exists('gd_client_portal_get_project_lead')) { $lead=gd_client_portal_get_project_lead($project->id); if($lead)$ids[]=absint($lead->user_id); }
    $ids=array_values(array_unique(array_filter(array_map('absint',$ids))));
    foreach($ids as $uid) gd_client_portal_create_notification($uid,__('Lifecycle action needed','gd-client-portal'),sprintf(__('Project #%d: %s','gd-client-portal'),$project->id,__('A lifecycle milestone needs attention.','gd-client-portal')),'lifecycle',$project->id,$project->tenant_id);
    return !empty($ids);
}

function gd_client_portal_lifecycle_execute_action($project_id,$action) {
    $row=gd_client_portal_lifecycle_intelligence($project_id); $p=$row['project']??false;
    if(!$p||!gd_client_portal_verify_project_access($p)||!(gd_client_portal_is_platform_admin()||gd_client_portal_user_is_tenant_admin())) return array('ok'=>false,'message'=>__('Access denied.','gd-client-portal'));
    $gap=$row['gap']??array();
    if(!$gap||$gap['action']!==$action) return array('ok'=>false,'message'=>__('That lifecycle action is no longer current. Refresh and try again.','gd-client-portal'));
    switch($action){
        case 'notify_client':
            return array('ok'=>gd_client_portal_lifecycle_notify_project($p,'client'),'message'=>__('Client notification sent.','gd-client-portal'));
        case 'notify_team':
            return array('ok'=>gd_client_portal_lifecycle_notify_project($p,'team'),'message'=>__('Team notification sent.','gd-client-portal'));
        case 'create_agreement':
            if(!function_exists('gd_client_portal_contracts_create_from_quote')) return array('ok'=>false,'message'=>__('Agreement service is unavailable.','gd-client-portal'));
            $q=function_exists('gdcp_billing_service') ? gdcp_billing_service()->repo_latest_quote_for_project($p->id) : null; if($q && $q->status!=='accepted') $q=null;
            $id=$q?gd_client_portal_contracts_create_from_quote($q->id):0;
            if($id){ if(function_exists('gd_client_portal_audit_log')) gd_client_portal_audit_log('lifecycle_action','project',$p->id,__('Agreement created from lifecycle recommendation.','gd-client-portal'),array('action'=>$action)); return array('ok'=>true,'message'=>__('Agreement created from the accepted quote.','gd-client-portal'),'id'=>$id); }
            return array('ok'=>false,'message'=>__('No accepted quote was available to create an agreement.','gd-client-portal'));
        case 'start_revision':
            $workflow=gd_client_portal_get_workflow($p->service_type); $target=in_array('revisions',$workflow,true)?'revisions':$p->current_stage;
            $progress=gd_client_portal_auto_progress($p->service_type,$target);
            $ok=gd_client_portal_update_project_stage($p->id,$target,$progress);
            return array('ok'=>(bool)$ok,'message'=>$ok?__('Revision workflow started.','gd-client-portal'):__('Could not start the revision workflow.','gd-client-portal'));
    }
    return array('ok'=>false,'message'=>__('Unsupported lifecycle action.','gd-client-portal'));
}

function gd_client_portal_lifecycle_execute_ajax(){
    gd_client_portal_ajax_guard('gd_client_portal_lifecycle_action','nonce');
    if (!(gd_client_portal_is_platform_admin() || gd_client_portal_user_is_tenant_admin())) wp_send_json_error(array('message'=>__('Access denied.','gd-client-portal')),403);
    $project_id=absint($_POST['project_id']??0); $action=sanitize_key($_POST['action']??'');
    $result=gd_client_portal_lifecycle_execute_action($project_id,$action);
    if(!$result['ok']) wp_send_json_error($result,400);
    wp_send_json_success($result);
}
add_action('wp_ajax_gd_client_portal_lifecycle_action','gd_client_portal_lifecycle_execute_ajax');

function gd_client_portal_lifecycle_daily_intelligence(){
    if(!function_exists('gd_client_portal_lifecycle_projects')) return;
    $rows=gd_client_portal_lifecycle_projects(200,'',0);
    foreach($rows as $row){
        $p=$row['project']??false; if(!$p)continue; $gap=gd_client_portal_lifecycle_gap($row); if(!$gap)continue;
        $key='gdcp_lifecycle_gap_'.absint($p->id).'_'.sanitize_key($gap['key']);
        if(get_transient($key))continue;
        set_transient($key,1,DAY_IN_SECONDS);
        do_action('gd_client_portal_lifecycle_gap',$p->id,$gap); if(function_exists('gd_client_portal_automation_run')) gd_client_portal_automation_run('lifecycle_gap',array('project_id'=>$p->id,'tenant_id'=>$p->tenant_id,'gap_key'=>$gap['key'],'next_action'=>$gap['label']));
        if(function_exists('gd_client_portal_create_notification')) gd_client_portal_lifecycle_notify_project($p,'team');
    }
}
add_action('gd_client_portal_automation_daily','gd_client_portal_lifecycle_daily_intelligence',20);

function gd_client_portal_lifecycle_automation_assets(){
    if(is_admin() && isset($_GET['page']) && $_GET['page']==='gd-client-portal-lifecycle'){
        wp_register_script('gd-client-portal-lifecycle-automation',GD_CLIENT_PORTAL_URL.'assets/lifecycle-automation.js',array('jquery'),GD_CLIENT_PORTAL_VERSION,true);
        wp_localize_script('gd-client-portal-lifecycle-automation','GDCP_LIFECYCLE',array('nonce'=>wp_create_nonce('gd_client_portal_lifecycle_action')));
        wp_enqueue_script('gd-client-portal-lifecycle-automation');
    }
}
add_action('admin_enqueue_scripts','gd_client_portal_lifecycle_automation_assets',30);
