<?php
if (!defined('ABSPATH')) exit;

final class GDCP_Payment_Service {
    private $payments;
    private $billing;
    public function __construct(){ $this->payments=new GDCP_Payment_Repository(); $this->billing=new GDCP_Billing_Repository(); }
    public function get_invoice($id){ return $this->billing->find_invoice($id); }
    public function get_for_invoice($invoice_id){ return $this->payments->find_for_invoice($invoice_id); }
    public function record($invoice_id,$amount,$reference,$gateway='manual',$channel='',$gateway_status='success',$metadata=array()){
        global $wpdb;
        $invoice_id=absint($invoice_id); $amount=max(0,(float)$amount); $reference=sanitize_text_field($reference);
        if(!$invoice_id||$amount<=0||!$reference) return new WP_Error('invalid_payment','Invoice, amount and payment reference are required.');
        $wpdb->query('START TRANSACTION');
        try {
            $existing=$this->payments->find_by_reference($reference,true);
            if($existing){
                if(absint($existing->invoice_id)!==$invoice_id){
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('payment_reference_conflict','This payment reference is already bound to a different invoice.');
                }
                $existing_amount=(float)$existing->amount;
                if(abs($existing_amount-$amount)>0.0001){
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('payment_amount_conflict','This payment reference was already recorded with a different amount.');
                }
                $existing_invoice=$this->billing->find_invoice($invoice_id);
                $wpdb->query('COMMIT');
                return array('id'=>absint($existing->id),'payment'=>$existing,'invoice'=>$existing_invoice,'created'=>false);
            }
            $invoice=$this->billing->find_invoice($invoice_id,true);
            if(!$invoice){$wpdb->query('ROLLBACK');return new WP_Error('invoice_not_found','Invoice not found.');}
            $balance=max(0,(float)$invoice->total-(float)$invoice->amount_paid); $amount=min($amount,$balance);
            if($amount<=0){$wpdb->query('ROLLBACK');return new WP_Error('no_balance','Invoice has no outstanding balance.');}
            $new_paid=(float)$invoice->amount_paid+$amount; $total=(float)$invoice->total;
            $status=$total<=0?'paid':($new_paid>=$total-0.0001?'paid':($new_paid>0?'part_paid':'unpaid'));
            $now=current_time('mysql');
            $id=$this->payments->insert(array('invoice_id'=>$invoice_id,'tenant_id'=>$invoice->tenant_id,'user_id'=>$invoice->user_id,'project_id'=>$invoice->project_id,'amount'=>$amount,'currency'=>$invoice->currency,'gateway'=>sanitize_key($gateway),'reference'=>$reference,'channel'=>sanitize_text_field($channel),'status'=>'success','gateway_status'=>sanitize_text_field($gateway_status),'metadata'=>wp_json_encode($metadata),'created_at'=>$now));
            if(!$id){
                // A concurrent request may have inserted the same unique reference after our
                // initial lookup. Re-read after rollback and treat an exact match as idempotent.
                $wpdb->query('ROLLBACK');
                $concurrent=$this->payments->find_by_reference($reference,false);
                if($concurrent && absint($concurrent->invoice_id)===$invoice_id && abs((float)$concurrent->amount-$amount)<=0.0001){
                    return array('id'=>absint($concurrent->id),'payment'=>$concurrent,'invoice'=>$this->billing->find_invoice($invoice_id),'created'=>false);
                }
                if($concurrent && absint($concurrent->invoice_id)!==$invoice_id) return new WP_Error('payment_reference_conflict','This payment reference is already bound to a different invoice.');
                return new WP_Error('payment_create_failed','Could not record payment.');
            }
            $updated=$wpdb->query($wpdb->prepare('UPDATE '.$this->billing->invoices_table().' SET amount_paid=%f,status=%s,payment_reference=%s,updated_at=%s,paid_at=%s WHERE id=%d AND amount_paid=%f',$new_paid,$status,$reference,$now,$status==='paid'?$now:null,$invoice_id,(float)$invoice->amount_paid));
            if($updated!==1){$wpdb->query('ROLLBACK');return new WP_Error('invoice_update_failed','Could not update invoice balance.');}
            $payment=$this->payments->find($id); $updated_invoice=$this->billing->find_invoice($invoice_id); $wpdb->query('COMMIT');
        } catch(Throwable $e){$wpdb->query('ROLLBACK');return new WP_Error('payment_transaction_failed','Payment transaction could not be completed.');}
        do_action('gd_client_portal_payment_received',$payment);
        if(function_exists('gd_client_portal_create_notification')) gd_client_portal_create_notification($updated_invoice->user_id,__('Payment received','gd-client-portal'),sprintf(__('Payment of %s was recorded for invoice %s.','gd-client-portal'),gd_client_portal_billing_money($amount,$updated_invoice->currency),$updated_invoice->invoice_number),'billing',$updated_invoice->project_id,$updated_invoice->tenant_id);
        if(function_exists('gd_client_portal_audit_log')) gd_client_portal_audit_log('payment_recorded',__('Payment recorded','gd-client-portal'),$updated_invoice->project_id,$updated_invoice->tenant_id,$reference,'payment',$id,array('invoice_id'=>$invoice_id,'amount'=>$amount,'gateway'=>$gateway));
        return array('id'=>$id,'payment'=>$payment,'invoice'=>$updated_invoice,'created'=>true);
    }
}
function gdcp_payment_service(){ static $service; if(!$service)$service=new GDCP_Payment_Service(); return $service; }
