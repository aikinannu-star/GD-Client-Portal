<?php
if (!defined('ABSPATH')) exit;

class GDCP_SLA_Repository {
    public function table() { global $wpdb; return $wpdb->prefix . 'gd_project_slas'; }
    public function find_by_project($project_id) {
        global $wpdb; $id=absint($project_id); if(!$id) return false;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->table().' WHERE project_id=%d',$id));
    }
    public function create(array $data) {
        global $wpdb; $ok=$wpdb->insert($this->table(),$data,array('%d','%d','%s','%s','%s','%d','%d','%s'));
        return $ok===false ? false : absint($wpdb->insert_id);
    }
    public function update_by_project($project_id,array $data) {
        global $wpdb; $ok=$wpdb->update($this->table(),$data,array('project_id'=>absint($project_id)),null,array('%d'));
        return $ok===false ? false : true;
    }
    public function due_projects($now,$cutoff,$limit=100) {
        global $wpdb; $p=gd_client_portal_get_project_table_name(); $s=$this->table();
        $limit=max(1,min(100,absint($limit)));
        $sql="SELECT p.*,s.* FROM {$p} p LEFT JOIN {$s} s ON s.project_id=p.id WHERE p.status NOT IN ('completed','cancelled','closed') AND ((s.due_date IS NOT NULL AND s.due_date < %s) OR (s.stage_due_date IS NOT NULL AND s.stage_due_date < %s)) AND (s.last_escalated_at IS NULL OR s.last_escalated_at < %s) LIMIT %d";
        return $wpdb->get_results($wpdb->prepare($sql,$now,$now,$cutoff,$limit));
    }
    public function project_list($mode='all',$limit=20) {
        global $wpdb; $params=array(); $where=gd_client_portal_operations_scope_project_where($params); $p=gd_client_portal_get_project_table_name(); $s=$this->table();
        $sql="SELECT p.*,s.priority,s.due_date,s.stage_due_date,s.warning_days,s.escalation_level FROM {$p} p LEFT JOIN {$s} s ON s.project_id=p.id WHERE p.status NOT IN ('completed','cancelled','closed') {$where}";
        if($mode==='overdue') { $now=current_time('mysql'); $params[]=$now; $params[]=$now; $params[]=gmdate('Y-m-d H:i:s',time()-14*DAY_IN_SECONDS); $sql.=" AND ((s.due_date IS NOT NULL AND s.due_date < %s) OR (s.stage_due_date IS NOT NULL AND s.stage_due_date < %s) OR (s.id IS NULL AND p.created_at < %s))"; }
        if($mode==='at_risk') { $now=current_time('mysql'); $warn=gmdate('Y-m-d H:i:s',time()+2*DAY_IN_SECONDS); array_push($params,$now,$warn,$now,$warn); $sql.=" AND ((s.due_date IS NOT NULL AND s.due_date BETWEEN %s AND %s) OR (s.stage_due_date IS NOT NULL AND s.stage_due_date BETWEEN %s AND %s))"; }
        $sql.=' ORDER BY COALESCE(s.due_date,p.created_at) ASC LIMIT %d'; $params[]=max(1,min(100,absint($limit)));
        return $wpdb->get_results($wpdb->prepare($sql,$params));
    }
}
function gdcp_sla_repository() { static $repo; if(!$repo) $repo=new GDCP_SLA_Repository(); return $repo; }
