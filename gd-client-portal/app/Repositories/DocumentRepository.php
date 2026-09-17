<?php
if (!defined('ABSPATH')) exit;
final class GDCP_Document_Repository extends GDCP_Base_Repository {
    public function table(){return function_exists('gd_client_portal_documents_table')?gd_client_portal_documents_table():$this->db()->prefix.'gd_portal_documents';}
    public function find($id){$row=$this->db()->get_row($this->db()->prepare('SELECT * FROM '.$this->table().' WHERE id=%d',absint($id)));if(!$row)return null;if(function_exists('gd_client_portal_is_platform_admin')&&gd_client_portal_is_platform_admin())return $row;$tid=$this->tenant_id();if(absint($row->tenant_id)!==$tid)return null;if(function_exists('gd_client_portal_user_is_tenant_admin')&&gd_client_portal_user_is_tenant_admin())return $row;return absint($row->user_id)===get_current_user_id()? $row:null;}
    public function for_project($project_id,$limit=200){$p=absint($project_id);if(function_exists('gd_client_portal_get_project_by_id')){$project=gd_client_portal_get_project_by_id($p);if(!$project||!gd_client_portal_verify_project_access($project))return array();}$sql='SELECT * FROM '.$this->table().' WHERE project_id=%d';$args=array($p);list($w,$a)=$this->scoped_where();$sql.=$w;$args=array_merge($args,$a);$sql.=' ORDER BY version DESC,id DESC LIMIT %d';$args[]=max(1,min(500,absint($limit)));return $this->db()->get_results($this->db()->prepare($sql,$args));}
}
