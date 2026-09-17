<?php
/**
 * GD Client Portal v5.2 - Intelligent Client & Project Command Center.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_command_center_data() {
    $data = array(
        'projects'=>0,'active'=>0,'completed'=>0,'overdue'=>0,'at_risk'=>0,'unassigned'=>0,
        'revenue'=>0,'paid'=>0,'outstanding'=>0,'invoices'=>0,'open_tickets'=>0,'overdue_tickets'=>0,
        'avg_rating'=>0,'recommend'=>0,'reviews'=>0,'tasks_open'=>0,'risk_high'=>0,'risk_medium'=>0,
        'automation_failed'=>0,'automation_queued'=>0,'automation_stale'=>0,'automation_success_rate'=>100,
    );
    if (function_exists('gd_client_portal_exec_metrics')) $data = array_merge($data, (array) gd_client_portal_exec_metrics());
    if (function_exists('gd_client_portal_predictive_projects')) {
        foreach ((array) gd_client_portal_predictive_projects(100) as $item) {
            if (($item['level'] ?? '') === 'high') $data['risk_high']++;
            elseif (($item['level'] ?? '') === 'medium') $data['risk_medium']++;
        }
    }
    if (function_exists('gdcp_automation_service')) {
        $stats=gdcp_automation_service()->stats();
        $data['automation_failed']=(int)($stats['failed']??0); $data['automation_queued']=(int)($stats['queued']??0); $data['automation_stale']=(int)($stats['stale']??0);
    }
    return $data;
}



function gd_client_portal_command_center_snapshot_ajax() {
    if (!is_user_logged_in() || !gd_client_portal_verify_request() || !check_ajax_referer('gd_client_portal_command_center','nonce',false)) {
        wp_send_json_error(array('message'=>__('Invalid request.','gd-client-portal')),403);
    }
    if (!(current_user_can('manage_options') || gd_client_portal_user_is_tenant_admin())) {
        wp_send_json_error(array('message'=>__('You do not have permission to perform this action.','gd-client-portal')),403);
    }
    $d = gd_client_portal_command_center_data();
    $attention = array();
    if (function_exists('gd_client_portal_predictive_projects')) {
        foreach ((array) gd_client_portal_predictive_projects(20) as $item) {
            if (in_array(($item['level'] ?? ''), array('high','medium'), true)) $attention[] = $item;
        }
    }
    wp_send_json_success(array(
        'metrics'=>array(
            'projects'=>(int)$d['projects'],'active'=>(int)$d['active'],'completed'=>(int)$d['completed'],
            'overdue'=>(int)$d['overdue'],'at_risk'=>(int)$d['at_risk'],'unassigned'=>(int)$d['unassigned'],
            'outstanding'=>(float)$d['outstanding'],'open_tickets'=>(int)$d['open_tickets'],
            'tasks_open'=>(int)$d['tasks_open'],'automation_failed'=>(int)$d['automation_failed'],
            'automation_queued'=>(int)$d['automation_queued'],'automation_success_rate'=>(float)$d['automation_success_rate'],
        ),
        'attention_count'=>count($attention),
        'generated_at'=>current_time('mysql'),
    ));
}
add_action('wp_ajax_gd_client_portal_command_center_snapshot','gd_client_portal_command_center_snapshot_ajax');

function gd_client_portal_command_center_project_ajax() {
    if (!is_user_logged_in() || !gd_client_portal_verify_request() || !check_ajax_referer('gd_client_portal_command_center','nonce',false)) wp_send_json_error(array('message'=>__('Invalid request.','gd-client-portal')),403);
    if (!(current_user_can('manage_options') || gd_client_portal_user_is_tenant_admin())) wp_send_json_error(array('message'=>__('You do not have permission to perform this action.','gd-client-portal')),403);
    $id=absint($_POST['project_id']??0); $p=$id?gd_client_portal_get_project_by_id($id):false;
    if (!$p || !gd_client_portal_verify_project_access($p)) wp_send_json_error(array('message'=>__('Project access denied.','gd-client-portal')),403);
    $u=$p->user_id?gd_client_portal_cached_user($p->user_id):false; $lead=function_exists('gd_client_portal_get_project_lead')?gd_client_portal_get_project_lead($id):false;
    $risk=function_exists('gd_client_portal_predictive_score_project')?gd_client_portal_predictive_score_project($p):array('score'=>0,'level'=>'unknown','signals'=>array());
    wp_send_json_success(array('project'=>array('id'=>$id,'title'=>$p->title??__('Project','gd-client-portal'),'status'=>$p->status??'','stage'=>$p->current_stage??'','progress'=>isset($p->progress)?(int)$p->progress:0,'service_type'=>$p->service_type??'','tenant_id'=>(int)$p->tenant_id,'client'=>$u?array('name'=>$u->display_name,'email'=>$u->user_email):array('name'=>'','email'=>''),'lead'=>$lead?array('name'=>$lead->display_name,'email'=>$lead->user_email):null,'risk'=>$risk)));
}
add_action('wp_ajax_gd_client_portal_command_center_project','gd_client_portal_command_center_project_ajax');

function gd_client_portal_command_center_action_ajax() {
    if (!is_user_logged_in() || !gd_client_portal_verify_request() || !check_ajax_referer('gd_client_portal_command_center','nonce',false)) wp_send_json_error(array('message'=>__('Invalid request.','gd-client-portal')),403);
    if (!(current_user_can('manage_options') || gd_client_portal_user_is_tenant_admin())) wp_send_json_error(array('message'=>__('You do not have permission to perform this action.','gd-client-portal')),403);
    $op=sanitize_key(wp_unslash($_POST['operation']??'')); $id=absint($_POST['id']??0); $project_id=absint($_POST['project_id']??0); $project=$project_id?gd_client_portal_get_project_by_id($project_id):false;
    if ($project && !gd_client_portal_verify_project_access($project)) wp_send_json_error(array('message'=>__('Project access denied.','gd-client-portal')),403);
    if (in_array($op,array('assign_lead','advance_stage','notify_client','start_revision','create_task'),true) && !$project) wp_send_json_error(array('message'=>__('Project not found.','gd-client-portal')),404);
    if ($op==='notify_client') { $owner=$project->user_id?gd_client_portal_cached_user($project->user_id):false; if(!$owner) wp_send_json_error(array('message'=>__('This project has no assigned client.','gd-client-portal'))); wp_mail($owner->user_email,sprintf(__('Action needed: %s','gd-client-portal'),$project->title),sprintf(__('Your project “%s” needs your attention. Please open your Client Portal workspace to continue.','gd-client-portal'),$project->title)); if(function_exists('gd_client_portal_create_notification')) gd_client_portal_create_notification($owner->ID,__('Action needed on your project','gd-client-portal'),sprintf(__('Please review and continue %s in your Client Portal.','gd-client-portal'),$project->title),'action_required',$project->id,$project->tenant_id); do_action('gd_client_portal_client_reminder_sent',$project->id); if(function_exists('gd_client_portal_audit_log')) gd_client_portal_audit_log('client_reminder',$project->title,$project->id,$project->tenant_id,'','project',$project->id,array('source'=>'command_center')); wp_send_json_success(array('message'=>__('Client reminder sent.','gd-client-portal'))); }
    if ($op==='advance_stage' || $op==='start_revision') { $workflow=gd_client_portal_get_workflow($project->service_type); if($op==='advance_stage'){ $idx=array_search($project->current_stage,$workflow,true); $next=($idx!==false&&!empty($workflow[$idx+1]))?$workflow[$idx+1]:''; } else { $next=in_array('revisions',$workflow,true)?'revisions':''; } if(!$next || ($op==='start_revision' && $next===$project->current_stage)) wp_send_json_error(array('message'=>__('No valid next stage is available.','gd-client-portal'))); if(!gd_client_portal_update_project_stage($project->id,$next,gd_client_portal_auto_progress($project->service_type,$next))) wp_send_json_error(array('message'=>__('Unable to update the project stage.','gd-client-portal'))); wp_send_json_success(array('message'=>sprintf(__('Project moved to %s.','gd-client-portal'),ucwords(str_replace('_',' ',$next))),'stage'=>$next)); }
    if ($op==='assign_lead') { $uid=absint($_POST['user_id']??0); if(!function_exists('gd_client_portal_set_project_assignment') || !$uid || !gd_client_portal_set_project_assignment($project->id,$uid,'lead')) wp_send_json_error(array('message'=>__('Unable to assign that team lead.','gd-client-portal'))); $u=gd_client_portal_cached_user($uid); wp_send_json_success(array('message'=>sprintf(__('Lead assigned to %s.','gd-client-portal'),$u?$u->display_name:__('team member','gd-client-portal')),'user_id'=>$uid)); }
    if ($op==='submit_approval') {
        $service=function_exists('gdcp_service')?gdcp_service('approval'):null;
        if(!$service || !method_exists($service,'submit')) wp_send_json_error(array('message'=>__('Approval workflow is unavailable.','gd-client-portal')),500);
        $result=$service->submit($project->id);
        if(empty($result['ok'])) wp_send_json_error(array('message'=>$result['message']??__('Unable to create the approval request.','gd-client-portal')),absint($result['code']??400));
        $version=absint($result['version']); $owner=$project->user_id?gd_client_portal_cached_user($project->user_id):false;
        if($owner&&$owner->user_email)wp_mail($owner->user_email,sprintf(__('Approval requested: %s','gd-client-portal'),$project->title),sprintf(__('A new version of your project is ready for review: %s\n\nPlease open your project workspace to approve it or request a revision.','gd-client-portal'),$project->title));
        do_action('gd_client_portal_approval_requested',$project->id,$version);
        wp_send_json_success(array('message'=>__('Project submitted for client approval.','gd-client-portal'),'version'=>$version));
    }
    if ($op==='create_contract') {
        $quote_id=absint($_POST['id']??0);
        if(!function_exists('gd_client_portal_contracts_create_from_quote'))wp_send_json_error(array('message'=>__('Contracts module is unavailable.','gd-client-portal')),500);
        $q=function_exists('gdcp_billing_service') ? gdcp_billing_service()->get_quote_for_access($quote_id) : null;
        if(!$q||$q->status!=='accepted')wp_send_json_error(array('message'=>__('Only an accepted quote can create an agreement.','gd-client-portal')),400);
        if(!gd_client_portal_billing_quote_access($q,true))wp_send_json_error(array('message'=>__('Quote access denied.','gd-client-portal')),403);
        $contract_id=gd_client_portal_contracts_create_from_quote($quote_id); if(!$contract_id)wp_send_json_error(array('message'=>__('Unable to create the agreement. It may already exist.','gd-client-portal')));
        if(function_exists('gd_client_portal_audit_log'))gd_client_portal_audit_log('contract_created','Agreement created from accepted quote',$q->project_id,$q->tenant_id,$q->quote_number,'quote',$quote_id,array('source'=>'command_center','contract_id'=>$contract_id));
        wp_send_json_success(array('message'=>__('Agreement created from the accepted quote.','gd-client-portal'),'contract_id'=>$contract_id));
    }
    if ($op==='retry_automation' || $op==='cancel_automation') { if(!function_exists('gdcp_automation_service')) wp_send_json_error(array('message'=>__('Automation system is unavailable.','gd-client-portal'))); $row=gdcp_automation_service()->get_queue($id); if(!$row) wp_send_json_error(array('message'=>__('Queue item not found.','gd-client-portal')),404); if($row->project_id){$qp=gd_client_portal_get_project_by_id($row->project_id);if(!$qp||!gd_client_portal_verify_project_access($qp))wp_send_json_error(array('message'=>__('Queue item access denied.','gd-client-portal')),403);} if($op==='retry_automation'){ if(!gdcp_automation_service()->retry_queue($id)) wp_send_json_error(array('message'=>__('Automation item is not eligible for retry.','gd-client-portal')),409);  wp_schedule_single_event(time()+2,'gd_client_portal_automation_queue_runner',array($id)); wp_send_json_success(array('message'=>__('Automation item requeued.','gd-client-portal'))); } if(!gdcp_automation_service()->cancel_queue($id)) wp_send_json_error(array('message'=>__('Automation item is not eligible for cancellation.','gd-client-portal')),409); wp_send_json_success(array('message'=>__('Automation item cancelled.','gd-client-portal'))); }
    if ($op==='update_ticket') { global $wpdb; $t=function_exists('gd_client_portal_support_ticket')?gd_client_portal_support_ticket($id):false; if(!$t||!gd_client_portal_support_access($t,true)) wp_send_json_error(array('message'=>__('Support ticket access denied.','gd-client-portal')),403); $status=sanitize_key(wp_unslash($_POST['status']??'in_progress')); $allowed=gd_client_portal_support_statuses(); if(!isset($allowed[$status]))$status='in_progress'; $assigned=absint($_POST['assigned_to']??0); if($assigned){$u=gd_client_portal_cached_user($assigned);$tenant=absint(get_user_meta($assigned,'gd_client_portal_tenant_id',true));if(!$u||$tenant!==absint($t->tenant_id)||user_can($assigned,'manage_options'))$assigned=0;} if(!gd_client_portal_support_service()->update_ticket($t,$status,$t->priority,$assigned))wp_send_json_error(array('message'=>__('Support ticket update failed.','gd-client-portal')),500); if(function_exists('gd_client_portal_audit_log'))gd_client_portal_audit_log('support_ticket_updated',$t->subject,$t->project_id,$t->tenant_id,'','support_ticket',$id,array('status'=>$status,'assigned_to'=>$assigned,'source'=>'command_center')); wp_send_json_success(array('message'=>__('Support ticket updated.','gd-client-portal'))); }
    wp_send_json_error(array('message'=>__('Unknown command.','gd-client-portal')));
}
add_action('wp_ajax_gd_client_portal_command_center_action','gd_client_portal_command_center_action_ajax');

function gd_client_portal_command_center_unified_queue($limit=30){
    global $wpdb; $items=array();
    $projects=function_exists('gd_client_portal_get_visible_projects')?(array)gd_client_portal_get_visible_projects(200):array();
    foreach($projects as $p){
        $risk=function_exists('gd_client_portal_predictive_score_project')?gd_client_portal_predictive_score_project($p):array('level'=>'low','score'=>0,'signals'=>array());
        $stage=(string)($p->current_stage??''); $status=(string)($p->status??'');
        if($risk['level']==='high' || $status==='awaiting_requirements' || empty(gd_client_portal_get_project_lead($p->id))){
            $items[]=array('kind'=>'project','id'=>(int)$p->id,'priority'=>$risk['level']==='high'?'urgent':'watch','title'=>$p->title,'meta'=>'Project #'.$p->id.' · '.ucwords(str_replace('_',' ',$stage)),'action'=>'view_project','project_id'=>(int)$p->id);
        }
    }
    if(function_exists('gd_client_portal_support_tickets')) foreach((array)gd_client_portal_support_tickets(100) as $t){
        if(in_array($t->status,array('resolved','closed'),true)) continue;
        $overdue=!empty($t->due_at)&&strtotime($t->due_at)<current_time('timestamp');
        $items[]=array('kind'=>'ticket','id'=>(int)$t->id,'priority'=>$overdue||$t->priority==='urgent'?'urgent':($t->priority==='high'?'watch':'normal'),'title'=>$t->subject,'meta'=>'Support #'.$t->id.' · '.ucwords(str_replace('_',' ',$t->status)),'action'=>'update_ticket','project_id'=>(int)$t->project_id);
    }
    if(function_exists('gdcp_automation_service')){ $rows=gdcp_automation_service()->list_queue_by_status(array('failed','running'),20); foreach((array)$rows as $q) $items[]=array('kind'=>'automation','id'=>(int)$q->id,'priority'=>$q->status==='failed'?'urgent':'watch','title'=>'Automation #'.$q->id,'meta'=>ucfirst($q->status).' · '.($q->project_id?'Project #'.$q->project_id:'System workflow'),'action'=>$q->status==='failed'?'retry_automation':'view_automation','project_id'=>(int)$q->project_id);
    }
    if(function_exists('gd_client_portal_billing_get_invoices')) foreach((array)gd_client_portal_billing_get_invoices(100) as $inv){ $st=function_exists('gd_client_portal_billing_display_status')?gd_client_portal_billing_display_status($inv):$inv->status; if(!in_array($st,array('overdue','unpaid','part_paid'),true)) continue; $items[]=array('kind'=>'invoice','id'=>(int)$inv->id,'priority'=>$st==='overdue'?'urgent':'watch','title'=>$inv->invoice_number,'meta'=>'Invoice · '.ucwords(str_replace('_',' ',$st)),'action'=>'view_invoice','project_id'=>(int)$inv->project_id);
    }
    usort($items,function($a,$b){$rank=array('urgent'=>1,'watch'=>2,'normal'=>3);return ($rank[$a['priority']]??9)<=>($rank[$b['priority']]??9);}); return array_slice($items,0,max(1,min(50,(int)$limit)));
}


function gd_client_portal_command_center_executive_actions($limit=20) {
    global $wpdb;
    $items=array(); $limit=max(5,min(50,absint($limit)));
    $tenant=gd_client_portal_is_platform_admin()?0:absint(gd_client_portal_get_current_tenant_id());
    if(!gd_client_portal_is_platform_admin() && $tenant<=0)return $items;
    // Pending project approvals: admins can submit eligible projects for client approval.
    if(function_exists('gdcp_service')){
        $service=gdcp_service('approval'); $rows=($service && method_exists($service,'pending_for_tenant'))?$service->pending_for_tenant($tenant,$limit):array();
        foreach((array)$rows as $r){
            $project=gd_client_portal_get_project_by_id($r->project_id); if(!$project||!gd_client_portal_verify_project_access($project))continue;
            $items[]=array('kind'=>'approval','id'=>(int)$r->id,'project_id'=>(int)$r->project_id,'priority'=>'urgent','title'=>sprintf(__('Project #%d awaiting client approval','gd-client-portal'),$r->project_id),'meta'=>sprintf(__('Version %d · pending since %s','gd-client-portal'),$r->version,wp_date(get_option('date_format'),strtotime($r->created_at))),'action'=>'open_project');
        }
    }
    // Quotes that are already accepted can be converted to an agreement without leaving the center.
    if(function_exists('gd_client_portal_billing_get_quotes')){
        foreach((array)gd_client_portal_billing_get_quotes(100) as $q){
            if($q->status!=='accepted')continue;
            if($tenant && absint($q->tenant_id)!==$tenant)continue;
            if(function_exists('gd_client_portal_contracts_latest') && gd_client_portal_contracts_latest($q->id))continue;
            if(function_exists('gd_client_portal_contracts_get')){
                $existing=false; foreach((array)gd_client_portal_contracts_get(100) as $c){if(absint($c->quote_id)===absint($q->id)){$existing=true;break;}}
                if($existing)continue;
            }
            $items[]=array('kind'=>'quote','id'=>(int)$q->id,'project_id'=>(int)$q->project_id,'priority'=>'watch','title'=>$q->quote_number.' · '.$q->title,'meta'=>__('Accepted quote ready for agreement','gd-client-portal'),'action'=>'create_contract');
        }
    }
    // Contracts sent to clients remain visible as executive follow-up items.
    if(function_exists('gd_client_portal_contracts_get')){
        foreach((array)gd_client_portal_contracts_get(100) as $c){
            if($c->status!=='sent')continue;
            if($tenant && absint($c->tenant_id)!==$tenant)continue;
            $items[]=array('kind'=>'contract','id'=>(int)$c->id,'project_id'=>(int)$c->project_id,'priority'=>'watch','title'=>$c->contract_number.' · '.$c->title,'meta'=>__('Agreement awaiting client decision','gd-client-portal'),'action'=>'open_contract');
        }
    }
    usort($items,function($a,$b){$rank=array('urgent'=>0,'watch'=>1,'normal'=>2);return ($rank[$a['priority']]??9)<=>($rank[$b['priority']]??9);});
    return array_slice($items,0,$limit);
}

function gd_client_portal_command_center_admin() {
    if (!gd_client_portal_user_can_access_admin()) wp_die(esc_html__('You do not have permission to access this page.', 'gd-client-portal'));
    $d = gd_client_portal_command_center_data();
    $nonce = wp_create_nonce('gd_client_portal_command_center');
    $attention = function_exists('gd_client_portal_predictive_projects') ? (array) gd_client_portal_predictive_projects(100) : array();
    $filter_status=sanitize_key(wp_unslash($_GET['cc_status']??'')); $filter_risk=sanitize_key(wp_unslash($_GET['cc_risk']??'')); $filter_tenant=absint($_GET['cc_tenant']??0);
    $attention=array_values(array_filter($attention,function($item) use($filter_status,$filter_risk,$filter_tenant){ $p=$item['project']??null; if(!$p)return false; if($filter_status && ($p->status??'')!==$filter_status)return false; if($filter_risk && ($item['level']??'')!==$filter_risk)return false; if($filter_tenant && absint($p->tenant_id)!==$filter_tenant)return false; return true; }));
    $attention=array_slice($attention,0,20);
    $assignable=array(); if(function_exists('gd_client_portal_get_assignable_users')){ $tid=gd_client_portal_is_platform_admin()?absint(gd_client_portal_get_default_tenant_id()):absint(gd_client_portal_get_current_tenant_id()); if($tid)$assignable=gd_client_portal_get_assignable_users($tid); }
    $failed_queue=function_exists('gdcp_automation_service')?gdcp_automation_service()->list_queue_by_status(array('failed'),8):array();
    $links = array(
        'Operations Center'=>'gd-client-portal-operations',
        'Executive Dashboard'=>'gd-client-portal-executive',
        'Predictive Intelligence'=>'gd-client-portal-predictive',
        'Automation & Workflow'=>'gd-client-portal-automation',
        'Workflow Studio'=>'gd-client-portal-studio',
        'Orchestration Monitor'=>'gd-client-portal-orchestration',
        'Automation Reliability'=>'gd-client-portal-reliability',
        'Workflow Governance'=>'gd-client-portal-governance',
        'Workflow Intelligence'=>'gd-client-portal-insights',
        'Event Intelligence'=>'gd-client-portal-event-intelligence',
    );
    echo '<div class="wrap gdcp-command-center"><div class="gdcp-cc-controlbar"><div class="gdcp-cc-controlbar-title"><span class="gdcp-cc-live-dot" aria-hidden="true"></span><span>Live Command Center</span><small class="gdcp-cc-last-sync" aria-live="polite">Updated now</small></div><div class="gdcp-cc-controlbar-actions"><button type="button" class="button gdcp-cc-refresh">Refresh</button><a class="button" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-inbox')).'">Inbox</a><a class="button" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-unified-search')).'">Search</a></div></div><div class="gdcp-cc-hero"><div><span>GD CLIENT PORTAL · COMMAND CENTER</span><h1>Intelligent Client &amp; Project Command Center</h1><p>One operational view across delivery, finance, client experience, support, risk and automation.</p></div><div class="gdcp-cc-health"><strong>'.esc_html((string)($d['automation_success_rate'])).'%</strong><small>30-day automation success</small></div></div>';
    echo '<div class="gdcp-cc-grid">';
    $cards = array(
        array('Projects', $d['projects'], 'Active '.$d['active'].' · Completed '.$d['completed']),
        array('At risk', $d['at_risk'] + $d['risk_high'], 'Predictive high '.$d['risk_high'].' · Medium '.$d['risk_medium']),
        array('Overdue', $d['overdue'], 'Projects past SLA'),
        array('Unassigned', $d['unassigned'], 'Projects without a lead'),
        array('Outstanding', gd_client_portal_exec_money($d['outstanding']), 'Across '.$d['invoices'].' invoices'),
        array('Open support', $d['open_tickets'], 'Overdue '.$d['overdue_tickets']),
        array('Open tasks', $d['tasks_open'], 'Collaboration workload'),
        array('Automation queue', $d['automation_queued'], 'Failed '.$d['automation_failed'].' · Stale '.$d['automation_stale']),
    );
    foreach ($cards as $c) echo '<div class="gdcp-cc-card"><small>'.esc_html($c[0]).'</small><strong>'.esc_html((string)$c[1]).'</strong><span>'.esc_html($c[2]).'</span></div>';
    echo '</div>';
    echo '<div class="gdcp-cc-columns"><section class="gdcp-cc-panel"><h2>Priority signals</h2><ul class="gdcp-cc-signals">';
    $signals = array();
    if ($d['overdue']) $signals[] = array('urgent', $d['overdue'].' project(s) are overdue.');
    if ($d['risk_high']) $signals[] = array('urgent', $d['risk_high'].' project(s) have high predictive risk.');
    if ($d['unassigned']) $signals[] = array('watch', $d['unassigned'].' project(s) have no team lead.');
    if ($d['automation_failed']) $signals[] = array('urgent', $d['automation_failed'].' automation queue item(s) failed.');
    if ($d['automation_stale']) $signals[] = array('watch', $d['automation_stale'].' automation execution(s) appear stale.');
    if ($d['overdue_tickets']) $signals[] = array('watch', $d['overdue_tickets'].' support ticket(s) are past their SLA.');
    if (!$signals) $signals[] = array('good', 'No major operational warning detected.');
    foreach ($signals as $s) echo '<li class="'.esc_attr($s[0]).'"><b>'.esc_html(ucfirst($s[0])).'</b><span>'.esc_html($s[1]).'</span></li>';
    echo '</ul></section><section class="gdcp-cc-panel"><h2>Client experience</h2><div class="gdcp-cc-experience"><strong>'.esc_html(number_format_i18n((float)$d['avg_rating'],1)).'/5</strong><span>'.intval($d['reviews']).' published reviews</span><span>'.esc_html((string)$d['recommend']).'% would recommend</span></div><p>Use the Feedback, Support and Communication areas to investigate experience signals.</p></section></div>';
    echo '<section class="gdcp-cc-panel gdcp-cc-filters"><div class="gdcp-cc-section-head"><div><h2>Command filters</h2><p>Focus the attention queue by status, risk or tenant.</p></div></div><form method="get" class="gdcp-cc-filter-form"><input type="hidden" name="page" value="gd-client-portal-command-center"><label>Status <select name="cc_status"><option value="">All statuses</option><option value="active" '.selected($filter_status,'active',false).'>Active</option><option value="in_progress" '.selected($filter_status,'in_progress',false).'>In progress</option><option value="awaiting_requirements" '.selected($filter_status,'awaiting_requirements',false).'>Awaiting requirements</option><option value="completed" '.selected($filter_status,'completed',false).'>Completed</option></select></label><label>Risk <select name="cc_risk"><option value="">All risk</option><option value="high" '.selected($filter_risk,'high',false).'>High</option><option value="medium" '.selected($filter_risk,'medium',false).'>Medium</option><option value="low" '.selected($filter_risk,'low',false).'>Low</option></select></label>';
    if (gd_client_portal_is_platform_admin()) { $tenants=gd_client_portal_get_all_tenants(); echo '<label>Tenant <select name="cc_tenant"><option value="">All tenants</option>'; foreach((array)$tenants as $tid=>$tenant){ echo '<option value="'.intval($tid).'" '.selected($filter_tenant,absint($tid),false).'>'.esc_html($tenant['name']??('Tenant #'.$tid)).'</option>'; } echo '</select></label>'; }
    echo '<button class="button button-primary" type="submit">Apply</button> <a class="button" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-command-center')).'">Reset</a></form></section>';
echo '<section class="gdcp-cc-panel gdcp-cc-attention"><div class="gdcp-cc-section-head"><div><h2>Needs attention</h2><p>Resolve high-impact items without leaving the Command Center.</p></div></div><div class="gdcp-cc-action-list">';
    foreach($attention as $item){ $pid=absint($item['project_id']??0); $p=gd_client_portal_get_project_by_id($pid); if(!$p)continue; $lead=function_exists('gd_client_portal_get_project_lead')?gd_client_portal_get_project_lead($pid):false; echo '<div class="gdcp-cc-action-row"><div><strong>'.esc_html($p->title).'</strong><span>#'.intval($pid).' · '.esc_html(ucwords(str_replace('_',' ',$p->current_stage))).' · '.esc_html(ucfirst($item['level']??'risk')).' risk</span></div><div class="gdcp-cc-actions">'; if(!$lead){echo '<select class="gdcp-cc-lead" data-project="'.intval($pid).'"><option value="">Assign lead…</option>';foreach($assignable as $u)echo '<option value="'.intval($u->ID).'">'.esc_html($u->display_name).'</option>';echo '</select>';} echo '<button type="button" class="button gdcp-cc-view" data-project="'.intval($pid).'">View</button><button type="button" class="button gdcp-cc-action" data-op="notify_client" data-project="'.intval($pid).'">Remind</button><button type="button" class="button gdcp-cc-action" data-op="advance_stage" data-project="'.intval($pid).'">Advance</button><button type="button" class="button gdcp-cc-action" data-op="start_revision" data-project="'.intval($pid).'">Revision</button></div></div>'; }
    foreach($failed_queue as $q){$qp=$q->project_id?gd_client_portal_get_project_by_id($q->project_id):false;echo '<div class="gdcp-cc-action-row automation"><div><strong>Automation #'.intval($q->id).'</strong><span>'.esc_html($qp?$qp->title:'System workflow').' · Failed</span></div><div class="gdcp-cc-actions"><button type="button" class="button gdcp-cc-action" data-op="retry_automation" data-id="'.intval($q->id).'">Retry</button><button type="button" class="button gdcp-cc-action" data-op="cancel_automation" data-id="'.intval($q->id).'">Cancel</button></div></div>';}
    if(!$attention&&!$failed_queue)echo '<p class="gdcp-cc-empty">Nothing requires immediate action.</p>'; echo '</div></section><section class="gdcp-cc-panel gdcp-cc-executive-actions"><div class="gdcp-cc-section-head"><div><h2>Executive action &amp; approval center</h2><p>Handle project approvals, accepted quotes and agreement follow-ups without leaving the Command Center.</p></div></div><div class="gdcp-cc-unified-list">';
    $exec_actions=gd_client_portal_command_center_executive_actions(20);
    foreach($exec_actions as $item){
        echo '<div class="gdcp-cc-unified-row"><div class="gdcp-cc-unified-icon '.esc_attr($item['priority']).'">'.esc_html(strtoupper(substr($item['kind'],0,1))).'</div><div class="gdcp-cc-unified-main"><strong>'.esc_html($item['title']).'</strong><span>'.esc_html($item['meta']).'</span></div><div class="gdcp-cc-unified-actions">';
        if($item['kind']==='approval') echo '<button type="button" class="button gdcp-cc-view" data-project="'.intval($item['project_id']).'">Open project</button>';
        elseif($item['kind']==='quote') echo '<button type="button" class="button button-primary gdcp-cc-action" data-op="create_contract" data-id="'.intval($item['id']).'">Create agreement</button><a class="button" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-billing')).'">Review quote</a>';
        elseif($item['kind']==='contract') echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-contracts')).'">Open agreement</a>';
        echo '</div></div>';
    }
    if(!$exec_actions) echo '<p class="gdcp-cc-empty">No executive approvals or commercial follow-ups require attention.</p>';
    echo '</div></section><section class="gdcp-cc-panel gdcp-cc-unified"><div class="gdcp-cc-section-head"><div><h2>Unified operations queue</h2><p>Projects, support, automation and billing items that need a decision or follow-up.</p></div></div><div class="gdcp-cc-unified-list">';
    $unified=gd_client_portal_command_center_unified_queue(30);
    foreach($unified as $item){
        echo '<div class="gdcp-cc-unified-row"><div class="gdcp-cc-unified-icon '.esc_attr($item['priority']).'">'.esc_html(strtoupper(substr($item['kind'],0,1))).'</div><div class="gdcp-cc-unified-main"><strong>'.esc_html($item['title']).'</strong><span>'.esc_html($item['meta']).'</span></div><div class="gdcp-cc-unified-actions">';
        if($item['kind']==='project') echo '<button type="button" class="button gdcp-cc-view" data-project="'.intval($item['project_id']).'">Open</button><button type="button" class="button gdcp-cc-action" data-op="notify_client" data-project="'.intval($item['project_id']).'">Remind</button>';
        elseif($item['kind']==='ticket') echo '<button type="button" class="button gdcp-cc-action" data-op="update_ticket" data-id="'.intval($item['id']).'" data-status="in_progress">Work ticket</button>';
        elseif($item['kind']==='automation' && $item['action']==='retry_automation') echo '<button type="button" class="button gdcp-cc-action" data-op="retry_automation" data-id="'.intval($item['id']).'">Retry</button><button type="button" class="button gdcp-cc-action" data-op="cancel_automation" data-id="'.intval($item['id']).'">Cancel</button>';
        elseif($item['kind']==='invoice') echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-billing')).'">Open billing</a>';
        echo '</div></div>';
    }
    if(!$unified) echo '<p class="gdcp-cc-empty">The unified operations queue is clear.</p>';
    echo '</div></section><section class="gdcp-cc-panel"><h2>Command shortcuts</h2><div class="gdcp-cc-links">';
    foreach ($links as $label=>$page) echo '<a class="button" href="'.esc_url(admin_url('admin.php?page='.$page)).'">'.esc_html($label).'</a>';
    echo '</div></section><div class="gdcp-cc-drawer-backdrop" hidden></div><aside class="gdcp-cc-drawer" aria-hidden="true"><button type="button" class="gdcp-cc-drawer-close" aria-label="Close">×</button><div class="gdcp-cc-drawer-body"><p>Loading project…</p></div></aside></div>';
}

add_action('admin_menu', function(){
    add_submenu_page('gd-client-portal', 'Command Center', 'Command Center', 'gd_client_portal_access_admin', 'gd-client-portal-command-center', 'gd_client_portal_command_center_admin');
}, 25);
add_shortcode('gd_command_center', 'gd_client_portal_command_center_admin');

function gd_client_portal_register_command_center_assets(){
    wp_register_style('gd-client-portal-command-center', GD_CLIENT_PORTAL_URL.'assets/command-center.css', array('gd-client-portal'), GD_CLIENT_PORTAL_VERSION);
    wp_enqueue_style('gd-client-portal-command-center');
    wp_enqueue_script('gd-client-portal-command-center', GD_CLIENT_PORTAL_URL.'assets/command-center.js', array(), GD_CLIENT_PORTAL_VERSION, true);
    wp_localize_script('gd-client-portal-command-center','GDCPCommandCenter',array('ajax_url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('gd_client_portal_command_center')));
}
add_action('admin_enqueue_scripts','gd_client_portal_register_command_center_assets');
