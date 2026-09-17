<?php
if(!defined('ABSPATH')) exit;
class GDCP_Request_Service {
    public function get($id){ return gdcp_request_repository()->find($id); }
    public function list($limit=50){
        return gdcp_request_repository()->list_for_context($limit,gd_client_portal_get_current_tenant_id(),gd_client_portal_cached_current_user_id(),gd_client_portal_is_platform_admin(),gd_client_portal_user_is_tenant_admin());
    }
    public function create($data){ return gdcp_request_repository()->create($data); }
    public function update_status($id,$status,$note){ return gdcp_request_repository()->update($id,array('status'=>$status,'admin_note'=>$note,'updated_at'=>current_time('mysql'))); }
}
function gdcp_request_service(){ static $s=null; if(!$s)$s=new GDCP_Request_Service(); return $s; }
