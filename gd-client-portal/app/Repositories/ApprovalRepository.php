<?php
if (!defined('ABSPATH')) exit;

/** Canonical persistence boundary for project approvals. */
final class GDCP_Approval_Repository extends GDCP_Base_Repository {
    public function table(){ return $this->db()->prefix.'gd_project_approvals'; }

    public function find_active_for_project($project_id,$tenant_id=0,$lock=false){
        $project_id=absint($project_id); $tenant_id=absint($tenant_id); if(!$project_id) return null;
        $sql='SELECT * FROM '.$this->table().' WHERE project_id=%d AND status=\'pending\'';
        $args=array($project_id);
        if($tenant_id){ $sql.=' AND tenant_id=%d'; $args[]=$tenant_id; }
        $sql.=' ORDER BY id DESC LIMIT 1'; if($lock) $sql.=' FOR UPDATE';
        return $this->db()->get_row($this->db()->prepare($sql,$args));
    }

    public function latest_version($project_id,$tenant_id=0){
        $project_id=absint($project_id); $tenant_id=absint($tenant_id); if(!$project_id) return 0;
        $sql='SELECT MAX(version) FROM '.$this->table().' WHERE project_id=%d'; $args=array($project_id);
        if($tenant_id){ $sql.=' AND tenant_id=%d'; $args[]=$tenant_id; }
        return absint($this->db()->get_var($this->db()->prepare($sql,$args)));
    }

    public function pending_for_tenant($tenant_id=0,$limit=20){$sql='SELECT * FROM '.$this->table().' WHERE status=\'pending\'';$args=array();if($tenant_id){$sql.=' AND tenant_id=%d';$args[]=$tenant_id;}$sql.=' ORDER BY created_at DESC LIMIT %d';$args[]=max(1,min(100,absint($limit)));return $this->db()->get_results($this->db()->prepare($sql,$args));}

    public function create_pending($project_id,$tenant_id,$version,$requested_by){
        return $this->db()->insert($this->table(),array(
            'project_id'=>absint($project_id),'tenant_id'=>absint($tenant_id),'version'=>absint($version),
            'status'=>'pending','requested_by'=>absint($requested_by),'created_at'=>current_time('mysql')
        ),array('%d','%d','%d','%s','%d','%s')) ? absint($this->db()->insert_id) : 0;
    }

    public function decide_pending($id,$project_id,$tenant_id,$decision,$actor,$comment){
        return $this->db()->query($this->db()->prepare(
            'UPDATE '.$this->table().' SET status=%s,decided_by=%d,client_comment=%s,decided_at=%s WHERE id=%d AND project_id=%d AND tenant_id=%d AND status=\'pending\'',
            $decision,absint($actor),$comment,current_time('mysql'),absint($id),absint($project_id),absint($tenant_id)
        ));
    }

    public function find_approved_for_project_version($project_id,$tenant_id,$version,$lock=false){
        $sql="SELECT * FROM ".$this->table()." WHERE project_id=%d AND tenant_id=%d AND status='approved' AND version=%d ORDER BY id DESC LIMIT 1";
        if($lock) $sql.=' FOR UPDATE';
        return $this->db()->get_row($this->db()->prepare($sql,absint($project_id),absint($tenant_id),absint($version)));
    }

    public function history($project_id,$limit=8){
        return $this->db()->get_results($this->db()->prepare('SELECT * FROM '.$this->table().' WHERE project_id=%d ORDER BY id DESC LIMIT %d',absint($project_id),max(1,min(50,absint($limit)))));
    }
}
