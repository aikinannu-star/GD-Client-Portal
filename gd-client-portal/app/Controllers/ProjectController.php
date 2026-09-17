<?php
if (!defined('ABSPATH')) exit;
final class GDCP_Project_Controller extends GDCP_Controller_Base {
    public function stage(){
        if(!$this->require_login() || !$this->nonce('gd_client_portal_project_stage')) return $this->fail('Invalid request',403);
        $id=absint($_POST['project_id']??0); $stage=sanitize_key(wp_unslash($_POST['stage']??''));
        if(!$id || !$stage || !gdcp_can('edit','project',$id)) return $this->fail('You are not authorized to update this project',403);
        $service=gdcp_service('project'); $ok=$service && $service->update_stage($id,$stage,isset($_POST['progress'])?absint($_POST['progress']):null);
        return $ok ? $this->ok(array('project_id'=>$id,'stage'=>$stage)) : $this->fail('Stage update failed',409);
    }
}
