<?php
if (!defined('ABSPATH')) exit;

final class GDCP_Notification_Service {
    public function create($user_id,$title,$body='',$type='general',$project_id=0,$tenant_id=0,$event_key=''){
        $user_id=absint($user_id); if(!$user_id)return false;
        $user_tenant=absint(get_user_meta($user_id,'gd_client_portal_tenant_id',true));
        $tenant_id=$tenant_id?absint($tenant_id):$user_tenant;
        if($user_tenant && $tenant_id && $user_tenant!==$tenant_id)return false;
        $event_key=$event_key ? sanitize_key($event_key) : null;
        if($event_key){
            $existing=gdcp_notifications_repository()->find_by_event_key($user_id,$event_key);
            if($existing) return absint($existing->id);
        }
        $data=array('user_id'=>$user_id,'tenant_id'=>$tenant_id,'project_id'=>absint($project_id),'type'=>sanitize_key($type),'title'=>sanitize_text_field($title),'body'=>sanitize_textarea_field($body),'is_read'=>0,'created_at'=>current_time('mysql'),'event_key'=>$event_key);
        $id=gdcp_notifications_repository()->insert($data);
        if($id) return $id;
        // A concurrent request may have won the unique event-key insert race.
        if($event_key){ $existing=gdcp_notifications_repository()->find_by_event_key($user_id,$event_key); if($existing) return absint($existing->id); }
        return false;
    }
    public function for_user($user_id=0,$limit=50){ return gdcp_notifications_repository()->list_for_user($user_id,$limit); }
    public function unread_count($user_id=0){ return gdcp_notifications_repository()->unread_count($user_id); }
    public function mark_read($id,$user_id=0){ return gdcp_notifications_repository()->mark_read($id,$user_id); }
    public function mark_all_read($user_id=0){ return gdcp_notifications_repository()->mark_all_read($user_id); }
}
function gdcp_notification_service(){ static $service; if(!$service)$service=new GDCP_Notification_Service(); return $service; }
