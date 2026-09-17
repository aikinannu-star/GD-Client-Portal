<?php
/** Canonical Project Message application boundary. */
if (!defined('ABSPATH')) exit;

final class GDCP_Message_Service {
    private function repo(){return gdcp_message_repository();}
    public function find($id){return $this->repo()->find($id);}
    public function list_for_project($project_id,$limit=100,$order='DESC'){return $this->repo()->list_for_project($project_id,$limit,$order);}
    public function list_for_tenant($tenant_id=0,$limit=100){return $this->repo()->list_for_tenant($tenant_id,$limit);}
    public function list_for_user($user_id,$tenant_id=0,$tenant_admin=false,$limit=100){return $this->repo()->list_for_user($user_id,$tenant_id,$tenant_admin,$limit);}
    public function count_for_tenant($tenant_id){return $this->repo()->count_for_tenant($tenant_id);}
    public function create(array $data){
        $project_id=absint($data['project_id']??0);$user_id=absint($data['user_id']??0);$message=sanitize_textarea_field($data['message']??'');
        if(!$project_id||!$user_id||$message==='')return new WP_Error('invalid_message','Project and message are required.');
        $event_key=sanitize_key($data['event_key']??'');
        if($event_key){$existing=$this->repo()->find_by_event_key($project_id,$event_key);if($existing)return $existing;}
        $row=array('project_id'=>$project_id,'user_id'=>$user_id,'message'=>$message,'file_url'=>esc_url_raw($data['file_url']??''));
        if($event_key)$row['event_key']=$event_key;
        $id=$this->repo()->insert($row);
        if(!$id && $event_key){$existing=$this->repo()->find_by_event_key($project_id,$event_key);if($existing)return $existing;}
        return $id?:new WP_Error('message_insert_failed','Unable to save the message.');
    }
}
function gdcp_message_service(){static $s;if(!$s)$s=new GDCP_Message_Service();return $s;}
