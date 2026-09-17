<?php
if(!defined('ABSPATH')) exit;
class GDCP_Feedback_Service {
    private $repo;
    public function __construct($repo=null){$this->repo=$repo?:new GDCP_Feedback_Repository();}
    public function get_review_for_access($id){return $this->repo->find_review($id);}
    public function latest_for_project($project_id){return $this->repo->find_latest_for_project($project_id);}
    public function existing($project_id,$ticket_id=0,$user_id=0){return $this->repo->find_existing($project_id,$ticket_id,$user_id);}
    public function submit(array $input){
        $tenant=absint($input['tenant_id']??0);$user_id=absint($input['user_id']??0);$project_id=absint($input['project_id']??0);$ticket_id=absint($input['ticket_id']??0);$rating=max(1,min(5,absint($input['rating']??0)));$comment=sanitize_textarea_field($input['comment']??'');
        if($tenant<=0&&!gd_client_portal_is_platform_admin())return new WP_Error('no_tenant','No tenant assigned.');if(!$user_id)$user_id=gd_client_portal_cached_current_user_id();if(!$rating||!$comment)return new WP_Error('invalid_feedback','Rating and feedback are required.');if($this->repo->find_existing($project_id,$ticket_id,$user_id))return new WP_Error('duplicate_feedback','Feedback has already been submitted for this item.');
        $now=current_time('mysql');return $this->repo->insert_review(array('tenant_id'=>$tenant,'user_id'=>$user_id,'project_id'=>$project_id,'ticket_id'=>$ticket_id,'rating'=>$rating,'service_rating'=>max(0,min(5,absint($input['service_rating']??$rating))),'communication_rating'=>max(0,min(5,absint($input['communication_rating']??$rating))),'timeliness_rating'=>max(0,min(5,absint($input['timeliness_rating']??$rating))),'comment'=>$comment,'recommend'=>!empty($input['recommend'])?1:0,'status'=>'published','created_at'=>$now,'updated_at'=>$now));
    }
    public function respond($review_id,$tenant_id,$author_id,$body,$internal=false){$review=$this->repo->find_review($review_id);if(!$review||absint($review->tenant_id)!==absint($tenant_id))return new WP_Error('feedback_access','Feedback access denied.');$body=sanitize_textarea_field($body);if(!$body)return new WP_Error('empty_response','Response is required.');return $this->repo->insert_response(array('review_id'=>absint($review_id),'tenant_id'=>absint($tenant_id),'author_id'=>absint($author_id),'body'=>$body,'is_internal'=>$internal?1:0,'created_at'=>current_time('mysql')));}
    public function list_for_current_user($limit=100){if(gd_client_portal_is_platform_admin())return $this->repo->list_reviews($limit);$tenant=absint(gd_client_portal_get_current_tenant_id());if($tenant<=0)return array();if(!gd_client_portal_user_is_tenant_admin())return $this->repo->list_reviews($limit,$tenant,gd_client_portal_cached_current_user_id());return $this->repo->list_reviews($limit,$tenant);}
    public function metrics_for_current_user(){if(gd_client_portal_is_platform_admin())$row=$this->repo->metrics();else{$tenant=absint(gd_client_portal_get_current_tenant_id());if($tenant<=0)return array('count'=>0,'avg'=>0,'recommend'=>0);$row=$this->repo->metrics($tenant);}return array('count'=>intval($row->count),'avg'=>round(floatval($row->avg),2),'recommend'=>round(floatval($row->recommend),1));}
}
function gdcp_feedback_service(){static $service;if(!$service)$service=new GDCP_Feedback_Service();return $service;}
