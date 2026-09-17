<?php
/**
 * Security helpers for the GD Client Portal plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gd_client_portal_verify_request')) {
    function gd_client_portal_verify_request()
    {
        return is_user_logged_in() && current_user_can('read');
    }
}

if (!function_exists('gd_client_portal_verify_tenant_access')) {
    function gd_client_portal_verify_tenant_access($tenant_id = null)
    {
        if (!gd_client_portal_verify_request()) {
            return false;
        }

        $required_tenant = absint($tenant_id !== null ? $tenant_id : gd_client_portal_get_current_tenant_id());
        $current_tenant = gd_client_portal_get_current_tenant_id();

        if (current_user_can('manage_options')) {
            return true;
        }

        if ($required_tenant <= 0) {
            return true;
        }

        return $current_tenant > 0 && $required_tenant === $current_tenant;
    }
}

if (!function_exists('gd_client_portal_verify_nonce_request')) {
    function gd_client_portal_verify_nonce_request($action = 'gd_client_portal_nonce')
    {
        if (!gd_client_portal_verify_request()) {
            return false;
        }

        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';

        return wp_verify_nonce($nonce, $action);
    }
}

if (!function_exists('gd_client_portal_verify_ajax_tenant')) {
    /**
     * Verify an incoming AJAX/POST request is scoped to the current tenant.
     * Returns true for platform admins.
     */
    function gd_client_portal_verify_ajax_tenant($param_name = 'tenant_id')
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        $req_tenant = 0;
        if (!empty($_REQUEST[$param_name])) {
            $req_tenant = absint(wp_unslash($_REQUEST[$param_name]));
        }

        $current = gd_client_portal_get_current_tenant_id();

        // If request did not provide a tenant, allow if current user has a tenant
        if ($req_tenant <= 0) {
            return $current > 0;
        }

        return $current > 0 && $req_tenant === $current;
    }
}

if (!function_exists('gd_client_portal_user_is_tenant_admin')) {
    /**
     * Lightweight check for tenant admin role. Defaults to false unless a
     * `tenant_admin` role/capability exists or the user has `manage_options`.
     */
    function gd_client_portal_user_is_tenant_admin($user_id = 0)
    {
        $user_id = absint($user_id ?: get_current_user_id());
        if ($user_id <= 0) { return false; }
        if (user_can($user_id, 'manage_options')) { return true; }
        // allow a specific capability if present
        if (user_can($user_id, 'gd_client_portal_tenant_admin')) { return true; }
        return false;
    }
}

if (!function_exists('gd_client_portal_user_can_access_admin')) {
    /**
     * Platform administrators and plugin tenant admins can access the portal admin screens.
     * This is more resilient than relying on the role name alone.
     */
    function gd_client_portal_user_can_access_admin($user_id = 0)
    {
        $user_id = absint($user_id ?: get_current_user_id());
        if ($user_id <= 0) {
            return false;
        }

        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        if (user_can($user_id, 'gd_client_portal_tenant_admin')) {
            return true;
        }

        $user = get_userdata($user_id);
        if ($user && !empty($user->roles) && in_array('administrator', (array) $user->roles, true)) {
            return true;
        }

        return false;
    }
}
