<?php
/** Canonical Delivery persistence boundary. */
if (!defined('ABSPATH')) exit;

class GDCP_Delivery_Repository {
    public function table() { return gd_client_portal_get_delivery_table_name(); }
    public function find($id) { global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->table().' WHERE id=%d', absint($id))); }
    public function list_for_project($project_id, $published_only=false) {
        global $wpdb;
        $where = $published_only ? " AND status IN ('published','approved')" : '';
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.$this->table().' WHERE project_id=%d'.$where.' ORDER BY version DESC, id DESC', absint($project_id)));
    }
    public function latest_for_project($project_id, $published_only=true) {
        global $wpdb;
        $where = $published_only ? " AND status IN ('published','approved')" : '';
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->table().' WHERE project_id=%d'.$where.' ORDER BY version DESC, id DESC LIMIT 1', absint($project_id)));
    }
    public function next_version($project_id) {
        global $wpdb;
        return max(1, absint($wpdb->get_var($wpdb->prepare('SELECT MAX(version) FROM '.$this->table().' WHERE project_id=%d', absint($project_id)))) + 1);
    }
    public function supersede_published($project_id) {
        global $wpdb;
        return $wpdb->query($wpdb->prepare("UPDATE {$this->table()} SET status='superseded' WHERE project_id=%d AND status='published'", absint($project_id)));
    }
    public function insert($data) {
        global $wpdb;
        $formats = array('%d','%d','%d','%s','%s','%s','%s','%d','%s');
        return $wpdb->insert($this->table(), $data, $formats) ? absint($wpdb->insert_id) : 0;
    }
    public function approve_published($id, $now) {
        global $wpdb;
        $changed = $wpdb->update($this->table(), array('status'=>'approved','approved_at'=>$now), array('id'=>absint($id),'status'=>'published'), array('%s','%s'), array('%d','%s'));
        return $changed === 1;
    }
}
function gdcp_delivery_repository(){ static $r; return $r ?: ($r = new GDCP_Delivery_Repository()); }
