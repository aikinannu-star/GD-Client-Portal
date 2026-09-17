<?php
if (!defined('ABSPATH')) exit;
final class GDCP_Repository_Registry {
    private static $instances=array();
    public static function get($key){
        $map=array(
            'project'=>'GDCP_Project_Repository',
            'tenant'=>'GDCP_Tenant_Repository',
            'notification'=>'GDCP_Notifications_Repository',
            'billing'=>'GDCP_Billing_Repository',
            'document'=>'GDCP_Document_Repository',
            'support'=>'GDCP_Support_Repository',
            'automation'=>'GDCP_Automation_Repository',
            'approval'=>'GDCP_Approval_Repository',
            'onboarding'=>'GDCP_Onboarding_Repository',
            'collaboration'=>'GDCP_Collaboration_Repository',
            'intake'=>'GDCP_Intake_Repository',
            'request'=>'GDCP_Request_Repository',
            'payment'=>'GDCP_Payment_Repository',
            'feedback'=>'GDCP_Feedback_Repository',
            'sla'=>'GDCP_SLA_Repository',
            'executive'=>'GDCP_Executive_Analytics_Repository',
        );
        $key=sanitize_key($key); if(!isset($map[$key])||!class_exists($map[$key])) return null;
        if(!isset(self::$instances[$key])) self::$instances[$key]=new $map[$key](); return self::$instances[$key];
    }
}
function gdcp_repository($key){return GDCP_Repository_Registry::get($key);}
