<?php
if (!defined('ABSPATH')) exit;
/** v7.6: canonical controller boundary for newly migrated AJAX actions. */
final class GDCP_Ajax_Adapters {
    public static function register(){
        add_action('wp_ajax_gdcp_project_stage',array(__CLASS__,'project_stage'));
        add_action('wp_ajax_gdcp_create_invoice',array(__CLASS__,'create_invoice'));
    }
    public static function project_stage(){ (new GDCP_Project_Controller())->stage(); }
    public static function create_invoice(){ (new GDCP_Billing_Controller())->create_invoice(); }
}
add_action('init',array('GDCP_Ajax_Adapters','register'),20);
