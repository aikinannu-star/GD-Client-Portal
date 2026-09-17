<?php
if (!defined('ABSPATH')) exit;

/**
 * Request-scoped identity snapshot. It intentionally derives capabilities and
 * tenant assignment from WordPress on each request; no privilege decision is
 * persisted in a long-lived plugin cache.
 */
final class GDCP_Identity_Context {
    public static function user_id($user_id=0){ return absint($user_id ?: get_current_user_id()); }

    public static function fingerprint($user_id=0){
        $uid=self::user_id($user_id); if($uid<=0)return '';
        $user=get_userdata($uid); if(!$user)return '';
        $roles=is_array($user->roles)?$user->roles:array(); sort($roles,SORT_STRING);
        $caps=is_array($user->allcaps)?$user->allcaps:array(); ksort($caps,SORT_STRING);
        $tenant=function_exists('gdcp_service') && class_exists('GDCP_Tenant_Service') ? absint(gdcp_service('tenant')->current_id($uid)) : 0;
        return hash('sha256',wp_json_encode(array('uid'=>$uid,'roles'=>$roles,'caps'=>$caps,'tenant'=>$tenant)));
    }
}

function gdcp_identity_fingerprint($user_id=0){ return GDCP_Identity_Context::fingerprint($user_id); }

if (!function_exists('gdcp_flush_identity_cache')) {
    function gdcp_flush_identity_cache($user_id=0){
        $user_id=absint($user_id); if($user_id<=0)return;
        if(function_exists('clean_user_cache')) clean_user_cache($user_id);
        wp_cache_delete('gdcp_identity_fingerprint_'.$user_id);
        wp_cache_delete('gdcp_tenant_context_'.$user_id);
    }
}
