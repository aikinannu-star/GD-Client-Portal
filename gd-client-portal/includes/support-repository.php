<?php
if(!defined('ABSPATH')) exit;

/** Persistence boundary for the Support domain. */
if(!class_exists('GDCP_Support_Repository')):
final class GDCP_Support_Repository {
    public function table($name){ return gd_client_portal_support_table($name); }
    public function find($id){ global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->table('tickets').' WHERE id=%d',absint($id))); }
    public function list_tickets($where,$params,$limit){ global $wpdb; $sql='SELECT * FROM '.$this->table('tickets').($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY CASE priority WHEN \'urgent\' THEN 1 WHEN \'high\' THEN 2 ELSE 3 END, updated_at DESC LIMIT %d'; $params[]=$limit; return $wpdb->get_results($wpdb->prepare($sql,$params)); }
    public function create_ticket($data){ global $wpdb; $ok=$wpdb->insert($this->table('tickets'),$data); return $ok===false?0:absint($wpdb->insert_id); }
    public function create_message($data){ global $wpdb; $ok=$wpdb->insert($this->table('messages'),$data); return $ok===false?0:absint($wpdb->insert_id); }
    public function update_ticket($id,$data){ global $wpdb; return $wpdb->update($this->table('tickets'),$data,array('id'=>absint($id)))!==false; }

    // Compatibility aliases for the application-layer repository API.
    public function ticket($id){ return $this->find($id); }
    public function tickets($limit=100){ $limit=max(1,min(300,absint($limit))); return $this->list_tickets(array(),array(),$limit); }
    public function messages($id,$internal=false){ global $wpdb; $sql='SELECT * FROM '.$this->table('messages').' WHERE ticket_id=%d'; if(!$internal)$sql.=' AND is_internal=0'; $sql.=' ORDER BY id ASC'; return $wpdb->get_results($wpdb->prepare($sql,absint($id))); }
    public function overdue($now,$limit=100){ global $wpdb; $t=$this->table('tickets'); return $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE status NOT IN ('resolved','closed') AND due_at IS NOT NULL AND due_at < %s LIMIT %d",$now,max(1,min(300,absint($limit))))); }
}
endif;
