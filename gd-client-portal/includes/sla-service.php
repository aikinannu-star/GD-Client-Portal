<?php
if (!defined('ABSPATH')) exit;

class GDCP_SLA_Service {
    public function repository() { return gdcp_sla_repository(); }
    public function get($project_id) { return $this->repository()->find_by_project($project_id); }
    public function ensure($project) {
        if(!$project || empty($project->id)) return false;
        $existing=$this->get($project->id); if($existing) return $existing;
        $priority='normal'; $start=strtotime($project->created_at ?: current_time('mysql')); if(!$start) $start=time();
        $due=gmdate('Y-m-d H:i:s',$start + gd_client_portal_sla_priority_days($priority)*DAY_IN_SECONDS);
        $stage=gmdate('Y-m-d H:i:s',time()+gd_client_portal_sla_stage_days($priority)*DAY_IN_SECONDS);
        $this->repository()->create(array('project_id'=>absint($project->id),'tenant_id'=>absint($project->tenant_id),'priority'=>$priority,'due_date'=>$due,'stage_due_date'=>$stage,'warning_days'=>2,'updated_by'=>gd_client_portal_cached_current_user_id(),'updated_at'=>current_time('mysql')));
        return $this->get($project->id);
    }
    public function set($project_id,$priority,$due_date,$stage_due_date='',$warning_days=2) {
        $project=gd_client_portal_get_project_by_id($project_id); if(!$project) return false;
        $priority=in_array($priority,array('urgent','high','normal','low'),true)?$priority:'normal';
        $due=$due_date?sanitize_text_field($due_date):gmdate('Y-m-d H:i:s',time()+gd_client_portal_sla_priority_days($priority)*DAY_IN_SECONDS);
        $stage=$stage_due_date?sanitize_text_field($stage_due_date):gmdate('Y-m-d H:i:s',time()+gd_client_portal_sla_stage_days($priority)*DAY_IN_SECONDS);
        $data=array('tenant_id'=>absint($project->tenant_id),'priority'=>$priority,'due_date'=>$due,'stage_due_date'=>$stage,'warning_days'=>max(1,min(30,absint($warning_days))),'updated_by'=>gd_client_portal_cached_current_user_id(),'updated_at'=>current_time('mysql'));
        $existing=$this->get($project_id);
        if($existing) $ok=$this->repository()->update_by_project($project_id,$data);
        else { $data['project_id']=absint($project_id); $ok=$this->repository()->create($data)!==false; }
        return $ok;
    }
    public function on_stage_changed($project_id) {
        $project=gd_client_portal_get_project_by_id($project_id); if(!$project) return false;
        $sla=$this->ensure($project); if(!$sla) return false;
        return $this->repository()->update_by_project($project_id,array('stage_due_date'=>gmdate('Y-m-d H:i:s',time()+gd_client_portal_sla_stage_days($sla->priority)*DAY_IN_SECONDS),'escalation_level'=>0,'last_escalated_at'=>null,'updated_at'=>current_time('mysql')));
    }
    public function projects($mode='all',$limit=20) { return $this->repository()->project_list($mode,$limit); }
    public function escalate_due_projects() {
        if(get_transient('gd_client_portal_sla_escalation_lock')) return false;
        set_transient('gd_client_portal_sla_escalation_lock',1,5*MINUTE_IN_SECONDS);
        try {
            $now=current_time('mysql'); $rows=$this->repository()->due_projects($now,gmdate('Y-m-d H:i:s',time()-DAY_IN_SECONDS),100); $count=0;
            foreach($rows as $project){ $sla=$this->ensure($project); if(!$sla) continue;
                $lead=function_exists('gd_client_portal_get_project_lead')?gd_client_portal_get_project_lead($project->id):false;
                $recipients=array($project->user_id,$lead?$lead->user_id:0);
                foreach(array_unique(array_filter(array_map('absint',$recipients))) as $uid){
                    if(function_exists('gd_client_portal_create_notification')) gd_client_portal_create_notification($uid,__('SLA escalation: project overdue','gd-client-portal'),sprintf(__('Project “%s” has passed its deadline and needs immediate attention.','gd-client-portal'),$project->title),'sla_escalation',$project->id,$project->tenant_id,'sla_'.absint($project->id).'_'.absint($sla->escalation_level+1));
                    $u=gd_client_portal_cached_user($uid); if($u&&$u->user_email) wp_mail($u->user_email,sprintf(__('SLA escalation: %s','gd-client-portal'),$project->title),sprintf(__('Project “%s” is overdue. Please review the Client Portal.','gd-client-portal'),$project->title));
                }
                $this->repository()->update_by_project($project->id,array('escalation_level'=>absint($sla->escalation_level)+1,'last_escalated_at'=>$now)); do_action('gd_client_portal_sla_escalated',absint($project->id)); $count++;
            }
            return $count;
        } finally { delete_transient('gd_client_portal_sla_escalation_lock'); }
    }
}
function gdcp_sla_service() { static $service; if(!$service) $service=new GDCP_SLA_Service(); return $service; }
