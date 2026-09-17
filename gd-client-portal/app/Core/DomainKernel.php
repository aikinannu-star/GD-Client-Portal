<?php
if (!defined('ABSPATH')) exit;
/** v7 domain kernel: one canonical entry point for domain services/repositories. */
final class GDCP_Domain_Kernel {
    public static function repository($domain){ return function_exists('gdcp_repository') ? gdcp_repository($domain) : null; }
    public static function service($domain){ return function_exists('gdcp_service') ? gdcp_service($domain) : null; }
    public static function can($action,$object,$id=0,$args=array()){ return function_exists('gdcp_can') ? gdcp_can($action,$object,$id,$args) : false; }
    public static function event($event,$payload=array()){ return function_exists('gdcp_event') ? gdcp_event($event,$payload) : null; }
}
function gdcp_domain(){ return 'GDCP_Domain_Kernel'; }
