<?php
/** Canonical Collaboration persistence boundary. */
if (!defined('ABSPATH')) exit;

class GDCP_Collaboration_Repository {
    public function table($suffix) { return gd_client_portal_collab_table($suffix); }
    public function find_task($id, $project_id=0) { global $wpdb; $sql='SELECT * FROM '.$this->table('project_tasks').' WHERE id=%d'; $args=array(absint($id)); if($project_id){$sql.=' AND project_id=%d';$args[]=absint($project_id);} return $wpdb->get_row($wpdb->prepare($sql,$args)); }
    public function list_tasks($project_id=0,$tenant_id=0,$limit=100) { global $wpdb; $where='WHERE 1=1';$args=array(); if($project_id){$where.=' AND project_id=%d';$args[]=absint($project_id);} elseif($tenant_id){$where.=' AND tenant_id=%d';$args[]=absint($tenant_id);} $args[]=max(1,min(300,absint($limit))); return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.$this->table('project_tasks')." {$where} ORDER BY CASE status WHEN 'in_progress' THEN 1 WHEN 'todo' THEN 2 WHEN 'blocked' THEN 3 ELSE 4 END, due_date IS NULL, due_date ASC, id DESC LIMIT %d",$args)); }
    public function insert_task($data) { global $wpdb; $formats=array('%d','%d','%s','%s','%s','%s','%d','%s','%d','%s','%s'); $ok=$wpdb->insert($this->table('project_tasks'),$data,$formats); return $ok ? absint($wpdb->insert_id) : 0; }
    public function update_task($id,$data) { global $wpdb; $ok=$wpdb->update($this->table('project_tasks'),$data,array('id'=>absint($id)),array('%s','%s','%s','%s','%d','%s','%s','%s'),array('%d')); return $ok!==false; }
    public function delete_task($id) { global $wpdb; return $wpdb->delete($this->table('project_tasks'),array('id'=>absint($id)),array('%d'))!==false; }
    public function list_updates($project_id=0,$tenant_id=0,$limit=30,$include_internal=false) { global $wpdb; $where='WHERE 1=1';$args=array(); if($project_id){$where.=' AND project_id=%d';$args[]=absint($project_id);} elseif($tenant_id){$where.=' AND tenant_id=%d';$args[]=absint($tenant_id);} if(!$include_internal)$where.=" AND visibility='client'"; $args[]=max(1,min(100,absint($limit))); return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.$this->table('project_updates')." {$where} ORDER BY id DESC LIMIT %d",$args)); }
    public function insert_update($data) { global $wpdb; $ok=$wpdb->insert($this->table('project_updates'),$data,array('%d','%d','%d','%s','%s','%s','%s')); return $ok ? absint($wpdb->insert_id) : 0; }
    public function list_notes($project_id,$limit=100) { global $wpdb; return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.$this->table('project_internal_notes').' WHERE project_id=%d ORDER BY id DESC LIMIT %d',absint($project_id),max(1,min(200,absint($limit))))); }
    public function insert_note($data) { global $wpdb; $ok=$wpdb->insert($this->table('project_internal_notes'),$data,array('%d','%d','%d','%s','%s','%s')); return $ok ? absint($wpdb->insert_id) : 0; }
}
function gdcp_collaboration_repository(){ static $r; return $r?:($r=new GDCP_Collaboration_Repository()); }
