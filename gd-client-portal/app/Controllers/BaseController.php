<?php
if (!defined('ABSPATH')) exit;
abstract class GDCP_Controller_Base {
    protected function ok($data=array(),$status=200){ return wp_send_json_success($data,$status); }
    protected function fail($message,$status=400){ return wp_send_json_error(array('message'=>$message),$status); }
    protected function nonce($action){ return check_ajax_referer($action,'nonce',false) !== false; }
    protected function require_login(){ if(!is_user_logged_in()) return $this->fail('Authentication required',401); return true; }
}
