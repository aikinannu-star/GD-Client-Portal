<?php
if (!defined('ABSPATH')) exit;

final class GDCP_Contract_Service {
    private function repo(){ return gdcp_contract_repository(); }
    public function list_visible($limit=100){ return $this->repo()->list_visible($limit); }
    public function find($id,$lock=false){ return $this->repo()->find($id,$lock); }
    public function latest_for_project($project_id){ return $this->repo()->latest_for_project($project_id); }
    public function create_from_quote($quote_id){
        global $wpdb;
        if(!function_exists('gdcp_billing_service')) return 0;
        $wpdb->query('START TRANSACTION');
        try{
            $q=gdcp_billing_service()->get_quote_for_access(absint($quote_id),true);
            if(!$q||$q->status!=='accepted'){ $wpdb->query('ROLLBACK'); return 0; }
            $existing=$this->repo()->latest_for_quote($q->id,true); if($existing){$wpdb->query('COMMIT');return absint($existing->id);}
            $now=current_time('mysql'); $body='Agreement for '.sanitize_text_field($q->title)."\n\n".wp_strip_all_tags((string)$q->description)."\n\nCommercial amount: ".number_format((float)$q->amount,2).' '.strtoupper($q->currency).'.';
            $id=$this->repo()->insert(array('contract_number'=>gd_client_portal_contracts_number(),'tenant_id'=>absint($q->tenant_id),'user_id'=>absint($q->user_id),'project_id'=>absint($q->project_id),'quote_id'=>absint($q->id),'parent_id'=>0,'version'=>1,'title'=>sanitize_text_field($q->title),'body'=>$body,'status'=>'draft','created_by'=>gd_client_portal_cached_current_user_id(),'created_at'=>$now,'updated_at'=>$now));
            if(!$id){$wpdb->query('ROLLBACK');return 0;} $wpdb->query('COMMIT'); return $id;
        }catch(Throwable $e){$wpdb->query('ROLLBACK');return 0;}
    }
    public function create_sent(array $data){
        $now=current_time('mysql');
        $data=array_merge(array('contract_number'=>gd_client_portal_contracts_number(),'parent_id'=>0,'version'=>1,'status'=>'sent','created_at'=>$now,'updated_at'=>$now,'submitted_at'=>$now),$data);
        return $this->repo()->insert($data);
    }
    public function send($id){
        global $wpdb; $wpdb->query('START TRANSACTION');
        try{$row=$this->repo()->find($id,true); if(!$row||$row->status!=='draft'){$wpdb->query('ROLLBACK');return false;} $now=current_time('mysql'); $ok=$this->repo()->update($id,array('status'=>'sent','submitted_at'=>$now,'updated_at'=>$now)); if(!$ok){$wpdb->query('ROLLBACK');return false;} $wpdb->query('COMMIT'); return true;}catch(Throwable $e){$wpdb->query('ROLLBACK');return false;}
    }
    public function decide($id,$decision,$typed_name,$agree){
        global $wpdb; $decision=sanitize_key($decision); $typed_name=sanitize_text_field($typed_name);
        if(!in_array($decision,array('accepted','declined'),true))return new WP_Error('invalid_decision','Invalid decision.');
        $wpdb->query('START TRANSACTION');
        try{
            $row=$this->repo()->find($id,true); if(!$row){$wpdb->query('ROLLBACK');return new WP_Error('not_found','Agreement not found.');}
            if($row->status!=='sent'){$wpdb->query('ROLLBACK');return new WP_Error('not_pending','This agreement is not awaiting a decision.');}
            if($row->valid_until && strtotime($row->valid_until.' 23:59:59')<current_time('timestamp')){$this->repo()->update($id,array('status'=>'expired','updated_at'=>current_time('mysql')));$wpdb->query('COMMIT');return new WP_Error('expired','This agreement has expired.');}
            if($decision==='accepted'&&(!$agree||!$typed_name)){$wpdb->query('ROLLBACK');return new WP_Error('confirmation_required','Please confirm the agreement and enter your name.');}
            $now=current_time('mysql'); $this->repo()->update($id,array('status'=>$decision,'updated_at'=>$now,'accepted_at'=>$decision==='accepted'?$now:null,'declined_at'=>$decision==='declined'?$now:null));
            $sig=$this->repo()->insert_signature(array('contract_id'=>$id,'tenant_id'=>absint($row->tenant_id),'user_id'=>gd_client_portal_cached_current_user_id(),'decision'=>$decision,'typed_name'=>$typed_name,'ip_hash'=>hash('sha256',wp_unslash($_SERVER['REMOTE_ADDR']??'')),'user_agent'=>substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']??''),0,500),'created_at'=>$now));
            if(!$sig){$wpdb->query('ROLLBACK');return new WP_Error('signature_failed','Unable to record the decision.');}
            $updated=$this->repo()->find($id); $wpdb->query('COMMIT'); return $updated;
        }catch(Throwable $e){$wpdb->query('ROLLBACK');return new WP_Error('decision_failed','Unable to record the decision.');}
    }
}
function gdcp_contract_service(){static $service;if(!$service)$service=new GDCP_Contract_Service();return $service;}
