<?php
if (!defined('ABSPATH')) exit;

final class GDCP_Contract_Repository {
    private function db(){ global $wpdb; return $wpdb; }
    public function table(){ return gd_client_portal_contracts_table(); }
    public function signature_table(){ return $this->table().'_signatures'; }
    public function list_visible($limit=100){
        $db=$this->db(); $limit=max(1,min(500,absint($limit))); $where='1=1'; $args=array();
        if(!gd_client_portal_is_platform_admin()){
            $tenant=absint(gd_client_portal_get_current_tenant_id()); if(!$tenant)return array();
            $where.=' AND tenant_id=%d'; $args[]=$tenant;
            if(!gd_client_portal_user_is_tenant_admin()){ $where.=' AND user_id=%d'; $args[]=gd_client_portal_cached_current_user_id(); }
        }
        $args[]=$limit;
        return $db->get_results($db->prepare("SELECT * FROM {$this->table()} WHERE {$where} ORDER BY id DESC LIMIT %d",$args));
    }
    public function find($id,$lock=false){
        $sql="SELECT * FROM {$this->table()} WHERE id=%d LIMIT 1".($lock?' FOR UPDATE':'');
        return $this->db()->get_row($this->db()->prepare($sql,absint($id)));
    }
    public function latest_for_project($project_id,$lock=false){
        $sql="SELECT * FROM {$this->table()} WHERE project_id=%d ORDER BY version DESC,id DESC LIMIT 1".($lock?' FOR UPDATE':'');
        return $this->db()->get_row($this->db()->prepare($sql,absint($project_id)));
    }
    public function latest_for_quote($quote_id,$lock=false){
        $sql="SELECT * FROM {$this->table()} WHERE quote_id=%d ORDER BY version DESC,id DESC LIMIT 1".($lock?' FOR UPDATE':'');
        return $this->db()->get_row($this->db()->prepare($sql,absint($quote_id)));
    }
    private function formats(array $data){
        $integer=array('tenant_id','user_id','project_id','quote_id','parent_id','version','created_by');
        $formats=array(); foreach(array_keys($data) as $key) $formats[]=in_array($key,$integer,true)?'%d':'%s'; return $formats;
    }
    public function insert(array $data){
        if(!$data)return 0; $ok=$this->db()->insert($this->table(),$data,$this->formats($data)); return $ok===false?0:absint($this->db()->insert_id);
    }
    public function update($id,array $data){
        if(!$data)return false; return false!==$this->db()->update($this->table(),$data,array('id'=>absint($id)),$this->formats($data),array('%d'));
    }
    public function insert_signature(array $data){
        $formats=array('%d','%d','%d','%s','%s','%s','%s','%s');
        $ok=$this->db()->insert($this->signature_table(),$data,$formats); return $ok===false?0:absint($this->db()->insert_id);
    }
}
function gdcp_contract_repository(){ static $repo; if(!$repo)$repo=new GDCP_Contract_Repository(); return $repo; }
