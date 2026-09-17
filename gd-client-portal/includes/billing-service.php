<?php
if (!defined('ABSPATH')) exit;

final class GDCP_Billing_Service {
    private $repo;
    public function __construct(){ $this->repo=new GDCP_Billing_Repository(); }
    public function repo_latest_quote_for_project($project_id){return $this->repo->find_latest_quote_for_project($project_id);}
    public function repo_latest_invoice_for_project($project_id){return $this->repo->find_latest_invoice_for_project($project_id);}
    public function get_quote_for_access($id,$lock=false){ return $this->repo->find_quote($id,$lock); }
    public function create_quote(array $input){
        $tenant=absint($input['tenant_id']??0); $user=absint($input['user_id']??0); $project=absint($input['project_id']??0);
        if($tenant<=0||$user<=0||empty($input['title'])) return new WP_Error('invalid_quote','Client, tenant and title are required.');
        if($project){ $p=gd_client_portal_get_project_by_id($project); if(!$p||absint($p->tenant_id)!==$tenant||absint($p->user_id)!==$user) return new WP_Error('invalid_project','Project does not match client/tenant.'); }
        $data=array('quote_number'=>gd_client_portal_billing_quote_number(),'tenant_id'=>$tenant,'user_id'=>$user,'project_id'=>$project,'title'=>sanitize_text_field($input['title']),'description'=>sanitize_textarea_field($input['description']??''),'currency'=>strtoupper(sanitize_text_field($input['currency']??'GHS')),'amount'=>(float)$input['amount'],'status'=>'sent','valid_until'=>preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($input['valid_until']??''))?$input['valid_until']:null,'created_by'=>gd_client_portal_cached_current_user_id(),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'));
        if($data['amount']<0) return new WP_Error('invalid_amount','Amount cannot be negative.');
        $id=$this->repo->insert_quote($data); return $id?array('id'=>$id,'record'=>$this->repo->find_quote($id)):new WP_Error('quote_create_failed','Could not create quote.');
    }
    public function decide_quote($id,$status){
        $q=$this->repo->find_quote($id,true); if(!$q) return new WP_Error('not_found','Quote not found.');
        if(!in_array($status,array('accepted','declined'),true)) return new WP_Error('invalid_status','Invalid quote decision.');
        if($q->status!=='sent') return new WP_Error('invalid_state','Only sent quotes can be decided.');
        if(!$this->repo->update_quote($q->id,array('status'=>$status,'updated_at'=>current_time('mysql')))) return new WP_Error('update_failed','Could not update quote.');
        return $this->repo->find_quote($q->id);
    }
    public function sync_woocommerce_order($order_id){
        if(!function_exists('wc_get_order')) return 0;
        $order=wc_get_order(absint($order_id)); if(!$order) return 0;
        if(!in_array($order->get_status(),array('processing','completed','on-hold','pending','failed','cancelled','refunded'),true)) return 0;
        $existing=$this->billing_order_invoice(absint($order_id));
        $user_id=absint($order->get_user_id()); $tenant_id=absint($order->get_meta('_gd_mp_order_tenant_id'));
        if(!$tenant_id && $user_id && function_exists('gd_client_portal_get_user_tenant_id')) $tenant_id=absint(gd_client_portal_get_user_tenant_id($user_id));
        if(!$tenant_id && function_exists('gd_client_portal_get_default_tenant_id')) $tenant_id=absint(gd_client_portal_get_default_tenant_id());
        $project_id=0;
        if(function_exists('gd_client_portal_get_project_by_order_id')) $project_id=absint(gd_client_portal_get_project_by_order_id($order_id));
        $total=(float)$order->get_total(); $paid=in_array($order->get_status(),array('processing','completed'),true)?$total:0; $status=$paid>=$total&&$total>0?'paid':'unpaid';
        $now=current_time('mysql'); $due_date=$existing && !empty($existing->due_date)?$existing->due_date:gmdate('Y-m-d',current_time('timestamp')+7*DAY_IN_SECONDS);
        $data=array('tenant_id'=>$tenant_id,'user_id'=>$user_id,'project_id'=>$project_id,'order_id'=>absint($order_id),'currency'=>$order->get_currency()?:'GHS','subtotal'=>(float)$order->get_subtotal(),'tax'=>(float)$order->get_total_tax(),'total'=>$total,'amount_paid'=>$paid,'due_date'=>$due_date,'status'=>$status,'payment_reference'=>sanitize_text_field($order->get_transaction_id()),'description'=>sprintf(__('WooCommerce order #%d','gd-client-portal'),$order_id),'updated_at'=>$now);
        if($existing){ $this->billing_repo()->update_invoice($existing->id,$data); return absint($existing->id); }
        $data['invoice_number']=gd_client_portal_billing_invoice_number(); $data=array('invoice_number'=>$data['invoice_number'])+$data; $data['created_at']=$now; if($status==='paid')$data['paid_at']=$now;
        return $this->billing_repo()->insert_invoice($data);
    }
    private function billing_repo(){ return $this->repo; }
    private function billing_order_invoice($order_id){ return $this->repo->find_invoice_by_order($order_id); }
    public function convert_accepted_quote_to_invoice($quote_id,$due_date=''){
        global $wpdb; $wpdb->query('START TRANSACTION');
        $q=$this->repo->find_quote($quote_id,true); if(!$q){$wpdb->query('ROLLBACK');return new WP_Error('not_found','Quote not found.');}
        if($q->status!=='accepted'){$wpdb->query('ROLLBACK');return new WP_Error('invalid_state','Only accepted quotes can be converted.');}
        if($q->project_id){$p=gd_client_portal_get_project_by_id($q->project_id);if(!$p||absint($p->tenant_id)!==absint($q->tenant_id)||absint($p->user_id)!==absint($q->user_id)){$wpdb->query('ROLLBACK');return new WP_Error('invalid_project','Quote project does not match its tenant/client.');}}
        $existing=$this->repo->find_invoice_by_quote($q->id,true); if($existing){$wpdb->query('COMMIT');return array('invoice'=>$existing,'created'=>false);}
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$due_date))$due_date=gmdate('Y-m-d',current_time('timestamp')+7*DAY_IN_SECONDS);
        $id=$this->repo->insert_invoice(array('invoice_number'=>gd_client_portal_billing_invoice_number(),'tenant_id'=>$q->tenant_id,'user_id'=>$q->user_id,'project_id'=>$q->project_id,'quote_id'=>$q->id,'currency'=>$q->currency,'subtotal'=>$q->amount,'tax'=>0,'total'=>$q->amount,'amount_paid'=>0,'due_date'=>$due_date,'status'=>'unpaid','description'=>$q->title.($q->description?' — '.$q->description:''),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')));
        if(!$id){$wpdb->query('ROLLBACK');return new WP_Error('invoice_create_failed','Could not create invoice.');}
        $invoice=$this->repo->find_invoice($id); $wpdb->query('COMMIT'); return array('invoice'=>$invoice,'created'=>true);
    }
}
function gdcp_billing_service(){ static $service; if(!$service)$service=new GDCP_Billing_Service(); return $service; }
