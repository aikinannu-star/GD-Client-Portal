<?php
if (!defined('ABSPATH')) exit;

/** Canonical persistence boundary for client onboarding. */
final class GDCP_Onboarding_Repository extends GDCP_Base_Repository {
    public function table(){ return $this->db()->prefix.'gd_client_onboarding'; }
    public function documents_table(){ return $this->db()->prefix.'gd_client_onboarding_documents'; }

    public function find($id,$lock=false){
        $sql='SELECT * FROM '.$this->table().' WHERE id=%d';
        if($lock) $sql.=' FOR UPDATE';
        return $this->db()->get_row($this->db()->prepare($sql,absint($id)));
    }
    public function find_for_project($project_id,$lock=false){
        $sql='SELECT * FROM '.$this->table().' WHERE project_id=%d ORDER BY id DESC LIMIT 1';
        if($lock) $sql.=' FOR UPDATE';
        return $this->db()->get_row($this->db()->prepare($sql,absint($project_id)));
    }
    public function create_for_project($project){
        if(!$project) return 0;
        $existing=$this->find_for_project($project->id);
        if($existing) return absint($existing->id);
        $ok=$this->db()->insert($this->table(),array(
            'tenant_id'=>absint($project->tenant_id),'user_id'=>absint($project->user_id),'project_id'=>absint($project->id),
            'status'=>'not_started','current_step'=>1
        ),array('%d','%d','%d','%s','%d'));
        return $ok ? absint($this->db()->insert_id) : 0;
    }
    public function update($id,$data){
        $allowed=array('user_id','status','current_step','company_name','contact_name','goals','timeline','budget_range','notes','updated_at','completed_at');
        $clean=array(); foreach($allowed as $field){ if(array_key_exists($field,$data)) $clean[$field]=$data[$field]; }
        if(!$clean) return false;
        return $this->db()->update($this->table(),$clean,array('id'=>absint($id)));
    }
    public function add_document($row){
        if(empty($row['onboarding_id'])) return 0;
        $ok=$this->db()->insert($this->documents_table(),array(
            'onboarding_id'=>absint($row['onboarding_id']),'project_id'=>absint($row['project_id']??0),
            'tenant_id'=>absint($row['tenant_id']??0),'user_id'=>absint($row['user_id']??0),
            'title'=>sanitize_text_field($row['title']??''),'file_url'=>esc_url_raw($row['file_url']??''),
            'mime_type'=>sanitize_text_field($row['mime_type']??'')
        ),array('%d','%d','%d','%d','%s','%s','%s'));
        return $ok ? absint($this->db()->insert_id) : 0;
    }
    public function documents_for($onboarding_id){
        return $this->db()->get_results($this->db()->prepare('SELECT * FROM '.$this->documents_table().' WHERE onboarding_id=%d ORDER BY id DESC',absint($onboarding_id)));
    }
}
