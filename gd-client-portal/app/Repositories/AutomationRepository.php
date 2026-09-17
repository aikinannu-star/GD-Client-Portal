<?php
if (!defined('ABSPATH')) exit;
final class GDCP_Automation_Repository extends GDCP_Base_Repository {
    public function table($suffix='rules'){return function_exists('gd_client_portal_automation_table')?gd_client_portal_automation_table($suffix):$this->db()->prefix.'gd_portal_automation_'.$suffix;}
    public function find($id){return function_exists('gd_client_portal_automation_get_rule')?gd_client_portal_automation_get_rule($id):$this->db()->get_row($this->db()->prepare('SELECT * FROM '.$this->table().' WHERE id=%d',absint($id)));}
    public function active($limit=100){$sql='SELECT * FROM '.$this->table().' WHERE status=%s ORDER BY id ASC LIMIT %d';return $this->db()->get_results($this->db()->prepare($sql,'active',max(1,min(500,absint($limit)))));}
    public function list_rules($status=''){ if($status)return $this->db()->get_results($this->db()->prepare('SELECT * FROM '.$this->table('rules').' WHERE status=%s ORDER BY id ASC',sanitize_key($status))); return $this->db()->get_results('SELECT * FROM '.$this->table('rules').' ORDER BY id DESC'); }
    public function create_rule($data){return $this->db()->insert($this->table('rules'),$data)===false?0:absint($this->db()->insert_id);}
    public function update_rule($id,$data){return $this->db()->update($this->table('rules'),$data,array('id'=>absint($id)))!==false;}
    public function delete_rule($id){return $this->db()->delete($this->table('rules'),array('id'=>absint($id)),array('%d'))!==false;}
    public function increment_run($id,$when){return $this->db()->query($this->db()->prepare('UPDATE '.$this->table('rules').' SET run_count=run_count+1,last_run_at=%s WHERE id=%d',$when,absint($id)))===1;}
    public function get_template($id){return $this->db()->get_row($this->db()->prepare('SELECT * FROM '.$this->table('templates').' WHERE id=%d',absint($id)));}
    public function list_templates($limit=20){return $this->db()->get_results($this->db()->prepare('SELECT * FROM '.$this->table('templates').' ORDER BY id DESC LIMIT %d',max(1,absint($limit))));}
    public function create_template($data){return $this->db()->insert($this->table('templates'),$data)===false?0:absint($this->db()->insert_id);}
    public function list_logs($limit=25){return $this->db()->get_results($this->db()->prepare('SELECT * FROM '.$this->table('logs').' ORDER BY id DESC LIMIT %d',max(1,absint($limit))));}
    public function create_log($data){return $this->db()->insert($this->table('logs'),$data)===false?0:absint($this->db()->insert_id);}
    public function list_queue($limit=100){return $this->db()->get_results($this->db()->prepare('SELECT * FROM '.$this->table('queue').' ORDER BY run_at ASC, id DESC LIMIT %d',max(1,absint($limit))));}
    public function get_queue($id){return $this->db()->get_row($this->db()->prepare('SELECT * FROM '.$this->table('queue').' WHERE id=%d',absint($id)));}
    public function list_queue_for_scope($statuses,$tenant=0,$platform=false,$limit=100){$statuses=array_values(array_filter(array_map('sanitize_key',(array)$statuses)));if(!$statuses)return array();$ph=implode(',',array_fill(0,count($statuses),'%s'));$args=$statuses;$where="status IN ($ph)";if(!$platform){$where.=' AND tenant_id=%d';$args[]=absint($tenant);} $args[]=max(1,min(300,absint($limit)));return $this->db()->get_results($this->db()->prepare('SELECT id,project_id,event_key,status,attempts,max_attempts,updated_at,tenant_id FROM '.$this->table().' WHERE '.$where.' ORDER BY id DESC LIMIT %d',$args));}

    public function create_queue($data){return $this->db()->insert($this->table('queue'),$data)===false?0:absint($this->db()->insert_id);}
    public function update_queue($id,$data,$where_extra=array()){return $this->db()->update($this->table('queue'),$data,array_merge(array('id'=>absint($id)),$where_extra))!==false;}
    public function claim_queue($id){$now=current_time('mysql');return $this->db()->update($this->table('queue'),array('status'=>'running','locked_at'=>$now,'last_attempt_at'=>$now,'updated_at'=>$now),array('id'=>absint($id),'status'=>'queued'))===1;}
    public function stale_queue($limit=50){return $this->db()->get_results($this->db()->prepare("SELECT id,attempts,max_attempts FROM {$this->table('queue')} WHERE status='running' AND updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE) LIMIT %d",max(1,absint($limit))));}
    public function due_queue($limit=50){return $this->db()->get_col($this->db()->prepare("SELECT id FROM {$this->table('queue')} WHERE status='queued' AND run_at <= UTC_TIMESTAMP() ORDER BY id ASC LIMIT %d",max(1,absint($limit))));}
    public function list_queue_by_status($statuses,$limit=100){$statuses=array_values(array_filter(array_map('sanitize_key',(array)$statuses)));if(!$statuses)return array();$ph=implode(',',array_fill(0,count($statuses),'%s'));$args=$statuses;$args[]=max(1,min(200,absint($limit)));return $this->db()->get_results($this->db()->prepare('SELECT * FROM '.$this->table('queue').' WHERE status IN ('.$ph.') ORDER BY updated_at DESC LIMIT %d',$args));}
    public function stats(){ $q=$this->table('queue'); return array('queued'=>(int)$this->db()->get_var("SELECT COUNT(*) FROM {$q} WHERE status='queued'"),'running'=>(int)$this->db()->get_var("SELECT COUNT(*) FROM {$q} WHERE status='running'"),'failed'=>(int)$this->db()->get_var("SELECT COUNT(*) FROM {$q} WHERE status='failed'"),'stale'=>(int)$this->db()->get_var("SELECT COUNT(*) FROM {$q} WHERE status='running' AND updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)")); }
    public function analytics($days=30){
        $days=max(1,min(365,absint($days))); $since=gmdate('Y-m-d H:i:s',time()-($days*DAY_IN_SECONDS));
        $logs=$this->table('logs'); $queue=$this->table('queue'); $rules=$this->table('rules');
        $runs=(int)$this->db()->get_var($this->db()->prepare("SELECT COUNT(*) FROM {$logs} WHERE created_at >= %s",$since));
        $success=(int)$this->db()->get_var($this->db()->prepare("SELECT COUNT(*) FROM {$logs} WHERE created_at >= %s AND status='success'",$since));
        $failed=(int)$this->db()->get_var($this->db()->prepare("SELECT COUNT(*) FROM {$logs} WHERE created_at >= %s AND status='failed'",$since));
        $dry=(int)$this->db()->get_var($this->db()->prepare("SELECT COUNT(*) FROM {$logs} WHERE created_at >= %s AND status='dry_run'",$since));
        $queued=(int)$this->db()->get_var("SELECT COUNT(*) FROM {$queue} WHERE status='queued'");
        $failed_queue=(int)$this->db()->get_var("SELECT COUNT(*) FROM {$queue} WHERE status='failed'");
        $active_rules=(int)$this->db()->get_var("SELECT COUNT(*) FROM {$rules} WHERE status='active'");
        $events=$this->db()->get_results($this->db()->prepare("SELECT event_key, COUNT(*) runs, SUM(status='success') success, SUM(status='failed') failed FROM {$logs} WHERE created_at >= %s GROUP BY event_key ORDER BY runs DESC LIMIT 20",$since));
        $rule_stats=$this->db()->get_results($this->db()->prepare("SELECT rule_id, COUNT(*) runs, SUM(status='success') success, SUM(status='failed') failed, MAX(created_at) last_run FROM {$logs} WHERE created_at >= %s GROUP BY rule_id ORDER BY runs DESC LIMIT 20",$since));
        $old_failed=(int)$this->db()->get_var("SELECT COUNT(*) FROM {$queue} WHERE status='failed' AND updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)");
        $stale=(int)$this->db()->get_var("SELECT COUNT(*) FROM {$queue} WHERE status='running' AND updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)");
        return compact('days','runs','success','failed','dry','queued','failed_queue','active_rules','events','rule_stats','old_failed','stale');
    }
    public function event_analytics($days=30){
        $days=max(1,min(365,absint($days))); $since=gmdate('Y-m-d H:i:s',time()-($days*DAY_IN_SECONDS)); $logs=$this->table('logs');
        $has_duration=!(function_exists('gd_client_portal_platform_column_exists')) || gd_client_portal_platform_column_exists($logs,'duration_ms');
        $duration=$has_duration?'AVG(duration_ms) avg_ms':'0 avg_ms';
        $rows=$this->db()->get_results($this->db()->prepare("SELECT event_key, COUNT(*) runs, SUM(status='success') success, SUM(status='failed') failed, {$duration}, MAX(created_at) last_run FROM {$logs} WHERE created_at >= %s GROUP BY event_key ORDER BY runs DESC LIMIT 50",$since));
        $daily=$this->db()->get_results($this->db()->prepare("SELECT DATE(created_at) day, COUNT(*) runs, SUM(status='success') success, SUM(status='failed') failed FROM {$logs} WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY day ASC",$since));
        $top_failures=$this->db()->get_results($this->db()->prepare("SELECT event_key, COUNT(*) failures FROM {$logs} WHERE created_at >= %s AND status='failed' GROUP BY event_key ORDER BY failures DESC LIMIT 10",$since));
        $total=(int)$this->db()->get_var($this->db()->prepare("SELECT COUNT(*) FROM {$logs} WHERE created_at >= %s",$since));
        $success=(int)$this->db()->get_var($this->db()->prepare("SELECT COUNT(*) FROM {$logs} WHERE created_at >= %s AND status='success'",$since));
        $failed=(int)$this->db()->get_var($this->db()->prepare("SELECT COUNT(*) FROM {$logs} WHERE created_at >= %s AND status='failed'",$since));
        $avg_ms=$has_duration?(float)$this->db()->get_var($this->db()->prepare("SELECT AVG(duration_ms) FROM {$logs} WHERE created_at >= %s AND duration_ms IS NOT NULL",$since)):0;
        return array('days'=>$days,'rows'=>$rows,'daily'=>$daily,'top_failures'=>$top_failures,'total'=>$total,'success'=>$success,'failed'=>$failed,'avg_ms'=>$avg_ms);
    }

}
