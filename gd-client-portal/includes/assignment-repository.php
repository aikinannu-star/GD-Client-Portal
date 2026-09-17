<?php
if (!defined('ABSPATH')) exit;
final class GDCP_Assignment_Repository {
    public function table(){ return gd_client_portal_assignments_table(); }
    public function for_project($project_id){ global $wpdb; return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.$this->table().' WHERE project_id=%d ORDER BY CASE WHEN assignment_role="lead" THEN 0 ELSE 1 END, created_at ASC',absint($project_id))); }
    public function for_tenant_workload($tenant_id,$limit=100){ global $wpdb; $a=$this->table(); $p=gd_client_portal_get_project_table_name(); $limit=max(1,min(200,absint($limit))); $sql="SELECT a.user_id, MAX(CASE WHEN a.assignment_role='lead' THEN 1 ELSE 0 END) AS lead_count, COUNT(DISTINCT CASE WHEN p.status NOT IN ('completed','cancelled','closed') THEN a.project_id END) AS active_count, COUNT(DISTINCT CASE WHEN p.current_stage IN ('ready_for_review','approval','client_review','revision','revisions','revision_requested') THEN a.project_id END) AS attention_count FROM {$a} a LEFT JOIN {$p} p ON p.id=a.project_id WHERE a.tenant_id=%d GROUP BY a.user_id ORDER BY active_count DESC, attention_count DESC LIMIT %d"; return $wpdb->get_results($wpdb->prepare($sql,absint($tenant_id),$limit)); }
    public function replace($data){ global $wpdb; return false !== $wpdb->replace($this->table(),$data,array('%d','%d','%d','%s','%d')); }
    public function demote_leads($project_id){ global $wpdb; return false !== $wpdb->update($this->table(),array('assignment_role'=>'member'),array('project_id'=>absint($project_id),'assignment_role'=>'lead'),array('%s'),array('%d','%s')); }
    public function delete($project_id,$user_id){ global $wpdb; return false !== $wpdb->delete($this->table(),array('project_id'=>absint($project_id),'user_id'=>absint($user_id)),array('%d','%d')); }
}
function gdcp_assignment_repository(){ static $r; if(!$r)$r=new GDCP_Assignment_Repository(); return $r; }
