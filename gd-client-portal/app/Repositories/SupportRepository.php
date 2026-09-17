<?php
if (!defined('ABSPATH')) exit;
final class GDCP_Support_Repository extends GDCP_Base_Repository {
    public function table($suffix='tickets'){return function_exists('gd_client_portal_support_table')?gd_client_portal_support_table($suffix):$this->db()->prefix.'gd_support_'.$suffix;}
    public function ticket($id){$row=$this->db()->get_row($this->db()->prepare('SELECT * FROM '.$this->table().' WHERE id=%d',absint($id)));if(!$row)return null;if(function_exists('gd_client_portal_support_access')){ $manager=function_exists('gd_client_portal_user_is_tenant_admin')&&gd_client_portal_user_is_tenant_admin() || function_exists('gd_client_portal_is_platform_admin')&&gd_client_portal_is_platform_admin(); return gd_client_portal_support_access($row,$manager)?$row:null;}return $row;}
    public function tickets($limit=100){if(function_exists('gd_client_portal_support_tickets'))return gd_client_portal_support_tickets($limit);return array();}
}
