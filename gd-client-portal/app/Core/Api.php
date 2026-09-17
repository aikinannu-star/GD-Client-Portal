<?php
if (!defined('ABSPATH')) exit;
/** v7.5 REST API boundary. Legacy AJAX endpoints remain supported. */
final class GDCP_Api {
    const NS = 'gdcp/v1';
    public static function register(){
        register_rest_route(self::NS,'/projects',array(
            'methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'projects'),'permission_callback'=>array(__CLASS__,'authenticated'),
            'args'=>array('status'=>array('sanitize_callback'=>'sanitize_key'),'limit'=>array('default'=>50,'sanitize_callback'=>'absint'))
        ));
        register_rest_route(self::NS,'/projects/(?P<id>\d+)',array(
            'methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'project'),'permission_callback'=>array(__CLASS__,'project_permission')
        ));
        register_rest_route(self::NS,'/projects/(?P<id>\d+)/stage',array(
            'methods'=>WP_REST_Server::CREATABLE,'callback'=>array(__CLASS__,'stage'),'permission_callback'=>array(__CLASS__,'project_edit_permission'),
            'args'=>array('stage'=>array('required'=>true,'sanitize_callback'=>'sanitize_key'),'progress'=>array('required'=>false,'sanitize_callback'=>'absint'))
        ));
        register_rest_route(self::NS,'/invoices',array(
            'methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'invoices'),'permission_callback'=>array(__CLASS__,'authenticated')
        ));
        register_rest_route(self::NS,'/invoices/(?P<id>\d+)',array(
            'methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'invoice'),'permission_callback'=>array(__CLASS__,'invoice_permission')
        ));
        register_rest_route(self::NS,'/documents/(?P<id>\d+)',array(
            'methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'document'),'permission_callback'=>array(__CLASS__,'document_permission')
        ));
        register_rest_route(self::NS,'/support/tickets/(?P<id>\d+)',array(
            'methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'ticket'),'permission_callback'=>array(__CLASS__,'support_permission')
        ));
    }
    public static function authenticated(){ return is_user_logged_in() && current_user_can('read'); }
    public static function project_permission($request){ return self::authenticated() && gdcp_can('view','project',(int)$request['id']); }
    public static function project_edit_permission($request){ return self::authenticated() && gdcp_can('edit','project',(int)$request['id']); }
    public static function invoice_permission($request){ return self::authenticated() && gdcp_can('view','invoice',(int)$request['id']); }
    public static function document_permission($request){ return self::authenticated() && gdcp_can('view','document',(int)$request['id']); }
    public static function support_permission($request){ return self::authenticated() && gdcp_can('view','support',(int)$request['id']); }
    private static function response($data){ return rest_ensure_response($data); }
    private static function project_data($p){ return array('id'=>absint($p->id),'tenant_id'=>absint($p->tenant_id),'user_id'=>absint($p->user_id),'title'=>sanitize_text_field($p->title ?? ''),'status'=>sanitize_key($p->status ?? ''),'stage'=>sanitize_key($p->current_stage ?? ''),'progress'=>absint($p->progress ?? 0),'service_type'=>sanitize_key($p->service_type ?? ''),'file_url'=>esc_url_raw($p->file_url ?? '')); }
    private static function row_data($r,$fields){ $o=array(); foreach($fields as $f){ $v=is_object($r)&&isset($r->$f)?$r->$f:(is_array($r)&&isset($r[$f])?$r[$f]:null); if(in_array($f,array('id','tenant_id','user_id','project_id','invoice_id','ticket_id'),true)) $v=absint($v); elseif(in_array($f,array('total','subtotal','tax','amount_paid'),true)) $v=(float)$v; elseif(is_string($v)) $v=sanitize_text_field($v); $o[$f]=$v; } return $o; }
    public static function projects($request){ $service=gdcp_service('project'); if(!$service) return new WP_Error('gdcp_service_unavailable','Project service unavailable',array('status'=>503)); $limit=min(200,max(1,(int)$request->get_param('limit'))); $status=sanitize_key($request->get_param('status')); return self::response(array_map(array(__CLASS__,'project_data'),$service->visible($limit,$status))); }
    public static function project($request){ $p=gdcp_service('project')->get((int)$request['id']); if(!$p) return new WP_Error('gdcp_not_found','Project not found',array('status'=>404)); return self::response(self::project_data($p)); }
    public static function stage($request){ $id=(int)$request['id']; $service=gdcp_service('project'); $progress=$request->has_param('progress')?(int)$request->get_param('progress'):null; $ok=$service&&$service->update_stage($id,sanitize_key($request->get_param('stage')),$progress); if(!$ok) return new WP_Error('gdcp_stage_update_failed','Stage update was rejected',array('status'=>400)); return self::project($request); }
    public static function invoices(){ $service=gdcp_service('billing'); if(!$service) return new WP_Error('gdcp_service_unavailable','Billing service unavailable',array('status'=>503)); $out=array(); foreach($service->invoices(100) as $r) $out[]=self::row_data($r,array('id','invoice_number','tenant_id','user_id','project_id','currency','subtotal','tax','total','amount_paid','due_date','status')); return self::response($out); }
    public static function invoice($request){ $r=gdcp_service('billing')->invoice((int)$request['id']); if(!$r) return new WP_Error('gdcp_not_found','Invoice not found',array('status'=>404)); return self::response(self::row_data($r,array('id','invoice_number','tenant_id','user_id','project_id','currency','subtotal','tax','total','amount_paid','due_date','status','description'))); }
    public static function document($request){ $r=gdcp_service('document')->get((int)$request['id']); if(!$r) return new WP_Error('gdcp_not_found','Document not found',array('status'=>404)); return self::response(self::row_data($r,array('id','tenant_id','user_id','project_id','title','file_name','mime_type','filesize','version','status','expires_at'))); }
    public static function ticket($request){ $r=gdcp_service('support')->ticket((int)$request['id']); if(!$r) return new WP_Error('gdcp_not_found','Support ticket not found',array('status'=>404)); return self::response(self::row_data($r,array('id','tenant_id','user_id','project_id','subject','category','priority','status','assigned_to','due_at','created_at','updated_at'))); }
}
add_action('rest_api_init',array('GDCP_Api','register'));
