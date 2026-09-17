<?php
/** Canonical Delivery application service. */
if (!defined('ABSPATH')) exit;
class GDCP_Delivery_Service {
    public function repository(){ return gdcp_delivery_repository(); }
    public function versions($project_id, $published_only=false){ return $this->repository()->list_for_project($project_id, $published_only); }
    public function latest($project_id, $published_only=true){ return $this->repository()->latest_for_project($project_id, $published_only); }
    public function publish($project, $data) {
        if (!$project) return 0;
        $repo = $this->repository();
        $data['project_id']=absint($project->id);
        $data['tenant_id']=absint($project->tenant_id);
        $data['version']=$repo->next_version($project->id);
        $repo->supersede_published($project->id);
        $id=$repo->insert($data);
        if($id) do_action('gd_client_portal_delivery_published',$project->id,$data['version']);
        return $id;
    }
    public function approve($id) { return $this->repository()->approve_published($id,current_time('mysql')); }
    public function find($id) { return $this->repository()->find($id); }
}
function gdcp_delivery_service(){ static $s; return $s ?: ($s = new GDCP_Delivery_Service()); }
