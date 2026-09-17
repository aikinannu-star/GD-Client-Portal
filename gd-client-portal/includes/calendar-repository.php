<?php
if (!defined('ABSPATH')) exit;
final class GDCP_Calendar_Repository {
 public function table(){return gd_client_portal_calendar_table();}
 public function events($start,$end,$project_id=0,$tenant_id=0){global $wpdb;$where='WHERE start_at < %s AND (end_at IS NULL OR end_at >= %s)';$params=array($end,$start);if($project_id){$where.=' AND project_id=%d';$params[]=absint($project_id);}if($tenant_id){$where.=' AND tenant_id=%d';$params[]=absint($tenant_id);}return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.$this->table().' '.$where.' ORDER BY start_at ASC',$params));}
 public function insert($data){global $wpdb;$ok=$wpdb->insert($this->table(),$data,array('%d','%d','%d','%s','%s','%s','%s','%s','%d'));return $ok?absint($wpdb->insert_id):false;}
 public function find($id){global $wpdb;return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->table().' WHERE id=%d',absint($id)));}
 public function delete($id){global $wpdb;return false!==$wpdb->delete($this->table(),array('id'=>absint($id)),array('%d'));}
}
function gdcp_calendar_repository(){static $r;if(!$r)$r=new GDCP_Calendar_Repository();return $r;}
