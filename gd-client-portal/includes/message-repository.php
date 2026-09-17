<?php
/** Canonical Project Message persistence boundary. */
if (!defined('ABSPATH')) exit;

final class GDCP_Message_Repository {
    public function table(){ global $wpdb; return $wpdb->prefix . 'gd_project_messages'; }
    public function find($id){ global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->table().' WHERE id=%d',absint($id))); }
    public function list_for_project($project_id,$limit=100,$order='DESC'){ global $wpdb; $dir=strtoupper($order)==='ASC'?'ASC':'DESC'; return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.$this->table().' WHERE project_id=%d ORDER BY id '.$dir.' LIMIT %d',absint($project_id),max(1,min(300,absint($limit))))); }
    public function list_for_tenant($tenant_id=0,$limit=100){ global $wpdb; $pt=gd_client_portal_get_project_table_name(); $where='1=1';$args=array(); if($tenant_id){$where.=' AND p.tenant_id=%d';$args[]=absint($tenant_id);} $args[]=max(1,min(300,absint($limit))); return $wpdb->get_results($wpdb->prepare('SELECT m.*,p.title AS project_title,p.tenant_id FROM '.$this->table().' m INNER JOIN '.$pt.' p ON p.id=m.project_id WHERE '.$where.' ORDER BY m.id DESC LIMIT %d',$args)); }
    public function list_for_user($user_id,$tenant_id=0,$tenant_admin=false,$limit=100){ global $wpdb; $pt=gd_client_portal_get_project_table_name(); $where='1=1';$args=array(); if($tenant_id){$where.=' AND p.tenant_id=%d';$args[]=absint($tenant_id);} if(!$tenant_admin){$where.=' AND (m.user_id=%d OR p.user_id=%d)';$args[]=absint($user_id);$args[]=absint($user_id);} $args[]=max(1,min(300,absint($limit))); return $wpdb->get_results($wpdb->prepare('SELECT m.*,p.title AS project_title,p.tenant_id FROM '.$this->table().' m INNER JOIN '.$pt.' p ON p.id=m.project_id WHERE '.$where.' ORDER BY m.id DESC LIMIT %d',$args)); }
    public function count_for_tenant($tenant_id){ global $wpdb; $pt=gd_client_portal_get_project_table_name(); return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(m.id) FROM '.$this->table().' m INNER JOIN '.$pt.' p ON p.id=m.project_id WHERE p.tenant_id=%d',absint($tenant_id))); }
    public function find_by_event_key($project_id,$event_key){ global $wpdb; if(!$event_key)return null; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->table().' WHERE project_id=%d AND event_key=%s LIMIT 1',absint($project_id),sanitize_key($event_key))); }
    public function insert(array $data){ global $wpdb; $formats=array(); foreach($data as $key=>$value){$formats[]=in_array($key,array('project_id','user_id'),true)?'%d':'%s';} $ok=$wpdb->insert($this->table(),$data,$formats); return $ok?absint($wpdb->insert_id):0; }
}
function gdcp_message_repository(){static $r;if(!$r)$r=new GDCP_Message_Repository();return $r;}
