<?php
/** Canonical Collaboration application service. */
if (!defined('ABSPATH')) exit;
class GDCP_Collaboration_Service {
    public function repository(){return gdcp_collaboration_repository();}
    public function create_task($project,$data){ if(!$project)return 0; $data['project_id']=absint($project->id);$data['tenant_id']=absint($project->tenant_id);$id=$this->repository()->insert_task($data); if($id)do_action('gd_client_portal_task_created',$id,$project->id,$project->tenant_id); return $id; }
    public function update_task($id,$data){$ok=$this->repository()->update_task($id,$data);return $ok;}
    public function delete_task($id){return $this->repository()->delete_task($id);}
    public function task($id,$project_id=0){return $this->repository()->find_task($id,$project_id);}
    public function tasks($project_id=0,$tenant_id=0,$limit=100){return $this->repository()->list_tasks($project_id,$tenant_id,$limit);}
    public function create_update($project,$data){if(!$project)return 0;$data['project_id']=absint($project->id);$data['tenant_id']=absint($project->tenant_id);return $this->repository()->insert_update($data);}
    public function updates($project_id=0,$tenant_id=0,$limit=30,$include_internal=false){return $this->repository()->list_updates($project_id,$tenant_id,$limit,$include_internal);}
    public function create_note($project,$data){if(!$project)return 0;$data['project_id']=absint($project->id);$data['tenant_id']=absint($project->tenant_id);return $this->repository()->insert_note($data);}
    public function notes($project_id,$limit=100){return $this->repository()->list_notes($project_id,$limit);}
}
function gdcp_collaboration_service(){static $s;return $s?:($s=new GDCP_Collaboration_Service());}
