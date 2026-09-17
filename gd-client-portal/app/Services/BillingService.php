<?php
if (!defined('ABSPATH')) exit;
final class GDCP_Billing_Service {
    public function invoice($id){ $repo=gdcp_repository('billing'); return $repo ? $repo->invoice($id) : null; }
    public function invoices($limit=100){ $repo=gdcp_repository('billing'); return $repo ? $repo->invoices($limit) : array(); }
    public function can_view_invoice($id){ return gdcp_can('view','invoice',$id); }
    public function create_invoice($data){
        if(!gdcp_can('create','invoice',0,$data)) return false;
        $repo=gdcp_repository('billing'); if(!$repo || !method_exists($repo,'insert')) return false;
        $tenant=absint($data['tenant_id']??0);
        if(function_exists('gd_client_portal_is_platform_admin') && !gd_client_portal_is_platform_admin()) $tenant=absint(function_exists('gd_client_portal_get_current_tenant_id')?gd_client_portal_get_current_tenant_id():0);
        if($tenant<=0) return false;
        $row=array(
            'invoice_number'=>sanitize_text_field($data['invoice_number']??('INV-'.gmdate('YmdHis').'-'.wp_rand(100,999))),
            'tenant_id'=>$tenant,'user_id'=>absint($data['user_id']??get_current_user_id()),'project_id'=>absint($data['project_id']??0),'order_id'=>absint($data['order_id']??0),'quote_id'=>absint($data['quote_id']??0),
            'currency'=>sanitize_text_field($data['currency']??'GHS'),'subtotal'=>(float)($data['subtotal']??0),'tax'=>(float)($data['tax']??0),'total'=>(float)($data['total']??0),
            'amount_paid'=>0,'due_date'=>!empty($data['due_date'])?sanitize_text_field($data['due_date']):null,'status'=>sanitize_key($data['status']??'unpaid'),'payment_reference'=>sanitize_text_field($data['payment_reference']??''),'description'=>sanitize_textarea_field($data['description']??''),
            'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')
        );
        return $repo->insert($row);
    }
}
