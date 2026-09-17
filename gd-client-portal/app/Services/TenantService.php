<?php
if (!defined('ABSPATH')) exit;
final class GDCP_Tenant_Service {
    public function get($id){ $repo=gdcp_repository('tenant'); return $repo ? $repo->find($id) : null; }
    public function all($active_only=false){ $repo=gdcp_repository('tenant'); return $repo ? $repo->all($active_only) : array(); }
    public function current($user_id=0){ $repo=gdcp_repository('tenant'); return $repo ? $repo->find($repo->user_tenant($user_id ?: get_current_user_id())) : null; }
    public function current_id($user_id=0){ $repo=gdcp_repository('tenant'); return $repo ? $repo->user_tenant($user_id ?: get_current_user_id()) : 0; }
    public function can_access($id,$user_id=0){ $id=absint($id); if($id<=0)return false; if(function_exists('gd_client_portal_is_platform_admin') && gd_client_portal_is_platform_admin($user_id ?: get_current_user_id())) return true; return $this->current_id($user_id)===$id && $this->get($id)!==null; }
    public function set_user_tenant($user_id,$tenant_id){ $repo=gdcp_repository('tenant'); return $repo ? $repo->set_user_tenant($user_id,$tenant_id) : false; }
}

