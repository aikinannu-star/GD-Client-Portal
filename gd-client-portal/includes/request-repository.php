<?php
if(!defined('ABSPATH')) exit;
class GDCP_Request_Repository {
    public function table(){ return gd_client_portal_requests_table(); }
    public function find($id){ global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->table().' WHERE id=%d',absint($id))); }
    public function list_for_context($limit=50,$tenant=0,$user_id=0,$platform_admin=false,$tenant_admin=false){
        global $wpdb; $sql='SELECT * FROM '.$this->table(); $w=array(); $p=array();
        if(!$platform_admin){ $w[]='tenant_id=%d'; $p[]=absint($tenant); if(!$tenant_admin){$w[]='user_id=%d';$p[]=absint($user_id);} }
        if($w)$sql.=' WHERE '.implode(' AND ',$w); $sql.=' ORDER BY id DESC LIMIT '.absint($limit);
        return $p?$wpdb->get_results($wpdb->prepare($sql,$p)):$wpdb->get_results($sql);
    }
    public function create($data){ global $wpdb; $ok=$wpdb->insert($this->table(),$data,array('%d','%d','%d','%s','%s','%s','%s','%s','%s','%s')); return $ok?absint($wpdb->insert_id):0; }
    public function update($id,$data){ global $wpdb; return $wpdb->update($this->table(),$data,array('id'=>absint($id)),array('%s','%s','%s'),array('%d')); }
}
function gdcp_request_repository(){ static $r=null; if(!$r)$r=new GDCP_Request_Repository(); return $r; }
