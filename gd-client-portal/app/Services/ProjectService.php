<?php
if (!defined('ABSPATH')) exit;

final class GDCP_Project_Service extends GDCP_Service_Base {
    public function get($id){ $repo=gdcp_repository('project'); return $repo ? $repo->accessible($id) : null; }
    public function visible($limit=200,$status='',$tenant_id=0){ $repo=gdcp_repository('project'); return $repo ? $repo->visible($limit,$status,$tenant_id) : array(); }
    public function get_many($ids){ $repo=gdcp_repository('project'); return $repo ? $repo->find_many($ids) : array(); }
    public function get_by_order_id($order_id){ $repo=gdcp_repository('project'); return $repo && method_exists($repo,'find_by_order_id') ? $repo->find_by_order_id($order_id) : null; }

    /** Canonical, authorized project stage transition. */
    public function update_stage($project_id,$new_stage,$progress=null){
        return $this->transition_stage($project_id,$new_stage,$progress,false);
    }

    /**
     * Trusted automation transition. The automation engine is the caller, but
     * authorization and workflow validation still live in the project service.
     */
    public function advance_stage_for_automation($project_id,$new_stage='',$progress=null){
        return $this->transition_stage($project_id,$new_stage,$progress,true);
    }

    private function transition_stage($project_id,$new_stage,$progress=null,$automation=false){
        $project=$this->get($project_id);
        $authorized=gdcp_can('edit','project',$project_id);
        if($automation && !$authorized){
            $authorized=defined('DOING_CRON') && DOING_CRON;
        }
        if(!$project || !$authorized) return false;
        $stage=sanitize_key($new_stage); if($stage==='') return false;
        if(function_exists('gd_client_portal_get_workflow')){
            $workflow=gd_client_portal_get_workflow($project->service_type);
            if(!in_array($stage,$workflow,true)) return false;
        }
        if($progress===null && function_exists('gd_client_portal_auto_progress')) $progress=gd_client_portal_auto_progress($project->service_type,$stage);
        $data=array('current_stage'=>$stage);
        if($progress!==null) $data['progress']=max(0,min(100,absint($progress)));
        $repo=gdcp_repository('project'); if(!$repo) return false;
        $ok=$repo->transition_fields($project_id,$project->current_stage,$data);
        if($ok===1 && $project->current_stage!==$stage){
            if(function_exists('do_action')) do_action('gd_client_portal_project_stage_changed',absint($project_id),$stage,$data['progress']??null);
            $this->emit('project_stage_changed',$project_id,array('old_stage'=>$project->current_stage,'new_stage'=>$stage,'progress'=>$data['progress']??null));
        }
        return $ok;
    }

    /** Atomic/idempotent field update for authorized project operations. */
    public function update($project_id,$data){
        $project=$this->get($project_id); if(!$project || !gdcp_can('edit','project',$project_id)) return false;
        $allowed=array('title','description','status','current_stage','progress','file_url','tenant_id','user_id','product_id','service_type');
        $platform_admin=function_exists('gd_client_portal_is_platform_admin') && gd_client_portal_is_platform_admin();
        if(!$platform_admin){ unset($data['tenant_id'],$data['user_id']); }
        $repo=gdcp_repository('project'); if(!$repo) return false;
        return $repo->update_fields($project_id,$data);
    }

    /** Trusted WooCommerce purchase-to-project creation boundary. */
    public function create_from_woocommerce($data){
        if(!is_array($data)) return 0;
        $order_id=absint($data['order_id']??0); $product_id=absint($data['product_id']??0); $tenant_id=absint($data['tenant_id']??0);
        if(!$order_id||!$product_id||$tenant_id<=0) return 0;
        if(function_exists('gd_client_portal_is_woocommerce_active') && !gd_client_portal_is_woocommerce_active()) return 0;
        $repo=gdcp_repository('project'); if(!$repo || !method_exists($repo,'create')) return 0;
        return $repo->create(array(
            'tenant_id'=>$tenant_id,'order_id'=>$order_id,'user_id'=>absint($data['user_id']??0),'product_id'=>$product_id,
            'service_type'=>sanitize_text_field($data['service_type']??''),'title'=>sanitize_text_field($data['title']??''),
            'description'=>sanitize_textarea_field($data['description']??''),'status'=>sanitize_text_field($data['status']??'awaiting_requirements'),
            'current_stage'=>sanitize_text_field($data['current_stage']??'intake'),'progress'=>max(0,min(100,absint($data['progress']??0))),
            'created_at'=>current_time('mysql'),
        ));
    }

    /** Canonical requirements/progression mutation for the intake/requirements flow. */
    public function submit_requirements($project_id,$title,$description,$file_url=''){
        $project=$this->get($project_id);
        if(!$project || !gdcp_can('edit','project',$project_id)) return false;
        $workflow=function_exists('gd_client_portal_get_workflow') ? gd_client_portal_get_workflow($project->service_type) : array();
        $current_index=array_search($project->current_stage,$workflow,true);
        $next_stage=($current_index!==false && isset($workflow[$current_index+1])) ? $workflow[$current_index+1] : ($workflow[1]??$project->current_stage);
        $progress=function_exists('gd_client_portal_auto_progress') ? gd_client_portal_auto_progress($project->service_type,$next_stage) : 0;
        if($progress<=0 && $next_stage!==$project->current_stage) $progress=10;
        $data=array('title'=>sanitize_text_field($title),'description'=>sanitize_textarea_field($description),'status'=>'processing','current_stage'=>sanitize_key($next_stage),'progress'=>absint($progress),'file_url'=>esc_url_raw($file_url));
        $repo=gdcp_repository('project'); if(!$repo) return false;
        $ok=$repo->transition_fields($project_id,$project->current_stage,$data);
        if($ok===1 && $project->current_stage!==$next_stage){
            do_action('gd_client_portal_project_stage_changed',absint($project_id),$next_stage,$progress);
            $this->emit('project_stage_changed',$project_id,array('old_stage'=>$project->current_stage,'new_stage'=>$next_stage,'progress'=>$progress));
        }
        return $ok;
    }

    public function emit($event,$project_id,$extra=array()){
        $project=$this->get($project_id); if(!$project) return false;
        $payload=array_merge(array('project_id'=>absint($project_id),'tenant_id'=>absint($project->tenant_id ?? 0),'project'=>$project),$extra);
        return function_exists('gdcp_event') ? gdcp_event($event,$payload) : false;
    }
}
