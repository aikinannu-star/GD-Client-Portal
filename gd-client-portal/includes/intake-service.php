<?php
if(!defined('ABSPATH')) exit;
class GDCP_Intake_Service {
    public function form($id){ return gdcp_intake_repository()->form($id); }
    public function submission($id){ return gdcp_intake_repository()->submission($id); }
    public function submission_for_project($id){ return gdcp_intake_repository()->submission_for_project($id); }
    public function find_form($project){ return gdcp_intake_repository()->find_form($project); }
    public function create_form($data){ return gdcp_intake_repository()->create_form($data); }
    public function update_form($id,$data){ return gdcp_intake_repository()->update_form($id,$data); }
    public function delete_form($id){ return gdcp_intake_repository()->delete_form($id); }
    public function create_submission($data){ return gdcp_intake_repository()->create_submission($data); }
    public function update_submission($id,$data){ return gdcp_intake_repository()->update_submission($id,$data); }
    public function list_forms(){ return gdcp_intake_repository()->list_forms(gd_client_portal_get_current_tenant_id(),gd_client_portal_is_platform_admin(),200); }
}
function gdcp_intake_service(){ static $s=null; if(!$s)$s=new GDCP_Intake_Service(); return $s; }
