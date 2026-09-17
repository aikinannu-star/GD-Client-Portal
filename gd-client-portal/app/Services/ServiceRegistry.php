<?php
if (!defined('ABSPATH')) exit;
final class GDCP_Service_Registry {
    private static $instances=array();
    private static $map=array(
        'project'=>'GDCP_Project_Service','tenant'=>'GDCP_Tenant_Service','billing'=>'GDCP_Billing_Service',
        'document'=>'GDCP_Document_Service','support'=>'GDCP_Support_Service','authorization'=>'GDCP_Authorization_Service','approval'=>'GDCP_Approval_Service','onboarding'=>'GDCP_Onboarding_Service'
    );
    public static function get($key){
        $key=sanitize_key($key); $class=self::$map[$key]??'';
        if(!$class || !class_exists($class)) return null;
        if(!isset(self::$instances[$key])) self::$instances[$key]=new $class();
        return self::$instances[$key];
    }
}
function gdcp_service($key){return GDCP_Service_Registry::get($key);}
