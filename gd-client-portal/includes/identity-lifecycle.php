<?php
if (!defined('ABSPATH')) exit;

/** Invalidate request-facing identity caches whenever privilege state changes. */
function gdcp_identity_invalidate_on_role_change($user_id){
    $user_id=absint($user_id); if($user_id<=0)return;
    if(function_exists('gdcp_flush_identity_cache')) gdcp_flush_identity_cache($user_id);
    // Revoke existing login sessions after privilege changes so a stale session
    // cannot retain the old role/capability state.
    if(function_exists('wp_destroy_all_sessions')) wp_destroy_all_sessions($user_id);
}
add_action('set_user_role','gdcp_identity_invalidate_on_role_change',10,1);
add_action('add_user_role','gdcp_identity_invalidate_on_role_change',10,1);
add_action('remove_user_role','gdcp_identity_invalidate_on_role_change',10,1);
add_action('profile_update','gdcp_identity_invalidate_on_role_change',10,1);
add_action('updated_user_meta','gdcp_identity_invalidate_user_meta',10,4);
add_action('deleted_user_meta','gdcp_identity_invalidate_user_meta',10,4);
function gdcp_identity_invalidate_user_meta($meta_id,$user_id,$meta_key,$meta_value){
    if($meta_key==='gd_client_portal_tenant_id'){
        if(function_exists('gdcp_flush_identity_cache')) gdcp_flush_identity_cache($user_id);
        if(function_exists('wp_destroy_all_sessions')) wp_destroy_all_sessions(absint($user_id));
    }
}
