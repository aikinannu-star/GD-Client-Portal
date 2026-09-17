<?php
if (!defined('ABSPATH')) exit;

final class GDCP_Onboarding_Service extends GDCP_Service_Base {
    private function repo(){ return gdcp_repository('onboarding'); }

    public function create_for_project($project){
        $repo=$this->repo(); return $repo ? $repo->create_for_project($project) : 0;
    }
    public function claim($project_id,$user_id){
        $project=gd_client_portal_cached_project($project_id); $repo=$this->repo();
        if(!$project||!$repo||!$user_id) return false;
        $this->begin();
        try{
            $row=$repo->find_for_project($project_id,true);
            if(!$row){ $repo->create_for_project($project); $row=$repo->find_for_project($project_id,true); }
            if(!$row){$this->rollback();return false;}
            $ok=$repo->update($row->id,array('user_id'=>absint($user_id),'updated_at'=>current_time('mysql')));
            if($ok===false){$this->rollback();return false;}
            return $this->commit();
        }catch(Throwable $e){$this->rollback();return false;}
    }
    public function save($id,$data){
        $repo=$this->repo(); if(!$repo) return array('ok'=>false,'message'=>__('Onboarding service unavailable.','gd-client-portal'));
        $this->begin();
        try{
            $row=$repo->find($id,true); if(!$row){$this->rollback();return array('ok'=>false,'message'=>__('Onboarding record not found.','gd-client-portal'));}
            $ok=$repo->update($id,$data); if($ok===false){$this->rollback();return array('ok'=>false,'message'=>__('Unable to save onboarding.','gd-client-portal'));}
            if(!$this->commit()) return array('ok'=>false,'message'=>__('Unable to save onboarding.','gd-client-portal'));
            return array('ok'=>true,'row'=>$row);
        }catch(Throwable $e){$this->rollback();return array('ok'=>false,'message'=>__('Unable to save onboarding.','gd-client-portal'));}
    }
    public function add_document($id,$project,$file){
        $repo=$this->repo(); if(!$repo||!$project||empty($file['url'])) return 0;
        return $repo->add_document(array('onboarding_id'=>$id,'project_id'=>$project->id,'tenant_id'=>$project->tenant_id,'user_id'=>$this->actor(),'title'=>$file['name']??'','file_url'=>$file['url'],'mime_type'=>$file['type']??''));
    }
    public function documents_for($id){ $repo=$this->repo(); return $repo ? $repo->documents_for($id) : array(); }
}
