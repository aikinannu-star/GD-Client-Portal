<?php
if (!defined('ABSPATH')) exit;

/** Central tenant context and isolation helpers for v7.4. */
final class GDCP_Tenant_Context {
    public static function current(){
        if (function_exists('gd_client_portal_is_platform_admin') && gd_client_portal_is_platform_admin()) return 0;
        if (function_exists('gd_client_portal_get_current_tenant_id')) return absint(gd_client_portal_get_current_tenant_id());
        return 0;
    }
    public static function can_access($tenant_id){
        $tenant_id=absint($tenant_id);
        if ($tenant_id<=0) return false;
        if (function_exists('gd_client_portal_is_platform_admin') && gd_client_portal_is_platform_admin()) return true;
        return self::current() === $tenant_id;
    }
    public static function require($tenant_id){ return self::can_access($tenant_id); }
    public static function filter_ids($ids){
        $ids=array_values(array_unique(array_filter(array_map('absint',(array)$ids))));
        if (function_exists('gd_client_portal_is_platform_admin') && gd_client_portal_is_platform_admin()) return $ids;
        $current=self::current();
        return $current>0 ? ($ids ? array_values(array_intersect($ids,array($current))) : array()) : array();
    }
}
function gdcp_current_tenant_id(){ return GDCP_Tenant_Context::current(); }
function gdcp_tenant_can_access($tenant_id){ return GDCP_Tenant_Context::can_access($tenant_id); }
