<?php
if (!defined('ABSPATH')) exit;
final class GDCP_Billing_Controller extends GDCP_Controller_Base {
    public function create_invoice(){
        if(!$this->require_login() || !$this->nonce('gdcp_billing')) return $this->fail('Invalid request',403);
        if(!gdcp_can('create','invoice')) return $this->fail('Not authorized',403);
        $service=gdcp_service('billing'); if(!$service) return $this->fail('Billing service unavailable',503);
        $data=array('user_id'=>get_current_user_id(),'project_id'=>absint($_POST['project_id']??0),'total'=>(float)($_POST['total']??0),'currency'=>sanitize_text_field(wp_unslash($_POST['currency']??'GHS')),'description'=>sanitize_textarea_field(wp_unslash($_POST['description']??'')),'due_date'=>sanitize_text_field(wp_unslash($_POST['due_date']??'')));
        $id=$service->create_invoice($data); return $id ? $this->ok(array('invoice_id'=>absint($id)),201) : $this->fail('Invoice creation failed',409);
    }
}
