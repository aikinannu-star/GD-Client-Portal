<?php
/** Persistent security incident response boundary. */
if (!defined('ABSPATH')) exit;

final class GDCP_Security_Incident_Repository {
    public function table() { global $wpdb; return $wpdb->prefix . 'gdcp_security_incidents'; }
    public function actions_table() { global $wpdb; return $wpdb->prefix . 'gdcp_security_incident_actions'; }
    public function install() {
        global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php'; $c=$wpdb->get_charset_collate();
        $t=$this->table(); $a=$this->actions_table();
        $sql1="CREATE TABLE {$t} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, incident_key varchar(80) NOT NULL, created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, tenant_id bigint(20) unsigned NOT NULL DEFAULT 0, severity varchar(20) NOT NULL DEFAULT 'medium', category varchar(40) NOT NULL DEFAULT 'authorization', status varchar(20) NOT NULL DEFAULT 'open', title varchar(190) NOT NULL DEFAULT '', created_by bigint(20) unsigned NOT NULL DEFAULT 0, owner_user_id bigint(20) unsigned NOT NULL DEFAULT 0, ack_due_at datetime NULL, resolve_due_at datetime NULL, PRIMARY KEY(id), UNIQUE KEY incident_key(incident_key), KEY tenant_status(tenant_id,status), KEY created_at(created_at), KEY severity_status(severity,status) ) {$c};";
        $sql2="CREATE TABLE {$a} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, incident_id bigint(20) unsigned NOT NULL, action_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0, action_type varchar(30) NOT NULL, note varchar(1000) NOT NULL DEFAULT '', evidence_ref varchar(120) NOT NULL DEFAULT '', previous_hash char(64) NOT NULL DEFAULT '', action_hash char(64) NOT NULL DEFAULT '', PRIMARY KEY(id), KEY incident_action(incident_id,id), KEY action_time(action_time), KEY actor_user_id(actor_user_id), KEY action_hash(action_hash) ) {$c};";
        dbDelta($sql1); dbDelta($sql2); return empty($wpdb->last_error);
    }
    private function safe_key($v){ $v=sanitize_key($v); return $v ?: 'incident'; }
    public function create($data=array()) {
        global $wpdb; $data=is_array($data)?$data:array(); $key=$this->safe_key($data['incident_key']??('incident-'.wp_generate_uuid4()));
        $row=array('incident_key'=>$key,'tenant_id'=>absint($data['tenant_id']??0),'severity'=>sanitize_key($data['severity']??'medium'),'category'=>sanitize_key($data['category']??'authorization'),'status'=>'open','title'=>sanitize_text_field($data['title']??''),'created_by'=>get_current_user_id());
        if($wpdb->insert($this->table(),$row,array('%s','%d','%s','%s','%s','%s','%d'))===false) return false;
        $id=absint($wpdb->insert_id); $this->add_action($id,'created','Incident created.',isset($data['evidence_ref'])?$data['evidence_ref']:''); return $id;
    }
    public function find($id){ global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->table().' WHERE id=%d',$id)); }
    public function list($filters=array(),$limit=100){
        global $wpdb; $limit=min(200,max(1,absint($limit))); $w=array('1=1');$a=array();
        foreach(array('tenant_id'=>'%d','severity'=>'%s','category'=>'%s','status'=>'%s') as $k=>$fmt) if(isset($filters[$k])&&$filters[$k]!==''){ $w[]="$k = $fmt"; $a[]=$fmt==='%d'?absint($filters[$k]):sanitize_key($filters[$k]); }
        if(!empty($filters['from'])){$w[]='created_at >= %s';$a[]=sanitize_text_field($filters['from']);} if(!empty($filters['to'])){$w[]='created_at <= %s';$a[]=sanitize_text_field($filters['to']);}
        $sql='SELECT * FROM '.$this->table().' WHERE '.implode(' AND ',$w).' ORDER BY id DESC LIMIT '.$limit; return $a?$wpdb->get_results($wpdb->prepare($sql,$a)):$wpdb->get_results($sql);
    }
    public function list_for_user($user_id, $filters=array(), $limit=100) {
        $user_id = absint($user_id);
        if (!$user_id) return array();
        if (user_can($user_id, 'manage_options')) return $this->list($filters, $limit);
        if (!function_exists('gd_client_portal_get_user_tenant_id')) return array();
        $tenant_id = absint(gd_client_portal_get_user_tenant_id($user_id));
        if (!$tenant_id || !user_can($user_id, 'gd_client_portal_tenant_admin')) return array();
        $filters['tenant_id'] = $tenant_id;
        return $this->list($filters, $limit);
    }

    public function governed_assign_owner($incident_id, $owner_user_id) {
        $incident = $this->find(absint($incident_id));
        $owner_user_id = absint($owner_user_id);
        if (!$incident || !$owner_user_id || !function_exists('gdcp_security_incident_can_manage') || !gdcp_security_incident_can_manage($incident)) return false;
        $owner = get_userdata($owner_user_id);
        if (!$owner) return false;
        $eligible = user_can($owner_user_id, 'manage_options');
        if (!$eligible && absint($incident->tenant_id) > 0 && function_exists('gd_client_portal_get_user_tenant_id')) {
            $eligible = user_can($owner_user_id, 'gd_client_portal_tenant_admin') && absint(gd_client_portal_get_user_tenant_id($owner_user_id)) === absint($incident->tenant_id);
        }
        if (!$eligible) return false;
        return $this->assign_owner($incident_id, $owner_user_id);
    }

    public function assign_owner($incident_id, $owner_user_id) {
        global $wpdb; $incident_id=absint($incident_id); $owner_user_id=absint($owner_user_id);
        if (!$this->find($incident_id) || !$owner_user_id) return false;
        $ok=$wpdb->update($this->table(),array('owner_user_id'=>$owner_user_id),array('id'=>$incident_id),array('%d'),array('%d'));
        if ($ok !== false) $this->add_action($incident_id,'owner_assigned','Incident ownership assigned to the designated administrator.','owner-'.$owner_user_id);
        return $ok !== false;
    }

    public function governed_action($incident_id, $type, $note='', $evidence_ref='') {
        $incident=$this->find(absint($incident_id));
        if (!$incident || !function_exists('gdcp_security_incident_can_manage') || !gdcp_security_incident_can_manage($incident)) return false;
        return $this->add_action($incident_id,$type,$note,$evidence_ref);
    }

    public function add_action($incident_id,$type,$note='',$evidence_ref=''){
        global $wpdb; $incident_id=absint($incident_id); if(!$this->find($incident_id)) return false;
        $note=sanitize_textarea_field($note); $evidence_ref=sanitize_key($evidence_ref); $prev=(string)$wpdb->get_var($wpdb->prepare('SELECT action_hash FROM '.$this->actions_table().' WHERE incident_id=%d ORDER BY id DESC LIMIT 1',$incident_id));
        $row=array('incident_id'=>$incident_id,'actor_user_id'=>get_current_user_id(),'action_type'=>sanitize_key($type),'note'=>$note,'evidence_ref'=>$evidence_ref,'previous_hash'=>$prev);
        $canonical=wp_json_encode(array('incident_id'=>$incident_id,'actor_user_id'=>(int)$row['actor_user_id'],'action_type'=>$row['action_type'],'note'=>$note,'evidence_ref'=>$evidence_ref,'previous_hash'=>$prev)); $row['action_hash']=hash_hmac('sha256',(string)$canonical,wp_salt('auth'));
        if($wpdb->insert($this->actions_table(),$row,array('%d','%d','%s','%s','%s','%s','%s'))===false)return false;
        $new_status = $row['action_type']==='resolved'?'resolved':($row['action_type']==='reopened'?'open':($row['action_type']==='acknowledged'?'acknowledged':(string)$this->find($incident_id)->status));
        if(in_array($row['action_type'],array('acknowledged','resolved','reopened'),true)) $wpdb->update($this->table(),array('status'=>$new_status),array('id'=>$incident_id),array('%s'),array('%d'));
        $action_id = absint($wpdb->insert_id);
        if ($action_id && in_array($row['action_type'], array('acknowledged','resolved','reopened'), true)) {
            do_action('gdcp_security_incident_action_recorded', $incident_id, $row['action_type'], array('status' => $new_status, 'action_id' => $action_id));
        }
        return $action_id;
    }
    public function actions($incident_id,$limit=100){ global $wpdb; $limit=min(200,max(1,absint($limit))); return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.$this->actions_table().' WHERE incident_id=%d ORDER BY id ASC LIMIT '.$limit,absint($incident_id))); }
    public function verify_actions($incident_id){ $prev=''; foreach($this->actions($incident_id,200) as $r){$canonical=wp_json_encode(array('incident_id'=>(int)$r->incident_id,'actor_user_id'=>(int)$r->actor_user_id,'action_type'=>$r->action_type,'note'=>$r->note,'evidence_ref'=>$r->evidence_ref,'previous_hash'=>$prev));$expected=hash_hmac('sha256',(string)$canonical,wp_salt('auth'));if(!hash_equals((string)$r->action_hash,$expected)||$r->previous_hash!==$prev)return array('ok'=>false,'bad_id'=>(int)$r->id);$prev=$r->action_hash;}return array('ok'=>true,'bad_id'=>0); }
}
function gdcp_security_incident_repository(){static $r=null;if(!$r)$r=new GDCP_Security_Incident_Repository();return $r;}
