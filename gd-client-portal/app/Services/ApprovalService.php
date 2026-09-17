<?php
if (!defined('ABSPATH')) exit;

final class GDCP_Approval_Service extends GDCP_Service_Base {
    private function repo(){ return gdcp_repository('approval'); }

    public function latest_for_project($project_id){$repo=$this->repo();if(!$repo)return null;$rows=$repo->history($project_id,1);return $rows?$rows[0]:null;}
    public function pending_for_tenant($tenant_id=0,$limit=20){$repo=$this->repo();if(!$repo)return array();return $repo->pending_for_tenant($tenant_id,$limit);}
    public function approved_for_project_version($project_id,$tenant_id,$version,$lock=false){$repo=$this->repo();return $repo?$repo->find_approved_for_project_version($project_id,$tenant_id,$version,$lock):null;}

    public function submit($project_id){
        $project=gd_client_portal_get_project_by_id($project_id);
        if(!function_exists('gd_client_portal_approval_actor_can_manage') || !gd_client_portal_approval_actor_can_manage($project)) return array('ok'=>false,'code'=>403,'message'=>__('You do not have permission to submit this project for approval.','gd-client-portal'));
        $repo=$this->repo(); if(!$repo) return array('ok'=>false,'code'=>500,'message'=>__('Approval workflow is unavailable.','gd-client-portal'));
        $this->begin();
        try {
            $project_table=gd_client_portal_get_project_table_name();
            $locked=$this->db()->get_row($this->db()->prepare('SELECT id,tenant_id,user_id,service_type,current_stage,title FROM '.$project_table.' WHERE id=%d FOR UPDATE',absint($project_id)));
            if(!$locked || absint($locked->tenant_id)!==absint($project->tenant_id)){ $this->rollback(); return array('ok'=>false,'code'=>409,'message'=>__('Project integrity check failed.','gd-client-portal')); }
            $active=$repo->find_active_for_project($project_id,$locked->tenant_id,true);
            if($active){ $this->commit(); return array('ok'=>false,'code'=>409,'message'=>__('This project already has an approval request awaiting the client.','gd-client-portal')); }
            $version=0;
            if(function_exists('gd_client_portal_get_latest_delivery')){ $delivery=gd_client_portal_get_latest_delivery($project_id,false); if($delivery) $version=absint($delivery->version); }
            if($version<1) $version=max(1,$repo->latest_version($project_id,$locked->tenant_id)+1);
            $id=$repo->create_pending($project_id,$locked->tenant_id,$version,$this->actor());
            if(!$id){ $this->rollback(); return array('ok'=>false,'code'=>500,'message'=>__('Unable to create the approval request.','gd-client-portal')); }
            $this->commit();
            if(function_exists('gd_client_portal_set_review_stage')) gd_client_portal_set_review_stage($project_id);
            return array('ok'=>true,'id'=>$id,'version'=>$version,'project'=>$project);
        } catch(Throwable $e){ $this->rollback(); return array('ok'=>false,'code'=>500,'message'=>__('Unable to create the approval request.','gd-client-portal')); }
    }

    public function decide($project_id,$decision,$comment){
        $project=gd_client_portal_get_project_by_id($project_id);
        if(!in_array($decision,array('approved','revision_requested'),true)) return array('ok'=>false,'code'=>400,'message'=>__('Invalid approval decision.','gd-client-portal'));
        if(!function_exists('gd_client_portal_approval_client_can_act') || !gd_client_portal_approval_client_can_act($project)) return array('ok'=>false,'code'=>403,'message'=>__('Only the assigned client can make this approval decision.','gd-client-portal'));
        $repo=$this->repo(); if(!$repo) return array('ok'=>false,'code'=>500,'message'=>__('Approval workflow is unavailable.','gd-client-portal'));
        $this->begin();
        try {
            $approval=$repo->find_active_for_project($project_id,$project->tenant_id,true);
            if(!$approval){ $this->rollback(); return array('ok'=>false,'code'=>409,'message'=>__('There is no pending approval request for this project.','gd-client-portal')); }
            $updated=$repo->decide_pending($approval->id,$project_id,$project->tenant_id,$decision,$this->actor(),$comment);
            if($updated!==1){ $this->rollback(); return array('ok'=>false,'code'=>409,'message'=>__('Unable to save your decision.','gd-client-portal')); }
            $this->commit();
            $target=$decision==='approved'?'completed':'revisions'; $workflow=gd_client_portal_get_workflow($project->service_type);
            if(!in_array($target,$workflow,true)) $target=$decision==='approved'?(in_array('final_delivery',$workflow,true)?'final_delivery':$project->current_stage):(in_array('revisions',$workflow,true)?'revisions':$project->current_stage);
            if($target!==$project->current_stage) gd_client_portal_update_project_stage($project_id,$target,gd_client_portal_auto_progress($project->service_type,$target));
            return array('ok'=>true,'approval'=>$approval,'project'=>$project,'target'=>$target);
        } catch(Throwable $e){ $this->rollback(); return array('ok'=>false,'code'=>500,'message'=>__('Unable to save your decision.','gd-client-portal')); }
    }
}
