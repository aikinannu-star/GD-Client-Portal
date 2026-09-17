<?php
/**
 * GD Client Portal v4.1 - Advanced Automation & Workflow Builder.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_automation_table($suffix = 'rules') {
    global $wpdb;
    return $wpdb->prefix . 'gd_portal_automation_' . sanitize_key($suffix);
}

function gd_client_portal_automation_activate_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    $rules = gd_client_portal_automation_table('rules');
    $logs = gd_client_portal_automation_table('logs');
    $templates = gd_client_portal_automation_table('templates');
    $queue = gd_client_portal_automation_table('queue');
    dbDelta("CREATE TABLE {$rules} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(190) NOT NULL,
        event_key varchar(100) NOT NULL,
        conditions longtext NULL,
        actions longtext NULL,
        status varchar(20) NOT NULL DEFAULT 'active',
        run_count bigint(20) unsigned NOT NULL DEFAULT 0,
        last_run_at datetime NULL,
        created_by bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id), KEY event_key(event_key), KEY status(status)
    ) {$charset};");
    dbDelta("CREATE TABLE {$logs} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        rule_id bigint(20) unsigned NOT NULL DEFAULT 0,
        event_key varchar(100) NOT NULL,
        execution_id varchar(64) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'success',
        tenant_id bigint(20) unsigned NOT NULL DEFAULT 0,
        project_id bigint(20) unsigned NOT NULL DEFAULT 0,
        summary varchar(255) NOT NULL,
        details longtext NULL,
        payload longtext NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id), KEY rule_id(rule_id), KEY event_key(event_key), KEY execution_id(execution_id), KEY tenant_id(tenant_id), KEY project_id(project_id), KEY status(status), KEY created_at(created_at)
    ) {$charset};");
    dbDelta("CREATE TABLE {$queue} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        rule_id bigint(20) unsigned NOT NULL DEFAULT 0,
        action_index int unsigned NOT NULL DEFAULT 0,
        execution_id varchar(64) NOT NULL,
        event_key varchar(100) NOT NULL,
        payload longtext NULL,
        action longtext NULL,
        tenant_id bigint(20) unsigned NOT NULL DEFAULT 0,
        project_id bigint(20) unsigned NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'queued',
        attempts int unsigned NOT NULL DEFAULT 0,
        max_attempts int unsigned NOT NULL DEFAULT 3,
        run_at datetime NOT NULL,
        last_error text NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id), KEY rule_id(rule_id), KEY execution_id(execution_id), KEY status(status), KEY run_at(run_at), KEY project_id(project_id)
    ) {$charset};");
    dbDelta("CREATE TABLE {$templates} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(190) NOT NULL,
        description text NULL,
        event_key varchar(100) NOT NULL,
        conditions longtext NULL,
        actions longtext NULL,
        created_by bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id), KEY event_key(event_key)
    ) {$charset};");
}

function gd_client_portal_automation_maybe_upgrade(){if(absint(get_option('gd_client_portal_automation_schema_version',0))<4){gd_client_portal_automation_activate_tables();global $wpdb;$qt=gd_client_portal_automation_table('queue');$wpdb->query("ALTER TABLE {$qt} ADD COLUMN locked_at datetime NULL AFTER updated_at");$wpdb->query("ALTER TABLE {$qt} ADD COLUMN last_attempt_at datetime NULL AFTER locked_at");update_option('gd_client_portal_automation_schema_version',4,false);}if(!wp_next_scheduled('gd_client_portal_automation_daily'))wp_schedule_event(time()+300,'daily','gd_client_portal_automation_daily');}
add_action('plugins_loaded','gd_client_portal_automation_maybe_upgrade',30);

function gd_client_portal_automation_events() { return array(
    'project_created'=>'Project created','project_claimed'=>'Project claimed','project_stage_changed'=>'Project stage changed','requirements_submitted'=>'Requirements submitted','intake_completed'=>'Service intake completed','quote_accepted'=>'Quote accepted','payment_received'=>'Payment received','payment_overdue'=>'Payment overdue','approval_requested'=>'Approval requested','approval_decided'=>'Approval decided','delivery_published'=>'Delivery published','delivery_finalized'=>'Delivery finalized','project_assigned'=>'Project assigned','sla_escalated'=>'SLA escalated','client_reminder_sent'=>'Client reminder sent','service_request_created'=>'Service request created','support_ticket_created'=>'Support ticket created','support_ticket_status_changed'=>'Support ticket status changed','feedback_submitted'=>'Feedback submitted','document_requested'=>'Document requested','document_uploaded'=>'Document uploaded','task_created'=>'Task created','lifecycle_gap'=>'Lifecycle gap detected','client_experience_at_risk'=>'Client experience at risk','daily'=>'Daily automation check'
); }
function gd_client_portal_automation_actions() { return array(
    'notify_client'=>'Notify client','notify_team'=>'Notify project team','email_client'=>'Email client','email_admin'=>'Email tenant admin','advance_stage'=>'Advance project stage','create_task'=>'Create project task','audit'=>'Record audit event'
); }
function gd_client_portal_automation_operators() { return array('equals'=>'equals','not_equals'=>'not equals','contains'=>'contains','greater'=>'greater than','less'=>'less than','in'=>'is one of'); }
function gd_client_portal_automation_default_rules() { return array(
    array('name'=>'Client experience risk alert','event_key'=>'client_experience_at_risk','conditions'=>array('logic'=>'AND','groups'=>array()),'actions'=>array(array('type'=>'notify_team','title'=>'Client experience needs attention','body'=>'A client experience signal has fallen below the recommended threshold. Review the project before contacting the client.'))),
    array('name'=>'New project team alert','event_key'=>'project_created','conditions'=>array('logic'=>'AND','groups'=>array()),'actions'=>array(array('type'=>'notify_team','title'=>'New project created','body'=>'A new project is ready for your team.'))),
    array('name'=>'Requirements submitted alert','event_key'=>'requirements_submitted','conditions'=>array('logic'=>'AND','groups'=>array()),'actions'=>array(array('type'=>'notify_team','title'=>'Requirements submitted','body'=>'Project requirements are ready for review.'))),
    array('name'=>'Payment received confirmation','event_key'=>'payment_received','conditions'=>array('logic'=>'AND','groups'=>array()),'actions'=>array(array('type'=>'notify_client','title'=>'Payment received','body'=>'Your payment has been recorded successfully.'))),
    array('name'=>'SLA escalation alert','event_key'=>'sla_escalated','conditions'=>array('logic'=>'AND','groups'=>array()),'actions'=>array(array('type'=>'notify_team','title'=>'SLA escalation','body'=>'A project requires immediate attention.'))),
    array('name'=>'Project completion feedback prompt','event_key'=>'delivery_finalized','conditions'=>array('logic'=>'AND','groups'=>array()),'actions'=>array(array('type'=>'notify_client','title'=>'Project completed','body'=>'Your project has been finalized. We would appreciate your feedback.')))
); }
function gd_client_portal_automation_seed_defaults() {
    if (get_option('gd_client_portal_automation_seeded')) return;
    foreach (gd_client_portal_automation_default_rules() as $r) { gdcp_automation_service()->create_rule(array('name'=>$r['name'],'event_key'=>$r['event_key'],'conditions'=>wp_json_encode($r['conditions']),'actions'=>wp_json_encode($r['actions']),'status'=>'active','created_by'=>gd_client_portal_cached_current_user_id(),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'))); }
    update_option('gd_client_portal_automation_seeded', 1);
}
function gd_client_portal_automation_get_rule($id) { return gdcp_automation_service()->get_rule($id); }
function gd_client_portal_automation_decode($value, $fallback=array()) { $v=json_decode((string)$value,true); return is_array($v)?$v:$fallback; }
function gd_client_portal_automation_value($payload,$key) {
    if (is_array($payload) && array_key_exists($key,$payload)) return $payload[$key];
    $parts=explode('.',$key); $v=$payload;
    foreach($parts as $part){ if(is_array($v)&&array_key_exists($part,$v))$v=$v[$part]; elseif(is_object($v)&&isset($v->{$part}))$v=$v->{$part}; else return null; }
    return $v;
}
function gd_client_portal_automation_condition_match($condition,$payload) {
    $field=sanitize_key($condition['field']??''); $op=sanitize_key($condition['operator']??'equals'); $expected=$condition['value']??''; if(!$field)return false;
    $actual=gd_client_portal_automation_value($payload,$field); if($actual===null)return false;
    $a=is_scalar($actual)?(string)$actual:''; $e=is_scalar($expected)?(string)$expected:'';
    switch($op){
        case 'not_equals': return $a!==$e;
        case 'contains': return stripos($a,$e)!==false;
        case 'greater': return (float)$actual>(float)$expected;
        case 'less': return (float)$actual<(float)$expected;
        case 'in': return in_array($a,array_map('strval',(array)$expected),true);
        default: return $a===$e;
    }
}
function gd_client_portal_automation_conditions_match($conditions,$payload) {
    if(empty($conditions))return true;
    if(isset($conditions['logic'])||isset($conditions['groups'])){
        $logic=strtoupper($conditions['logic']??'AND'); $groups=(array)($conditions['groups']??array()); $results=array();
        foreach($groups as $group){$gl=strtoupper($group['logic']??'AND');$cr=array();foreach((array)($group['conditions']??array()) as $c)$cr[]=gd_client_portal_automation_condition_match($c,$payload);$groupResult=empty($cr)?true:($gl==='OR'?in_array(true,$cr,true):!in_array(false,$cr,true));$results[]=$groupResult;}
        if(isset($conditions['conditions'])&&is_array($conditions['conditions']))foreach($conditions['conditions'] as $c)$results[]=gd_client_portal_automation_condition_match($c,$payload);
        return empty($results)?true:($logic==='OR'?in_array(true,$results,true):!in_array(false,$results,true));
    }
    return true;
}
function gd_client_portal_automation_project($payload){$id=absint(gd_client_portal_automation_value($payload,'project_id'));return $id&&function_exists('gd_client_portal_get_project_by_id')?gd_client_portal_get_project_by_id($id):false;}
function gd_client_portal_automation_recipients($project,$type){
    if(!$project)return array();$ids=array();if($type==='client')$ids[]=absint($project->user_id);if($type==='team'){if(function_exists('gd_client_portal_get_project_assignments'))foreach((array)gd_client_portal_get_project_assignments($project->id) as $a)if(!empty($a->user_id))$ids[]=absint($a->user_id);if(function_exists('gd_client_portal_get_project_lead')){$l=gd_client_portal_get_project_lead($project->id);if($l)$ids[]=absint($l->user_id);}if(function_exists('gd_client_portal_notification_recipients'))$ids=array_merge($ids,(array)gd_client_portal_notification_recipients($project,0));}return array_values(array_unique(array_filter(array_map('absint',$ids))));
}
function gd_client_portal_automation_internal_advance_stage($project,$requested='') {
    if(!$project)return false;
    $workflow=function_exists('gd_client_portal_get_workflow')?gd_client_portal_get_workflow($project->service_type):array();
    if(!$workflow)return false;
    $stage=$requested?sanitize_key($requested):'';
    if(!$stage){$idx=array_search($project->current_stage,$workflow,true);$stage=($idx!==false&&isset($workflow[$idx+1]))?$workflow[$idx+1]:'';}
    if(!$stage||!in_array($stage,$workflow,true))return false;
    $progress=function_exists('gd_client_portal_auto_progress')?gd_client_portal_auto_progress($project->service_type,$stage):null;
    if(function_exists('gdcp_service')){
        $service=gdcp_service('project');
        if($service && method_exists($service,'advance_stage_for_automation')){
            return (bool)$service->advance_stage_for_automation(absint($project->id),$stage,$progress);
        }
    }
    return false;
}
function gd_client_portal_automation_action($action,$payload,$rule,$execution_id='',$dry_run=false){
    $project=gd_client_portal_automation_project($payload);
    if(function_exists('gd_client_portal_workflow_smart_action')) $action=gd_client_portal_workflow_smart_action($action,$payload,$project,$execution_id);
    $type=sanitize_key($action['type']??'');$title=sanitize_text_field($action['title']??$rule->name);$body=sanitize_textarea_field($action['body']??'Automation executed by GD Client Portal.');$delay=max(0,absint($action['delay_minutes']??0));
    if($delay&&!$dry_run){$run_at=time()+($delay*60);$queue_id=gdcp_automation_service()->create_queue(array('rule_id'=>absint($rule->id),'action_index'=>absint($action['_index']??0),'execution_id'=>$execution_id,'event_key'=>sanitize_key($rule->event_key),'payload'=>wp_json_encode($payload),'action'=>wp_json_encode($action),'tenant_id'=>$project?absint($project->tenant_id):absint($payload['tenant_id']??0),'project_id'=>$project?absint($project->id):absint($payload['project_id']??0),'status'=>'queued','attempts'=>0,'max_attempts'=>min(10,max(1,absint($action['max_attempts']??3))),'run_at'=>gmdate('Y-m-d H:i:s',$run_at),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')));wp_schedule_single_event($run_at,'gd_client_portal_automation_queue_runner',array($queue_id));return array('status'=>'scheduled','message'=>'Action queued for '.wp_date('Y-m-d H:i:s',$run_at),'queue_id'=>absint($queue_id));}
    if($dry_run)return array('status'=>'dry_run','message'=>'Would execute: '.$type);
    if($type==='notify_client'||$type==='email_client'){if(!$project)return array('status'=>'skipped','message'=>'Project not found.');foreach(gd_client_portal_automation_recipients($project,'client') as $uid){if($type==='notify_client'&&function_exists('gd_client_portal_create_notification'))gd_client_portal_create_notification($uid,$title,$body,'automation',$project->id,$project->tenant_id);if($type==='email_client'){ $u=gd_client_portal_cached_user($uid);if($u&&$u->user_email)wp_mail($u->user_email,$title,$body);}}return array('status'=>'success','message'=>'Client action completed.');}
    if($type==='notify_team'||$type==='email_admin'){ $ids=$project?gd_client_portal_automation_recipients($project,'team'):array();if(!$project){$tenant=absint($payload['tenant_id']??0);if($tenant)$ids=get_users(array('fields'=>'ID','meta_key'=>'gd_client_portal_tenant_id','meta_value'=>$tenant));}foreach($ids as $uid){if($type==='notify_team'&&function_exists('gd_client_portal_create_notification'))gd_client_portal_create_notification($uid,$title,$body,'automation',$project?$project->id:0,$project?$project->tenant_id:absint($payload['tenant_id']??0));if($type==='email_admin'){ $u=gd_client_portal_cached_user($uid);if($u&&$u->user_email)wp_mail($u->user_email,$title,$body);}}return array('status'=>'success','message'=>'Team action completed.');}
    if($type==='advance_stage'&&$project){$ok=gd_client_portal_automation_internal_advance_stage($project,sanitize_key($action['stage']??''));return array('status'=>$ok?'success':'skipped','message'=>$ok?'Project stage advanced.':'No valid next stage.');}
    if($type==='create_task'&&$project&&function_exists('gdcp_collaboration_service')){$assigned=absint($action['assigned_to']??0);if($assigned){$u=gd_client_portal_cached_user($assigned);$tenant=(int)get_user_meta($assigned,'gd_client_portal_tenant_id',true);if(!$u||$tenant!==absint($project->tenant_id)||user_can($u,'manage_options'))$assigned=0;}$due=null;if(!empty($action['due_days']))$due=gmdate('Y-m-d H:i:s',current_time('timestamp')+(absint($action['due_days'])*DAY_IN_SECONDS));$new=gdcp_collaboration_service()->create_task($project,array('title'=>$title,'description'=>$body,'status'=>'todo','priority'=>in_array($action['priority']??'normal',array('low','normal','high','urgent'),true)?$action['priority']:'normal','assigned_to'=>$assigned,'due_date'=>$due,'created_by'=>gd_client_portal_cached_current_user_id(),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')));return array('status'=>$new?'success':'failed','message'=>$new?'Task created.':'Task creation failed.');}
    if($type==='audit'&&function_exists('gd_client_portal_audit_log')){gd_client_portal_audit_log('automation_executed',$title,$project?$project->id:0,$project?$project->tenant_id:absint($payload['tenant_id']??0),$body,'automation',$rule->id,array('event'=>$rule->event_key,'execution_id'=>$execution_id));return array('status'=>'success','message'=>'Audit entry recorded.');}
    return array('status'=>'skipped','message'=>'Unsupported or incomplete action.');
}
// Compatibility-safe logger (kept separate to avoid malformed field arrays in older WP versions).
function gd_client_portal_automation_write_log($rule,$event,$execution_id,$status,$payload,$summary,$details='') { $project=gd_client_portal_automation_project($payload); return gdcp_automation_service()->create_log(array('rule_id'=>absint($rule->id),'event_key'=>sanitize_key($event),'execution_id'=>sanitize_text_field($execution_id),'status'=>sanitize_key($status),'tenant_id'=>$project?absint($project->tenant_id):absint($payload['tenant_id']??0),'project_id'=>$project?absint($project->id):absint($payload['project_id']??0),'summary'=>sanitize_text_field($summary),'details'=>sanitize_textarea_field($details),'payload'=>wp_json_encode($payload),'created_at'=>current_time('mysql'))); }
function gd_client_portal_automation_run($event,$payload=array(),$options=array()) {
    static $running=false;if($running)return array();$event=sanitize_key($event);if(!$event)return array();$payload['_automation_event']=$event;$options=wp_parse_args($options,array('dry_run'=>false,'rule_id'=>0));$rules=gdcp_automation_service()->list_rules('active'); if(absint($options['rule_id'])) $rules=array_values(array_filter($rules,function($r)use($options){return absint($r->id)===absint($options['rule_id']);})); $rules=array_values(array_filter($rules,function($r)use($event){return $r->event_key===$event;})); usort($rules,function($a,$b){return absint($a->id)<=>absint($b->id);});if(!$rules)return array();$running=true;$results=array();
    foreach($rules as $rule){$conditions=gd_client_portal_automation_decode($rule->conditions);if(!gd_client_portal_automation_conditions_match($conditions,$payload)){continue;}$execution_id=wp_generate_uuid4();$actions=gd_client_portal_automation_decode($rule->actions);$action_results=array();$failed=false;foreach($actions as $ai=>$action){$action['_index']=$ai;$result=gd_client_portal_automation_action($action,$payload,$rule,$execution_id,!empty($options['dry_run']));$action_results[]=$result;if(($result['status']??'')==='failed')$failed=true;}$status=$failed?'failed':(!empty($options['dry_run'])?'dry_run':'success');$summary=$failed?'Automation completed with errors':(!empty($options['dry_run'])?'Test run':'Automation executed');if(empty($options['dry_run'])){gdcp_automation_service()->increment_run($rule->id,current_time('mysql'));gd_client_portal_automation_write_log($rule,$event,$execution_id,$status,$payload,$summary,wp_json_encode($action_results));}else{gd_client_portal_automation_write_log($rule,$event,$execution_id,'dry_run',$payload,$summary,wp_json_encode($action_results));}$results[]=array('rule_id'=>absint($rule->id),'execution_id'=>$execution_id,'status'=>$status,'actions'=>$action_results);}
    $running=false;return $results;
}
function gd_client_portal_automation_recover_stale_queue() {
    $rows=gdcp_automation_service()->stale_queue(50);
    foreach((array)$rows as $row){
        if(absint($row->attempts) >= absint($row->max_attempts)) {
            gdcp_automation_service()->update_queue($row->id,array('status'=>'failed','last_error'=>'Execution lease expired after maximum attempts.','updated_at'=>current_time('mysql')));
        } else {
            gdcp_automation_service()->update_queue($row->id,array('status'=>'queued','run_at'=>current_time('mysql'),'last_error'=>'Recovered from a stale execution lease.','updated_at'=>current_time('mysql')));
            wp_schedule_single_event(time()+5,'gd_client_portal_automation_queue_runner',array(absint($row->id)));
        }
    }
    $due=gdcp_automation_service()->due_queue(50);
    foreach((array)$due as $id) wp_schedule_single_event(time()+1,'gd_client_portal_automation_queue_runner',array(absint($id)));
}
add_action('gd_client_portal_automation_reliability_tick','gd_client_portal_automation_recover_stale_queue');

function gd_client_portal_automation_queue_runner($queue_id){
    $row=gdcp_automation_service()->get_queue($queue_id);
    if(!$row||$row->status!=='queued')return; $claimed=gdcp_automation_service()->claim_queue($row->id); if(!$claimed)return; $row=gdcp_automation_service()->get_queue($queue_id); $rule=gd_client_portal_automation_get_rule($row->rule_id); if(!$rule||$rule->status!=='active'){ gdcp_automation_service()->update_queue($row->id,array('status'=>'cancelled','updated_at'=>current_time('mysql'))); return; }
    $action=gd_client_portal_automation_decode($row->action); $payload=gd_client_portal_automation_decode($row->payload); $action['delay_minutes']=0; $attempts=absint($row->attempts)+1;
    gdcp_automation_service()->update_queue($row->id,array('status'=>'running','attempts'=>$attempts,'updated_at'=>current_time('mysql')));
    $result=gd_client_portal_automation_action($action,$payload,$rule,$row->execution_id,false); $status=$result['status']??'failed';
    if(in_array($status,array('success','skipped','dry_run'),true)){ gdcp_automation_service()->update_queue($row->id,array('status'=>$status,'updated_at'=>current_time('mysql'))); gd_client_portal_automation_write_log($rule,$row->event_key,$row->execution_id,$status,$payload,'Queued action executed',wp_json_encode($result)); return; }
    if($attempts < absint($row->max_attempts)){ $next=time()+min(3600,60*pow(2,$attempts-1)); gdcp_automation_service()->update_queue($row->id,array('status'=>'queued','run_at'=>gmdate('Y-m-d H:i:s',$next),'last_error'=>sanitize_textarea_field($result['message']??'Action failed'),'locked_at'=>null,'updated_at'=>current_time('mysql'))); wp_schedule_single_event($next,'gd_client_portal_automation_queue_runner',array($row->id)); }
    else { gdcp_automation_service()->update_queue($row->id,array('status'=>'failed','last_error'=>sanitize_textarea_field($result['message']??'Action failed'),'locked_at'=>null,'updated_at'=>current_time('mysql'))); gd_client_portal_automation_write_log($rule,$row->event_key,$row->execution_id,'failed',$payload,'Queued action failed',wp_json_encode($result)); }
}
add_action('gd_client_portal_automation_queue_runner','gd_client_portal_automation_queue_runner',10,1);
function gd_client_portal_automation_delayed_action($rule_id,$action,$payload,$execution_id=''){ $rule=gd_client_portal_automation_get_rule($rule_id);if(!$rule||$rule->status!=='active')return;gd_client_portal_automation_action($action,$payload,$rule,$execution_id,false); }
add_action('gd_client_portal_automation_delayed_action','gd_client_portal_automation_delayed_action',10,4);
function gd_client_portal_automation_hook_project($project){if($project)gd_client_portal_automation_run('project_created',array('project_id'=>$project->id,'tenant_id'=>$project->tenant_id,'status'=>$project->status,'stage'=>$project->current_stage,'progress'=>$project->progress,'service_type'=>$project->service_type));}
function gd_client_portal_automation_hook_claimed($project_id,$user_id){$p=gd_client_portal_get_project_by_id($project_id);gd_client_portal_automation_run('project_claimed',array('project_id'=>$project_id,'user_id'=>$user_id,'tenant_id'=>$p?$p->tenant_id:0));}
function gd_client_portal_automation_hook_stage($project_id,$stage,$progress=null){$p=gd_client_portal_get_project_by_id($project_id);gd_client_portal_automation_run('project_stage_changed',array('project_id'=>$project_id,'tenant_id'=>$p?$p->tenant_id:0,'stage'=>$stage,'progress'=>$progress,'status'=>$p?$p->status:''));}
function gd_client_portal_automation_hook_project_event($event,$project_id){$p=gd_client_portal_get_project_by_id($project_id);gd_client_portal_automation_run($event,array('project_id'=>$project_id,'tenant_id'=>$p?$p->tenant_id:0,'stage'=>$p?$p->current_stage:'','status'=>$p?$p->status:''));}
add_action('gd_client_portal_project_created','gd_client_portal_automation_hook_project',110);add_action('gd_client_portal_project_claimed','gd_client_portal_automation_hook_claimed',110,2);add_action('gd_client_portal_project_stage_changed','gd_client_portal_automation_hook_stage',110,3);add_action('gd_client_portal_requirements_submitted',function($p){gd_client_portal_automation_run('requirements_submitted',array('project_id'=>$p?$p->id:0,'tenant_id'=>$p?$p->tenant_id:0,'stage'=>$p?$p->current_stage:''));},110);add_action('gd_client_portal_intake_completed',function($sid,$pid,$uid){gd_client_portal_automation_hook_project_event('intake_completed',$pid);},110,3);add_action('gd_client_portal_quote_accepted',function($q){gd_client_portal_automation_run('quote_accepted',array('project_id'=>$q?$q->project_id:0,'tenant_id'=>$q?$q->tenant_id:0,'quote_id'=>$q?$q->id:0));},110);add_action('gd_client_portal_payment_received',function($payment){$pid=is_object($payment)?absint($payment->project_id):absint($payment);$p=$pid?gd_client_portal_get_project_by_id($pid):false;gd_client_portal_automation_run('payment_received',array('project_id'=>$pid,'tenant_id'=>$p?$p->tenant_id:0,'payment_id'=>is_object($payment)?absint($payment->id):0,'amount'=>is_object($payment)?(float)$payment->amount:0));},110);add_action('gd_client_portal_approval_requested',function($pid){gd_client_portal_automation_hook_project_event('approval_requested',$pid);},110);add_action('gd_client_portal_approval_decided',function($pid){gd_client_portal_automation_hook_project_event('approval_decided',$pid);},110);add_action('gd_client_portal_delivery_published',function($pid){gd_client_portal_automation_hook_project_event('delivery_published',$pid);},110);add_action('gd_client_portal_delivery_finalized',function($pid){gd_client_portal_automation_hook_project_event('delivery_finalized',$pid);},110);add_action('gd_client_portal_project_assigned',function($pid){gd_client_portal_automation_hook_project_event('project_assigned',$pid);},110);add_action('gd_client_portal_sla_escalated',function($pid){gd_client_portal_automation_hook_project_event('sla_escalated',$pid);},110);add_action('gd_client_portal_client_reminder_sent',function($pid){gd_client_portal_automation_hook_project_event('client_reminder_sent',$pid);},110);add_action('gd_client_portal_feedback_submitted',function($id,$pid,$tid){$p=$pid?gd_client_portal_get_project_by_id($pid):false;gd_client_portal_automation_run('feedback_submitted',array('project_id'=>$pid,'ticket_id'=>$tid,'tenant_id'=>$p?$p->tenant_id:0));},110,3);add_action('gd_client_portal_service_request_created',function($id,$pid,$tenant){gd_client_portal_automation_run('service_request_created',array('project_id'=>$pid,'tenant_id'=>$tenant,'request_id'=>$id));},110,3);add_action('gd_client_portal_support_ticket_created',function($id,$pid,$tenant){gd_client_portal_automation_run('support_ticket_created',array('project_id'=>$pid,'tenant_id'=>$tenant,'ticket_id'=>$id));},110,3);add_action('gd_client_portal_support_ticket_status_changed',function($id,$pid,$status,$tenant=0){gd_client_portal_automation_run('support_ticket_status_changed',array('project_id'=>$pid,'tenant_id'=>$tenant,'ticket_id'=>$id,'status'=>$status));},110,4);add_action('gd_client_portal_task_created',function($id,$pid,$tenant){gd_client_portal_automation_run('task_created',array('project_id'=>$pid,'tenant_id'=>$tenant,'task_id'=>$id));},110,3);
add_action('gd_client_portal_sla_daily',function(){gd_client_portal_automation_run('daily',array('tenant_id'=>0,'date'=>current_time('Y-m-d')));global $wpdb;if(function_exists('gd_client_portal_billing_invoices_table')){$t=gd_client_portal_billing_invoices_table();$today=current_time('Y-m-d');$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE status IN ('unpaid','part_paid') AND due_date IS NOT NULL AND due_date < %s",$today));foreach((array)$rows as $r)gd_client_portal_automation_run('payment_overdue',array('invoice_id'=>$r->id,'project_id'=>$r->project_id,'tenant_id'=>$r->tenant_id,'user_id'=>$r->user_id,'amount_due'=>max(0,(float)$r->total-(float)$r->amount_paid)));}},110);

function gd_client_portal_automation_parse_conditions(){
    $logic=strtoupper(sanitize_key($_POST['condition_logic']??'AND'));$groups=array();$group_logics=(array)($_POST['group_logic']??array());$fields=(array)($_POST['condition_field']??array());$ops=(array)($_POST['condition_operator']??array());$vals=(array)($_POST['condition_value']??array());$gids=(array)($_POST['condition_group']??array());foreach($fields as $i=>$field){$field=sanitize_key(wp_unslash($field));if(!$field)continue;$gid=absint($gids[$i]??0);if(!isset($groups[$gid]))$groups[$gid]=array('logic'=>strtoupper(sanitize_key($group_logics[$gid]??'AND'))==='OR'?'OR':'AND','conditions'=>array());$groups[$gid]['conditions'][]=array('field'=>$field,'operator'=>sanitize_key($ops[$i]??'equals'),'value'=>sanitize_text_field(wp_unslash($vals[$i]??'')));}return array('logic'=>$logic==='OR'?'OR':'AND','groups'=>array_values($groups));
}
function gd_client_portal_automation_parse_actions(){
    $actions=array();$types=(array)($_POST['action_type']??array());$titles=(array)($_POST['action_title']??array());$bodies=(array)($_POST['action_body']??array());$delays=(array)($_POST['action_delay']??array());$stages=(array)($_POST['action_stage']??array());$priorities=(array)($_POST['action_priority']??array());$assignees=(array)($_POST['action_assigned_to']??array());$due=(array)($_POST['action_due_days']??array());$allowed=gd_client_portal_automation_actions();foreach($types as $i=>$type){$type=sanitize_key($type);if(!isset($allowed[$type]))continue;$actions[]=array('type'=>$type,'title'=>sanitize_text_field(wp_unslash($titles[$i]??'')),'body'=>sanitize_textarea_field(wp_unslash($bodies[$i]??'')),'delay_minutes'=>min(43200,absint($delays[$i]??0)),'stage'=>sanitize_key($stages[$i]??''),'priority'=>in_array($priorities[$i]??'normal',array('low','normal','high','urgent'),true)?$priorities[$i]:'normal','assigned_to'=>absint($assignees[$i]??0),'due_days'=>min(365,absint($due[$i]??0)));}return $actions;
}
function gd_client_portal_automation_admin(){
    if(!gd_client_portal_user_can_access_admin())wp_die('Access denied.');$notice='';
    if(isset($_POST['gdcp_auto_save'])&&check_admin_referer('gdcp_automation_save')){$id=absint($_POST['rule_id']??0);$name=sanitize_text_field(wp_unslash($_POST['name']??''));$event=sanitize_key($_POST['event_key']??'');$status=in_array($_POST['status']??'',array('active','paused'),true)?$_POST['status']:'active';$conditions=gd_client_portal_automation_parse_conditions();$actions=gd_client_portal_automation_parse_actions();if($name&&isset(gd_client_portal_automation_events()[$event])&&!empty($actions)){$data=array('name'=>$name,'event_key'=>$event,'conditions'=>wp_json_encode($conditions),'actions'=>wp_json_encode($actions),'status'=>$status,'updated_at'=>current_time('mysql'));if($id){if(function_exists('gd_client_portal_automation_snapshot_rule'))gd_client_portal_automation_snapshot_rule($id,'before_update');gdcp_automation_service()->update_rule($id,$data);if(function_exists('gd_client_portal_automation_snapshot_rule'))gd_client_portal_automation_snapshot_rule($id,'after_update');}else{$data['created_by']=gd_client_portal_cached_current_user_id();$data['created_at']=current_time('mysql');$new_id=gdcp_automation_service()->create_rule($data);if($new_id&&function_exists('gd_client_portal_automation_snapshot_rule'))gd_client_portal_automation_snapshot_rule(absint($new_id),'created');} $notice='<div class="notice notice-success"><p>Automation saved.</p></div>';}}
    if(isset($_POST['gdcp_auto_use_template'])&&check_admin_referer('gdcp_automation_use_template')){$tpl_id=absint($_POST['template_id']??0);$tpl=gdcp_automation_service()->get_template($tpl_id);if($tpl){$new_id=gdcp_automation_service()->create_rule(array('name'=>$tpl->name,'event_key'=>$tpl->event_key,'conditions'=>$tpl->conditions,'actions'=>$tpl->actions,'status'=>'paused','created_by'=>gd_client_portal_cached_current_user_id(),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')));if($new_id){wp_safe_redirect(admin_url('admin.php?page=gd-client-portal-automation&edit_rule='.$new_id));exit;}}$notice='<div class="notice notice-error"><p>Unable to load workflow template.</p></div>';}
    if(isset($_POST['gdcp_auto_template'])&&check_admin_referer('gdcp_automation_template')){$name=sanitize_text_field(wp_unslash($_POST['template_name']??''));$description=sanitize_textarea_field(wp_unslash($_POST['template_description']??''));$rule=gd_client_portal_automation_get_rule(absint($_POST['template_rule_id']??0));if($rule&&$name){gdcp_automation_service()->create_template(array('name'=>$name,'description'=>$description,'event_key'=>$rule->event_key,'conditions'=>$rule->conditions,'actions'=>$rule->actions,'created_by'=>gd_client_portal_cached_current_user_id(),'created_at'=>current_time('mysql')));$notice='<div class="notice notice-success"><p>Workflow template saved.</p></div>';}}
    if(isset($_GET['delete_rule'])&&check_admin_referer('gdcp_automation_delete_'.absint($_GET['delete_rule']))){gdcp_automation_service()->delete_rule(absint($_GET['delete_rule']));$notice='<div class="notice notice-success"><p>Automation deleted.</p></div>';}
    if(isset($_POST['gdcp_auto_test'])&&check_admin_referer('gdcp_automation_test')){$rule_id=absint($_POST['test_rule_id']??0);$payload=array('project_id'=>absint($_POST['test_project_id']??0),'tenant_id'=>absint($_POST['test_tenant_id']??0),'stage'=>sanitize_key($_POST['test_stage']??''),'status'=>sanitize_key($_POST['test_status']??''),'progress'=>absint($_POST['test_progress']??0),'amount'=>(float)($_POST['test_amount']??0));$test_rule=gd_client_portal_automation_get_rule($rule_id);$results=$test_rule?gd_client_portal_automation_run($test_rule->event_key,$payload,array('rule_id'=>$rule_id,'dry_run'=>true)):array();$notice='<div class="notice notice-info"><p>Test completed. '.esc_html(count($results)).' matching rule(s) executed in dry-run mode.</p></div>';}
    $edit=isset($_GET['edit_rule'])?gd_client_portal_automation_get_rule($_GET['edit_rule']):false;$events=gd_client_portal_automation_events();$actions=gd_client_portal_automation_actions();$operators=gd_client_portal_automation_operators();$rules=gdcp_automation_service()->list_rules();$logs=gdcp_automation_service()->list_logs(25);$templates=gdcp_automation_service()->list_templates(20);
    $decoded_conditions=$edit?gd_client_portal_automation_decode($edit->conditions):array('logic'=>'AND','groups'=>array());$decoded_actions=$edit?gd_client_portal_automation_decode($edit->actions):array(array('type'=>'notify_team','title'=>'','body'=>'','delay_minutes'=>0));
    echo '<div class="wrap gdcp-automation-admin"><h1>Automation & Workflow Builder <span class="gdcp-version-badge">v4.1</span></h1><p>Build event-driven workflows with condition groups, multiple actions, delays, templates, testing and execution history.</p>'.$notice;
    echo '<div class="gdcp-auto-tabs"><a href="#builder">Builder</a><a href="#rules">Rules</a><a href="#logs">Execution history</a><a href="#templates">Templates</a></div>';
    echo '<section id="builder" class="gdcp-auto-panel"><form method="post">'.wp_nonce_field('gdcp_automation_save','_wpnonce',true,false).'<input type="hidden" name="gdcp_auto_save" value="1"><input type="hidden" name="rule_id" value="'.intval($edit?$edit->id:0).'">';
    echo '<div class="gdcp-builder-head"><div><h2>'.($edit?'Edit workflow':'Create workflow').'</h2><p>Define a trigger, optional conditions, then one or more actions.</p></div><label>Status <select name="status"><option value="active" '.selected($edit?$edit->status:'active','active',false).'>Active</option><option value="paused" '.selected($edit?$edit->status:'','paused',false).'>Paused</option></select></label></div>';
    echo '<div class="gdcp-builder-grid"><div><label class="gdcp-field-label">Workflow name<input class="regular-text" name="name" required value="'.esc_attr($edit?$edit->name:'').'" placeholder="e.g. High priority project escalation"></label></div><div><label class="gdcp-field-label">Trigger<select name="event_key">';foreach($events as $k=>$v)echo '<option value="'.esc_attr($k).'" '.selected($edit?$edit->event_key:'',$k,false).'>'.esc_html($v).'</option>';echo '</select></label></div></div>';
    echo '<div class="gdcp-builder-section"><div class="gdcp-section-title"><h3>Conditions</h3><select name="condition_logic"><option value="AND" '.selected($decoded_conditions['logic']??'AND','AND',false).'>ALL groups must match (AND)</option><option value="OR" '.selected($decoded_conditions['logic']??'AND','OR',false).'>ANY group can match (OR)</option></select></div><div id="gdcp-condition-groups">';
    $groups=$decoded_conditions['groups']??array();if(empty($groups))$groups=array(array('logic'=>'AND','conditions'=>array()));foreach($groups as $gi=>$group){echo '<div class="gdcp-condition-group" data-group="'.intval($gi).'" data-next="'.intval(count($group['conditions']??array())).'"><div class="gdcp-group-head"><strong>Condition group '.($gi+1).'</strong><select name="group_logic['.intval($gi).']"><option value="AND" '.selected($group['logic']??'AND','AND',false).'>ALL conditions (AND)</option><option value="OR" '.selected($group['logic']??'AND','OR',false).'>ANY condition (OR)</option></select><button type="button" class="button gdcp-remove-group">Remove group</button></div><div class="gdcp-condition-rows">';$conds=$group['conditions']??array();if(empty($conds))$conds=array(array('field'=>'','operator'=>'equals','value'=>''));foreach($conds as $ci=>$c){echo '<div class="gdcp-condition-row"><input type="hidden" name="condition_group[]" value="'.intval($gi).'"> <input name="condition_field[]" placeholder="field, e.g. stage" value="'.esc_attr($c['field']??'').'"><select name="condition_operator[]">';foreach($operators as $ok=>$ov)echo '<option value="'.esc_attr($ok).'" '.selected($c['operator']??'equals',$ok,false).'>'.esc_html($ov).'</option>';echo '</select><input name="condition_value[]" placeholder="value" value="'.esc_attr(is_scalar($c['value']??'')?$c['value']:'').'"> <button type="button" class="button gdcp-remove-condition">×</button></div>';}echo '</div><button type="button" class="button gdcp-add-condition">+ Add condition</button></div>';}echo '</div><button type="button" class="button" id="gdcp-add-group">+ Add condition group</button></div>';
    echo '<div class="gdcp-builder-section"><div class="gdcp-section-title"><h3>Actions</h3><span>Actions run in order. Add a delay to schedule an individual action.</span></div><div id="gdcp-action-list">';foreach($decoded_actions as $ai=>$a){$type=$a['type']??'notify_team';echo '<div class="gdcp-action-card"><div class="gdcp-action-top"><select name="action_type[]">';foreach($actions as $ak=>$av)echo '<option value="'.esc_attr($ak).'" '.selected($type,$ak,false).'>'.esc_html($av).'</option>';echo '</select><input name="action_title[]" placeholder="Title" value="'.esc_attr($a['title']??'').'"> <button type="button" class="button gdcp-remove-action">Remove</button></div><textarea name="action_body[]" rows="3" placeholder="Message, email content, or task description">'.esc_textarea($a['body']??'').'</textarea><div class="gdcp-action-options"><label>Delay (minutes)<input type="number" min="0" max="43200" name="action_delay[]" value="'.intval($a['delay_minutes']??0).'" /></label><label>Stage (optional)<input name="action_stage[]" value="'.esc_attr($a['stage']??'').'" placeholder="auto = next stage" /></label><label>Priority<select name="action_priority[]">';foreach(array('low','normal','high','urgent') as $p)echo '<option value="'.$p.'" '.selected($a['priority']??'normal',$p,false).'>'.ucfirst($p).'</option>';echo '</select></label><label>Assignee ID<input type="number" name="action_assigned_to[]" value="'.intval($a['assigned_to']??0).'" /></label><label>Due in days<input type="number" min="0" max="365" name="action_due_days[]" value="'.intval($a['due_days']??0).'" /></label></div></div>';}echo '</div><button type="button" class="button" id="gdcp-add-action">+ Add action</button></div>';
    echo '<p><button class="button button-primary button-hero">'.($edit?'Update workflow':'Create workflow').'</button> '.($edit?'<a class="button" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-automation')).'">Cancel</a>':'').'</p></form></section>';
    echo '<section id="rules" class="gdcp-auto-panel"><h2>Workflow rules</h2><table class="widefat striped"><thead><tr><th>Workflow</th><th>Trigger</th><th>Status</th><th>Runs</th><th>Last run</th><th>Actions</th></tr></thead><tbody>';foreach($rules as $r){$del=wp_nonce_url(admin_url('admin.php?page=gd-client-portal-automation&delete_rule='.$r->id),'gdcp_automation_delete_'.$r->id);echo '<tr><td><strong>'.esc_html($r->name).'</strong></td><td>'.esc_html($events[$r->event_key]??$r->event_key).'</td><td><span class="gdcp-status gdcp-status-'.esc_attr($r->status).'">'.esc_html(ucfirst($r->status)).'</span></td><td>'.intval($r->run_count).'</td><td>'.esc_html($r->last_run_at?:'—').'</td><td><a class="button" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-automation&edit_rule='.$r->id)).'">Edit</a> <a class="button" href="'.esc_url($del).'" onclick="return confirm(\'Delete this workflow?\')">Delete</a><form class="gdcp-inline-form" method="post">'.wp_nonce_field('gdcp_automation_template','_wpnonce',true,false).'<input type="hidden" name="gdcp_auto_template" value="1"><input type="hidden" name="template_rule_id" value="'.intval($r->id).'"><input type="hidden" name="template_name" value="'.esc_attr($r->name).' template"><input type="hidden" name="template_description" value="Reusable workflow template based on '.esc_attr($r->name).'"> <button class="button">Save template</button></form></td></tr>';}if(!$rules)echo '<tr><td colspan="6">No workflows yet.</td></tr>';echo '</tbody></table></section>';
    echo '<section id="logs" class="gdcp-auto-panel"><h2>Execution history</h2><p>Every live or test execution receives an execution ID and stored payload summary.</p><table class="widefat striped"><thead><tr><th>Time</th><th>Workflow</th><th>Event</th><th>Status</th><th>Project</th><th>Execution ID</th><th>Details</th></tr></thead><tbody>';foreach($logs as $l){$r=gd_client_portal_automation_get_rule($l->rule_id);echo '<tr><td>'.esc_html($l->created_at).'</td><td>'.esc_html($r?$r->name:'Deleted workflow').'</td><td>'.esc_html($events[$l->event_key]??$l->event_key).'</td><td>'.esc_html($l->status).'</td><td>#'.intval($l->project_id).'</td><td><code>'.esc_html($l->execution_id).'</code></td><td><details><summary>View</summary><pre>'.esc_html($l->details).'</pre></details></td></tr>';}if(!$logs)echo '<tr><td colspan="7">No execution history yet.</td></tr>';echo '</tbody></table></section>';
    echo '<section id="templates" class="gdcp-auto-panel"><h2>Reusable templates</h2><table class="widefat striped"><thead><tr><th>Name</th><th>Trigger</th><th>Description</th><th>Created</th><th></th></tr></thead><tbody>';foreach($templates as $tpl)echo '<tr><td><strong>'.esc_html($tpl->name).'</strong></td><td>'.esc_html($events[$tpl->event_key]??$tpl->event_key).'</td><td>'.esc_html($tpl->description).'</td><td>'.esc_html($tpl->created_at).'</td><td><form method="post">'.wp_nonce_field('gdcp_automation_use_template','_wpnonce',true,false).'<input type="hidden" name="gdcp_auto_use_template" value="1"><input type="hidden" name="template_id" value="'.intval($tpl->id).'"><button class="button">Use template</button></form></td></tr>';if(!$templates)echo '<tr><td colspan="5">No reusable templates yet. Save a workflow as a template above.</td></tr>';echo '</tbody></table></section>';
    echo '<section class="gdcp-auto-panel"><h2>Test a workflow</h2><form method="post" class="gdcp-test-form">'.wp_nonce_field('gdcp_automation_test','_wpnonce',true,false).'<input type="hidden" name="gdcp_auto_test" value="1"><label>Workflow<select name="test_rule_id">';foreach($rules as $r)echo '<option value="'.intval($r->id).'">'.esc_html($r->name).'</option>';echo '</select></label><label>Project ID<input type="number" name="test_project_id" min="0"></label><label>Tenant ID<input type="number" name="test_tenant_id" min="0"></label><label>Stage<input name="test_stage" placeholder="e.g. review"></label><label>Status<input name="test_status"></label><label>Progress<input type="number" name="test_progress" min="0" max="100"></label><label>Amount<input type="number" step="0.01" name="test_amount"></label><button class="button">Run dry test</button></form></section></div>';
}
add_action('admin_menu',function(){add_submenu_page('gd-client-portal','Automation & Workflow','Automation & Workflow','gd_client_portal_access_admin','gd-client-portal-automation','gd_client_portal_automation_admin');},20);
add_action('init',function(){if(function_exists('gd_client_portal_register_dashboard_view'))gd_client_portal_register_dashboard_view('automation','gd_client_portal_render_automation_dashboard');},30);
function gd_client_portal_render_automation_dashboard(){if(!gd_client_portal_verify_request()||!gd_client_portal_verify_tenant_access())return gd_client_portal_render_access_gate('Automation','Please sign in to view automation activity.');if(!gd_client_portal_user_can_access_admin())return gd_client_portal_render_access_gate('Automation','You do not have access to automation management.');$rules=gdcp_automation_service()->list_rules();usort($rules,function($a,$b){$al=$a->last_run_at?:'';$bl=$b->last_run_at?:'';return $al===$bl?absint($b->id)<=>absint($a->id):strcmp($bl,$al);});$rules=array_slice($rules,0,50);ob_start();echo '<div class="gdcp-automation-dashboard"><div class="gdcp-auto-hero"><div><span>GD CLIENT PORTAL · V4.4</span><h2>Automation Center</h2><p>Monitor and manage event-driven portal workflows.</p></div><a href="'.esc_url(admin_url('admin.php?page=gd-client-portal-automation')).'">Open workflow builder →</a></div><div class="gdcp-auto-stats"><div><b>'.intval(count($rules)).'</b><span>Rules</span></div><div><b>'.intval(count(array_filter($rules,function($r){return $r->status==='active';}))).'</b><span>Active</span></div><div><b>'.intval(array_sum(array_map(function($r){return (int)$r->run_count;},$rules))).'</b><span>Total runs</span></div></div></div>';return ob_get_clean();}
add_shortcode('gd_automation_center','gd_client_portal_render_automation_dashboard');add_shortcode('gd_workflow_automation','gd_client_portal_render_automation_dashboard');

function gd_client_portal_automation_orchestration_admin(){
    if(!gd_client_portal_user_can_access_admin())wp_die('Access denied.');
    $rows=gdcp_automation_service()->list_queue(100);
    echo '<div class="wrap"><h1>Workflow Orchestration Monitor</h1><p>Queued, running, completed and failed automation actions with retry visibility.</p><table class="widefat striped"><thead><tr><th>ID</th><th>Execution</th><th>Rule</th><th>Project</th><th>Status</th><th>Attempts</th><th>Run at</th><th>Error</th></tr></thead><tbody>';
    foreach((array)$rows as $r){$rule=gd_client_portal_automation_get_rule($r->rule_id);echo '<tr><td>'.intval($r->id).'</td><td><code>'.esc_html($r->execution_id).'</code></td><td>'.esc_html($rule?$rule->name:'Deleted workflow').'</td><td>#'.intval($r->project_id).'</td><td>'.esc_html($r->status).'</td><td>'.intval($r->attempts).' / '.intval($r->max_attempts).'</td><td>'.esc_html($r->run_at).'</td><td>'.esc_html($r->last_error?:'—').'</td></tr>';}
    if(!$rows)echo '<tr><td colspan="8">No queued automation actions.</td></tr>'; echo '</tbody></table></div>';
}
add_action('admin_menu',function(){add_submenu_page('gd-client-portal','Orchestration Monitor','Orchestration Monitor','gd_client_portal_access_admin','gd-client-portal-orchestration','gd_client_portal_automation_orchestration_admin');},21);
