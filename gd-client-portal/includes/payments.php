<?php
/**
 * GD Client Portal v3.3 — Payment & Invoice Operations.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_payment_table(){ global $wpdb; return $wpdb->prefix.'gd_portal_payments'; }
function gd_client_portal_payment_activate_table(){
    global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php'; $c=$wpdb->get_charset_collate(); $t=gd_client_portal_payment_table();
    dbDelta("CREATE TABLE $t (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,invoice_id bigint(20) unsigned NOT NULL DEFAULT 0,tenant_id bigint(20) unsigned NOT NULL DEFAULT 0,user_id bigint(20) unsigned NOT NULL DEFAULT 0,project_id bigint(20) unsigned NOT NULL DEFAULT 0,amount decimal(20,2) NOT NULL DEFAULT 0.00,currency varchar(10) NOT NULL DEFAULT 'GHS',gateway varchar(40) NOT NULL DEFAULT 'manual',reference varchar(120) NOT NULL,channel varchar(60) NULL,status varchar(30) NOT NULL DEFAULT 'success',gateway_status varchar(60) NULL,metadata longtext NULL,created_at datetime DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY reference(reference),KEY invoice_id(invoice_id),KEY tenant_id(tenant_id),KEY user_id(user_id),KEY project_id(project_id),KEY status(status)) $c;");
}
function gd_client_portal_payment_upgrade(){ if(absint(get_option('gd_client_portal_payment_schema_version',0))<1){ gd_client_portal_payment_activate_table(); update_option('gd_client_portal_payment_schema_version',1); } }
// Schema lifecycle is managed centrally by GDCP_Migration_Manager (v6.8+).

function gd_client_portal_payment_invoice($id){ return gdcp_payment_service()->get_invoice($id); }
function gd_client_portal_payment_access($invoice,$admin=false){
    if(!$invoice || !is_user_logged_in() || !gd_client_portal_verify_request()) return false;
    if(gd_client_portal_is_platform_admin()) return true;
    $tenant=absint(gd_client_portal_get_current_tenant_id()); if(!$tenant || absint($invoice->tenant_id)!==$tenant) return false;
    return $admin ? gd_client_portal_user_is_tenant_admin() : (gd_client_portal_user_is_tenant_admin() || absint($invoice->user_id)===gd_client_portal_cached_current_user_id());
}
function gd_client_portal_payment_balance($invoice){ return max(0,(float)$invoice->total-(float)$invoice->amount_paid); }
function gd_client_portal_payment_status_from_amount($invoice,$paid){ $total=(float)$invoice->total; if($total<=0)return 'paid'; if($paid>=$total-0.0001)return 'paid'; if($paid>0)return 'part_paid'; return 'unpaid'; }
function gd_client_portal_payment_record($invoice_id,$amount,$reference,$gateway='manual',$channel='',$gateway_status='success',$metadata=array()){
    $result=gdcp_payment_service()->record($invoice_id,$amount,$reference,$gateway,$channel,$gateway_status,$metadata);
    if(is_wp_error($result)) return 0;
    return absint($result['id']);
}
function gd_client_portal_payment_get_for_invoice($invoice_id){ return gdcp_payment_service()->get_for_invoice($invoice_id); }

function gd_client_portal_payment_init(){
    if(empty($_GET['gdcp_paystack_callback'])) return;
    $ref=sanitize_text_field(wp_unslash($_GET['reference']??'')); $invoice_id=absint($_GET['invoice_id']??0); if($ref && $invoice_id) gd_client_portal_payment_verify_paystack($ref,$invoice_id,true);
}
add_action('template_redirect','gd_client_portal_payment_init',1);

function gd_client_portal_paystack_secret(){ return trim((string)get_option('gd_client_portal_paystack_secret_key','')); }
function gd_client_portal_paystack_enabled(){ return get_option('gd_client_portal_paystack_enabled','0')==='1' && gd_client_portal_paystack_secret()!==''; }
function gd_client_portal_paystack_initialize($invoice){
    if(!gd_client_portal_paystack_enabled())return new WP_Error('disabled','Online payments are not enabled.');
    $user=gd_client_portal_cached_user($invoice->user_id); if(!$user)return new WP_Error('user','Invoice customer not found.'); $amount=gd_client_portal_payment_balance($invoice); if($amount<=0)return new WP_Error('paid','Invoice is already paid.');
    $callback=add_query_arg(array('gdcp_paystack_callback'=>1,'invoice_id'=>absint($invoice->id)),home_url('/'));
    $body=array('email'=>$user->user_email,'amount'=>(string)round($amount*100),'currency'=>strtoupper($invoice->currency),'reference'=>'GDCP-'.$invoice->id.'-'.wp_generate_password(14,false,false),'callback_url'=>$callback,'metadata'=>array('invoice_id'=>absint($invoice->id),'project_id'=>absint($invoice->project_id),'tenant_id'=>absint($invoice->tenant_id),'user_id'=>absint($invoice->user_id)));
    return wp_remote_post('https://api.paystack.co/transaction/initialize',array('timeout'=>20,'headers'=>array('Authorization'=>'Bearer '.gd_client_portal_paystack_secret(),'Content-Type'=>'application/json'),'body'=>wp_json_encode($body)));
}
function gd_client_portal_paystack_verify($reference){
    if(!gd_client_portal_paystack_enabled())return new WP_Error('disabled','Online payments are not enabled.');
    return wp_remote_get('https://api.paystack.co/transaction/verify/'.rawurlencode($reference),array('timeout'=>20,'headers'=>array('Authorization'=>'Bearer '.gd_client_portal_paystack_secret())));
}
function gd_client_portal_payment_verify_paystack($reference,$invoice_id=0,$redirect=false){
    $response=gd_client_portal_paystack_verify($reference); if(is_wp_error($response))return $response; $code=wp_remote_retrieve_response_code($response); $body=json_decode(wp_remote_retrieve_body($response),true); if($code!==200 || empty($body['status']) || empty($body['data']))return new WP_Error('verify','Payment verification failed.');
    $data=$body['data']; $meta=$data['metadata']??array(); $resolved=absint($invoice_id?:($meta['invoice_id']??0)); $invoice=gd_client_portal_payment_invoice($resolved); if(!$invoice)return new WP_Error('invoice','Invoice not found.'); if(!empty($data['reference']) && (string)$data['reference']!==(string)$reference)return new WP_Error('reference_mismatch','Verified payment reference does not match the requested reference.'); if(!empty($data['currency']) && strtoupper((string)$data['currency'])!==strtoupper((string)$invoice->currency))return new WP_Error('currency_mismatch','Payment currency does not match the invoice.');
    if($invoice_id && !empty($meta['invoice_id']) && absint($meta['invoice_id'])!==absint($invoice_id))return new WP_Error('invoice_mismatch','Payment metadata does not match the requested invoice.');
    if(!empty($meta['tenant_id']) && absint($meta['tenant_id'])!==absint($invoice->tenant_id))return new WP_Error('tenant_mismatch','Payment tenant does not match the invoice.');
    if(!empty($meta['project_id']) && absint($meta['project_id'])!==absint($invoice->project_id))return new WP_Error('project_mismatch','Payment project does not match the invoice.');
    if(!empty($meta['user_id']) && absint($meta['user_id'])!==absint($invoice->user_id))return new WP_Error('user_mismatch','Payment customer does not match the invoice.');
    if(absint($invoice->user_id)!==gd_client_portal_cached_current_user_id() && !gd_client_portal_is_platform_admin() && !gd_client_portal_user_is_tenant_admin())return new WP_Error('access','Access denied.');
    if(strtolower((string)($data['status']??''))!=='success')return new WP_Error('status','Payment was not successful.');
    $expected=round(gd_client_portal_payment_balance($invoice)*100); $received=absint($data['amount']??0); if($received<=0 || $received>$expected)return new WP_Error('amount','Verified payment amount does not match the invoice balance.');
    $pid=gd_client_portal_payment_record($resolved,$received/100,$data['reference']??$reference,'paystack',$data['channel']??'',$data['status'],$data);
    if($redirect){ wp_safe_redirect(add_query_arg(array('gdcp_payment'=>'success','invoice_id'=>$resolved),get_permalink())); exit; } return $pid?true:new WP_Error('record','Payment could not be recorded.');
}
function gd_client_portal_payment_initialize_ajax(){
    gd_client_portal_ajax_guard('gd_client_portal_payment','nonce'); $invoice=gd_client_portal_payment_invoice(absint($_POST['invoice_id']??0)); if(!$invoice||!gd_client_portal_payment_access($invoice))wp_send_json_error(array('message'=>'Invoice access denied.'),403);
    $response=gd_client_portal_paystack_initialize($invoice); if(is_wp_error($response))wp_send_json_error(array('message'=>$response->get_error_message()),400); $body=json_decode(wp_remote_retrieve_body($response),true); if(wp_remote_retrieve_response_code($response)!==200||empty($body['status'])||empty($body['data']['authorization_url']))wp_send_json_error(array('message'=>$body['message']??'Unable to start payment.'),400);
    wp_send_json_success(array('url'=>esc_url_raw($body['data']['authorization_url'])));
}
add_action('wp_ajax_gd_client_portal_payment_initialize','gd_client_portal_payment_initialize_ajax');

function gd_client_portal_payment_manual_ajax(){
    gd_client_portal_ajax_guard('gd_client_portal_payment_admin','nonce'); gd_client_portal_ajax_require_tenant_admin(); $invoice=gd_client_portal_payment_invoice(absint($_POST['invoice_id']??0)); if(!$invoice||!gd_client_portal_payment_access($invoice,true))wp_send_json_error(array('message'=>'Invoice access denied.'),403); $amount=(float)($_POST['amount']??0); $ref=sanitize_text_field($_POST['reference']??''); $channel=sanitize_text_field($_POST['channel']??'Bank transfer'); if($amount<=0||!$ref)wp_send_json_error(array('message'=>'Amount and payment reference are required.')); $id=gd_client_portal_payment_record($invoice->id,$amount,$ref,'manual',$channel,'confirmed',array('recorded_by'=>gd_client_portal_cached_current_user_id())); if(!$id)wp_send_json_error(array('message'=>'Could not record payment. Check the balance and reference.')); wp_send_json_success(array('message'=>'Payment recorded successfully.'));
}
add_action('wp_ajax_gd_client_portal_payment_manual','gd_client_portal_payment_manual_ajax');

function gd_client_portal_payment_quote_to_invoice_ajax(){
    gd_client_portal_ajax_guard('gd_client_portal_billing_admin','nonce'); gd_client_portal_ajax_require_tenant_admin();
    $quote_id=absint($_POST['quote_id']??0); $q=gdcp_billing_service()->get_quote_for_access($quote_id);
    if(!$q||!gd_client_portal_billing_quote_access($q,true))wp_send_json_error(array('message'=>'Quote access denied.'),403);
    $result=gdcp_billing_service()->convert_accepted_quote_to_invoice($quote_id,$_POST['due_date']??''); if(is_wp_error($result))wp_send_json_error(array('message'=>$result->get_error_message()),$result->get_error_code()==='invalid_project'?403:400);
    $invoice=$result['invoice']; if($result['created']){if(function_exists('gd_client_portal_create_notification'))gd_client_portal_create_notification($q->user_id,__('New invoice','gd-client-portal'),sprintf(__('Invoice %s has been issued.','gd-client-portal'),$invoice->invoice_number),'billing',$q->project_id,$q->tenant_id); if(function_exists('gd_client_portal_audit_log'))gd_client_portal_audit_log('invoice_created',__('Invoice created from quote','gd-client-portal'),$q->project_id,$q->tenant_id,$q->quote_number,'invoice',$invoice->id,array('quote_id'=>$q->id));}
    wp_send_json_success(array('message'=>$result['created']?'Invoice created from quote.':'Invoice already exists.','invoice_id'=>$invoice->id));
}
add_action('wp_ajax_gd_client_portal_payment_quote_to_invoice','gd_client_portal_payment_quote_to_invoice_ajax');

function gd_client_portal_payment_invoice_pdf(){
    if(empty($_GET['gdcp_invoice_pdf']))return; if(!is_user_logged_in()||!gd_client_portal_verify_request())wp_die('Access denied.',403); $invoice=gd_client_portal_payment_invoice(absint($_GET['gdcp_invoice_pdf'])); if(!$invoice||!gd_client_portal_payment_access($invoice))wp_die('Access denied.',403);
    $user=gd_client_portal_cached_user($invoice->user_id); $tenant=function_exists('gd_client_portal_get_tenant')?gd_client_portal_get_tenant($invoice->tenant_id):array(); $tenant_name=is_array($tenant)?($tenant['name']??get_bloginfo('name')):get_bloginfo('name');
    $lines=array('INVOICE',$invoice->invoice_number,'',$tenant_name,'Client: '.($user?$user->display_name:'Client #'.$invoice->user_id),'Email: '.($user?$user->user_email:''),'Date: '.wp_date(get_option('date_format'),strtotime($invoice->created_at)),'Due: '.($invoice->due_date?:'Upon receipt'),'','Description: '.wp_strip_all_tags($invoice->description),'','Subtotal: '.number_format((float)$invoice->subtotal,2).' '.strtoupper($invoice->currency),'Tax: '.number_format((float)$invoice->tax,2).' '.strtoupper($invoice->currency),'Total: '.number_format((float)$invoice->total,2).' '.strtoupper($invoice->currency),'Paid: '.number_format((float)$invoice->amount_paid,2).' '.strtoupper($invoice->currency),'Balance: '.number_format(gd_client_portal_payment_balance($invoice),2).' '.strtoupper($invoice->currency),'Status: '.gd_client_portal_billing_display_status($invoice));
    gd_client_portal_payment_output_simple_pdf($lines,'invoice-'.$invoice->invoice_number.'.pdf'); exit;
}
add_action('template_redirect','gd_client_portal_payment_invoice_pdf',2);
function gd_client_portal_payment_pdf_escape($s){$s=preg_replace('/[^\x20-\x7E]/','?',(string)$s);return str_replace(array('\\','(',')'),array('\\\\','\\(','\\)'),$s);}
function gd_client_portal_payment_output_simple_pdf($lines,$filename){
    $objects=array(); $objects[]= '<< /Type /Catalog /Pages 2 0 R >>'; $objects[]= '<< /Type /Pages /Kids [3 0 R] /Count 1 >>'; $objects[]= '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>'; $content="BT\n/F1 16 Tf\n50 790 Td\n"; foreach($lines as $idx=>$line){if($idx===1)$content.='/F1 12 Tf\n'; $content.='('.gd_client_portal_payment_pdf_escape($line).') Tj\n0 -22 Td\n';} $content.="ET\n"; $objects[]= '<< /Length '.strlen($content).' >> stream\n'.$content.'endstream'; $objects[]= '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'; $pdf="%PDF-1.4\n";$offsets=array(0);foreach($objects as $n=>$obj){$offsets[]=strlen($pdf);$pdf.=(($n+1).' 0 obj\n'.$obj.'\nendobj\n');}$xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";for($i=1;$i<=count($objects);$i++)$pdf.=sprintf('%010d 00000 n \n',$offsets[$i]);$pdf.="trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";nocache_headers();header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.sanitize_file_name($filename).'"');header('Content-Length: '.strlen($pdf));echo $pdf;
}

function gd_client_portal_paystack_webhook(){
    $secret=gd_client_portal_paystack_secret(); if(!$secret)return new WP_REST_Response(array('message'=>'Not configured'),503);
    $raw=file_get_contents('php://input'); $signature=sanitize_text_field($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']??''); if(!$signature||!hash_equals(hash_hmac('sha512',$raw,$secret),$signature))return new WP_REST_Response(array('message'=>'Invalid signature'),401);
    $payload=json_decode($raw,true); if(!is_array($payload))return new WP_REST_Response(array('message'=>'Invalid payload'),400); if(($payload['event']??'')!=='charge.success')return new WP_REST_Response(array('received'=>true),200);
    $data=$payload['data']??array(); $meta=$data['metadata']??array(); $invoice_id=absint($meta['invoice_id']??0); if(!$invoice_id && !empty($data['reference']) && preg_match('/^GDCP-(\d+)-/',$data['reference'],$m))$invoice_id=absint($m[1]);
    if($invoice_id && strtolower((string)($data['status']??''))==='success'){ $invoice=gd_client_portal_payment_invoice($invoice_id); $reference=sanitize_text_field($data['reference']??''); $amount_minor=absint($data['amount']??0); $currency=strtoupper((string)($data['currency']??'')); if($invoice && $reference && $amount_minor>0 && (!$currency || $currency===strtoupper((string)$invoice->currency))){ $received=$amount_minor/100; $balance=gd_client_portal_payment_balance($invoice); if($received>0 && $received<=$balance+0.0001)gd_client_portal_payment_record($invoice_id,$received,$reference,'paystack',$data['channel']??'','success',$data); } }
    return new WP_REST_Response(array('received'=>true),200);
}
add_action('rest_api_init',function(){register_rest_route('gd-client-portal/v1','/paystack/webhook',array('methods'=>'POST','callback'=>'gd_client_portal_paystack_webhook','permission_callback'=>'__return_true'));});

function gd_client_portal_payment_daily_overdue_reminders(){
    if(!function_exists('gd_client_portal_billing_get_invoices'))return; global $wpdb; $t=gd_client_portal_billing_invoices_table(); $rows=$wpdb->get_results("SELECT * FROM $t WHERE status IN ('unpaid','part_paid') AND due_date IS NOT NULL AND due_date < CURDATE() ORDER BY id DESC LIMIT 200");
    foreach((array)$rows as $invoice){$user=gd_client_portal_cached_user($invoice->user_id);if(!$user)continue; $marker='gdcp_overdue_notice_'.$invoice->id.'_'.gmdate('Y-m-d');if(get_transient($marker))continue; set_transient($marker,1,2*DAY_IN_SECONDS); if(function_exists('gd_client_portal_create_notification'))gd_client_portal_create_notification($invoice->user_id,__('Invoice overdue','gd-client-portal'),sprintf(__('Invoice %s is overdue. Outstanding balance: %s.', 'gd-client-portal'),$invoice->invoice_number,gd_client_portal_billing_money(gd_client_portal_payment_balance($invoice),$invoice->currency)),'billing',$invoice->project_id,$invoice->tenant_id); if(function_exists('gd_client_portal_audit_log'))gd_client_portal_audit_log('invoice_overdue_notice',__('Overdue invoice reminder sent','gd-client-portal'),$invoice->project_id,$invoice->tenant_id,$invoice->invoice_number,'invoice',$invoice->id,array('balance'=>gd_client_portal_payment_balance($invoice))); }
}
add_action('gd_client_portal_payment_daily','gd_client_portal_payment_daily_overdue_reminders');
if(!wp_next_scheduled('gd_client_portal_payment_daily'))wp_schedule_event(time()+600,'daily','gd_client_portal_payment_daily');
