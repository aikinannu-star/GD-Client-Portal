<?php
if(!defined('ABSPATH')) exit;
class GDCP_Intake_Repository {
    public function forms_table(){ return gd_client_portal_intake_forms_table(); }
    public function submissions_table(){ return gd_client_portal_intake_submissions_table(); }
    public function form($id){ global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->forms_table().' WHERE id=%d',absint($id))); }
    public function submission($id){ global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->submissions_table().' WHERE id=%d',absint($id))); }
    public function submission_for_project($project_id){ global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->submissions_table().' WHERE project_id=%d ORDER BY id DESC LIMIT 1',absint($project_id))); }
    public function find_form($project){ global $wpdb; if(!$project)return null; $table=$this->forms_table(); $queries=array(
        array('SELECT * FROM '.$table.' WHERE status="active" AND tenant_id=%d AND product_id=%d ORDER BY id DESC LIMIT 1',array(absint($project->tenant_id),absint($project->product_id??0))),
        array('SELECT * FROM '.$table.' WHERE status="active" AND tenant_id=%d AND service_type=%s ORDER BY id DESC LIMIT 1',array(absint($project->tenant_id),sanitize_key($project->service_type??''))),
        array('SELECT * FROM '.$table.' WHERE status="active" AND tenant_id=0 AND product_id=%d ORDER BY id DESC LIMIT 1',array(absint($project->product_id??0))),
        array('SELECT * FROM '.$table.' WHERE status="active" AND tenant_id=0 AND product_id=0 AND service_type=%s ORDER BY id DESC LIMIT 1',array(sanitize_key($project->service_type??''))),
        array('SELECT * FROM '.$table.' WHERE status="active" AND tenant_id=0 AND product_id=0 AND service_type="" ORDER BY id DESC LIMIT 1',array()),
    ); foreach($queries as $q){$row=$q[1]?$wpdb->get_row($wpdb->prepare($q[0],$q[1])):$wpdb->get_row($q[0]);if($row)return $row;} return null; }
    public function create_form($data){ global $wpdb; $ok=$wpdb->insert($this->forms_table(),$data); return $ok?absint($wpdb->insert_id):0; }
    public function update_form($id,$data){ global $wpdb; return $wpdb->update($this->forms_table(),$data,array('id'=>absint($id))); }
    public function delete_form($id){ global $wpdb; return $wpdb->delete($this->forms_table(),array('id'=>absint($id)),array('%d')); }
    public function create_submission($data){ global $wpdb; $ok=$wpdb->insert($this->submissions_table(),$data,array('%d','%d','%d','%d','%s','%d','%s','%s','%s')); return $ok?absint($wpdb->insert_id):0; }
    public function update_submission($id,$data){ global $wpdb; return $wpdb->update($this->submissions_table(),$data,array('id'=>absint($id))); }
    public function list_forms($tenant=0,$platform_admin=false,$limit=200){ global $wpdb; $where=$platform_admin?'':' WHERE tenant_id='.absint($tenant); return $wpdb->get_results('SELECT * FROM '.$this->forms_table().$where.' ORDER BY id DESC LIMIT '.absint($limit)); }
}
function gdcp_intake_repository(){ static $r=null; if(!$r)$r=new GDCP_Intake_Repository(); return $r; }
