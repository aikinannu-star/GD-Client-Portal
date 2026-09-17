<?php
if (!defined('ABSPATH')) exit;

final class GDCP_Notifications_Repository {
    private function db() { global $wpdb; return $wpdb; }
    public function table() { return function_exists('gd_client_portal_notifications_table') ? gd_client_portal_notifications_table() : $this->db()->prefix.'gd_client_portal_notifications'; }
    public function insert(array $data) {
        $allowed=array('user_id'=>'%d','tenant_id'=>'%d','project_id'=>'%d','type'=>'%s','title'=>'%s','body'=>'%s','is_read'=>'%d','created_at'=>'%s','event_key'=>'%s');
        $row=array(); $fmt=array(); foreach($allowed as $field=>$format){ if(array_key_exists($field,$data)){ $row[$field]=$data[$field]; $fmt[]=$format; } }
        if(!$row || !$this->db()->insert($this->table(),$row,$fmt)) return false;
        return absint($this->db()->insert_id);
    }
    /** Return an existing notification for an explicit idempotency key. */
    public function find_by_event_key($user_id,$event_key){
        $user_id=absint($user_id); $event_key=sanitize_key($event_key);
        if(!$user_id||!$event_key) return null;
        return $this->db()->get_row($this->db()->prepare('SELECT * FROM '.$this->table().' WHERE user_id=%d AND event_key=%s LIMIT 1',$user_id,$event_key));
    }

    public function find_for_user($id,$user_id=0){ $user_id=$user_id?absint($user_id):get_current_user_id(); if(!$user_id||!$id)return null; return $this->db()->get_row($this->db()->prepare('SELECT * FROM '.$this->table().' WHERE id=%d AND user_id=%d LIMIT 1',absint($id),$user_id)); }
    public function list_for_user($user_id=0,$limit=50){ $user_id=$user_id?absint($user_id):get_current_user_id(); if(!$user_id)return array(); $limit=max(1,min(100,absint($limit))); return $this->db()->get_results($this->db()->prepare('SELECT * FROM '.$this->table().' WHERE user_id=%d ORDER BY created_at DESC,id DESC LIMIT %d',$user_id,$limit)); }
    public function unread_count($user_id=0){ $user_id=$user_id?absint($user_id):get_current_user_id(); if(!$user_id)return 0; return (int)$this->db()->get_var($this->db()->prepare('SELECT COUNT(*) FROM '.$this->table().' WHERE user_id=%d AND is_read=0',$user_id)); }
    public function mark_read($id,$user_id=0){ $user_id=$user_id?absint($user_id):get_current_user_id(); if(!$id||!$user_id)return false; return false!==$this->db()->update($this->table(),array('is_read'=>1),array('id'=>absint($id),'user_id'=>$user_id),array('%d'),array('%d','%d')); }
    public function mark_all_read($user_id=0){ $user_id=$user_id?absint($user_id):get_current_user_id(); if(!$user_id)return false; return false!==$this->db()->update($this->table(),array('is_read'=>1),array('user_id'=>$user_id,'is_read'=>0),array('%d'),array('%d','%d')); }
}
function gdcp_notifications_repository(){ static $repo; if(!$repo)$repo=new GDCP_Notifications_Repository(); return $repo; }
