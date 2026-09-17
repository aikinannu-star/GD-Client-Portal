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

        // A non-platform user must have an explicit tenant assignment.
        // Unassigned/legacy records (tenant 0) are never treated as public.
        if ($required_tenant <= 0 || $current_tenant <= 0) {
            return false;
        }

        return $required_tenant === $current_tenant;
    }
}

if (!function_exists('gd_client_portal_verify_project_access')) {
    /**
     * Enforce both tenant isolation and ownership for a project.
     * Platform admins can access every project; tenant admins can access every
     * project inside their assigned tenant; regular clients can access only
     * their own project inside their assigned tenant.
     */
    function gd_client_portal_verify_project_access($project, $allow_tenant_admin = true)
    {
        if (!gd_client_portal_verify_request() || !is_object($project)) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $tenant_id = absint($project->tenant_id ?? 0);
        if (!gd_client_portal_verify_tenant_access($tenant_id)) {
            return false;
        }

        if ($allow_tenant_admin && gd_client_portal_user_is_tenant_admin()) {
            return true;
        }

        return absint($project->user_id ?? 0) === gd_client_portal_cached_current_user_id();
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
        if (!gd_client_portal_verify_request()) {
            return false;
        }

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



if (!function_exists('gd_client_portal_map_admin_access_capability')) {
    /**
     * Map the plugin's admin access meta capability to the capabilities that
     * actually grant access. This lets platform administrators and dedicated
     * tenant administrators use the same admin menu without granting
     * manage_options to tenant administrators.
     */
    function gd_client_portal_map_admin_access_capability($caps, $cap, $user_id, $args)
    {
        if ($cap !== 'gd_client_portal_access_admin') {
            return $caps;
        }

        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return array('do_not_allow');
        }

        // Platform administrators retain full plugin admin access.
        if (user_can($user_id, 'manage_options')) {
            return array();
        }

        // Dedicated tenant administrators get tenant-scoped admin access.
        if (user_can($user_id, 'gd_client_portal_tenant_admin')) {
            return array();
        }

        // Backward compatibility for administrator accounts whose custom
        // capability was not yet migrated.
        $user = gd_client_portal_cached_user($user_id);
        if ($user && in_array('administrator', (array) $user->roles, true)) {
            return array();
        }

        return array('do_not_allow');
    }
    add_filter('map_meta_cap', 'gd_client_portal_map_admin_access_capability', 10, 4);
}

if (!function_exists('gd_client_portal_admin_access_capability')) {
    function gd_client_portal_admin_access_capability()
    {
        return 'gd_client_portal_access_admin';
    }
}

if (!function_exists('gd_client_portal_user_is_tenant_admin')) {
    /**
     * Lightweight check for tenant admin role. Defaults to false unless a
     * `tenant_admin` role/capability exists or the user has `manage_options`.
     */
    function gd_client_portal_user_is_tenant_admin($user_id = 0)
    {
        $user_id = absint($user_id ?: gd_client_portal_cached_current_user_id());
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
        $user_id = absint($user_id ?: gd_client_portal_cached_current_user_id());
        if ($user_id <= 0) {
            return false;
        }

        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        if (user_can($user_id, 'gd_client_portal_tenant_admin')) {
            return true;
        }

        $user = gd_client_portal_cached_user($user_id);
        if ($user && !empty($user->roles) && in_array('administrator', (array) $user->roles, true)) {
            return true;
        }

        return false;
    }
}

/**
 * Send a consistent JSON authorization failure for AJAX handlers.
 */
if (!function_exists('gd_client_portal_ajax_require')) {
    function gd_client_portal_ajax_require($condition, $message = 'Access denied.', $status = 403)
    {
        if (!$condition) {
            wp_send_json_error(array('message' => $message), absint($status));
        }
        return true;
    }
}

/**
 * Verify that a file path resolves inside WordPress' uploads directory.
 * This is a defense-in-depth check before streaming portal files.
 */
if (!function_exists('gd_client_portal_verify_upload_path')) {
    function gd_client_portal_verify_upload_path($path)
    {
        if (empty($path) || !is_string($path)) {
            return false;
        }

        $uploads = wp_get_upload_dir();
        $base = isset($uploads['basedir']) ? realpath($uploads['basedir']) : false;
        $real = realpath($path);

        if ($base === false || $real === false || !is_file($real) || !is_readable($real)) {
            return false;
        }

        $base = rtrim(wp_normalize_path($base), '/') . '/';
        $real = wp_normalize_path($real);

        return strpos($real, $base) === 0;
    }
}

/**
 * Canonical AJAX guard for migrated handlers.
 * Centralizes authentication, nonce validation and optional capability checks.
 */
if (!function_exists('gd_client_portal_ajax_guard')) {
    function gd_client_portal_ajax_guard($nonce_action, $nonce_field = 'nonce', $capability = '')
    {
        if (!gd_client_portal_verify_request()) {
            wp_send_json_error(array('message' => 'Authentication required.'), 401);
        }

        if ($capability !== '' && !current_user_can($capability)) {
            wp_send_json_error(array('message' => 'Access denied.'), 403);
        }

        $nonce = isset($_REQUEST[$nonce_field]) ? sanitize_text_field(wp_unslash($_REQUEST[$nonce_field])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, $nonce_action)) {
            wp_send_json_error(array('message' => 'Invalid security token.'), 403);
        }

        return true;
    }
}

/**
 * Require platform or tenant administration for tenant-scoped mutations.
 */
if (!function_exists('gd_client_portal_ajax_require_tenant_admin')) {
    function gd_client_portal_ajax_require_tenant_admin()
    {
        gd_client_portal_ajax_require(
            gd_client_portal_is_platform_admin() || gd_client_portal_user_is_tenant_admin(),
            'Administrative access required.',
            403
        );
        return true;
    }
}


/**
 * Canonical object guard for tenant-scoped records.
 * Returns false for missing/unassigned tenants for non-platform users.
 */
if (!function_exists('gd_client_portal_ajax_require_tenant_record')) {
    function gd_client_portal_ajax_require_tenant_record($record, $tenant_field = 'tenant_id', $message = 'Access denied.')
    {
        if (!is_object($record)) {
            wp_send_json_error(array('message' => $message), 404);
        }
        $tenant_id = isset($record->{$tenant_field}) ? absint($record->{$tenant_field}) : 0;
        gd_client_portal_ajax_require(
            gd_client_portal_verify_tenant_access($tenant_id),
            $message,
            403
        );
        return true;
    }
}

/**
 * Validate an uploaded file before handing it to wp_handle_upload().
 */
if (!function_exists('gd_client_portal_validate_upload')) {
    function gd_client_portal_validate_upload($file, $allowed_mimes, $max_size = 0)
    {
        if (!is_array($file) || empty($file['tmp_name']) || !empty($file['error'])) {
            return new WP_Error('gdcp_upload_invalid', __('Please select a valid file.', 'gd-client-portal'));
        }
        if ($max_size > 0 && (absint($file['size']) <= 0 || absint($file['size']) > $max_size)) {
            return new WP_Error('gdcp_upload_size', __('The selected file exceeds the allowed upload size.', 'gd-client-portal'));
        }
        $checked = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);
        if (empty($checked['ext']) || empty($checked['type'])) {
            return new WP_Error('gdcp_upload_type', __('This file type is not allowed.', 'gd-client-portal'));
        }
        return true;
    }
}


/** Convert a WordPress uploads URL to a canonical local path. */
if (!function_exists('gd_client_portal_upload_url_to_path')) {
    function gd_client_portal_upload_url_to_path($url) {
        $uploads = wp_get_upload_dir();
        $baseurl = untrailingslashit($uploads['baseurl']);
        $basedir = realpath($uploads['basedir']);
        if (!$basedir || !is_string($url) || strpos($url, $baseurl . '/') !== 0) return false;
        $relative = ltrim(substr($url, strlen($baseurl)), '/');
        $candidate = realpath($basedir . DIRECTORY_SEPARATOR . $relative);
        if (!$candidate || strpos($candidate, $basedir . DIRECTORY_SEPARATOR) !== 0 || !is_file($candidate)) return false;
        return $candidate;
    }
}

/** Stream an authorized private portal file. */
if (!function_exists('gd_client_portal_stream_private_file')) {
    function gd_client_portal_stream_private_file($url, $filename = '', $mime = '') {
        $path = gd_client_portal_upload_url_to_path($url);
        if (!$path) wp_die(esc_html__('File unavailable.', 'gd-client-portal'), '', array('response'=>404));
        gd_client_portal_stream_private_path($path, $filename, $mime);
    }
}


/** Build a nonce-protected URL for a private project attachment. */
if (!function_exists('gd_client_portal_private_project_file_url')) {
    function gd_client_portal_private_project_file_url($project_id) {
        $project_id = absint($project_id);
        if (!$project_id) return '';
        $url = add_query_arg(array(
            'action' => 'gd_client_portal_project_file_download',
            'project_id' => $project_id,
        ), admin_url('admin-post.php'));
        return wp_nonce_url($url, 'gd_client_portal_project_file_download_' . $project_id);
    }
}

/** Build a nonce-protected URL for a private project message attachment. */
if (!function_exists('gd_client_portal_private_message_file_url')) {
    function gd_client_portal_private_message_file_url($message_id) {
        $message_id = absint($message_id);
        if (!$message_id) return '';
        $url = add_query_arg(array(
            'action' => 'gd_client_portal_message_file_download',
            'message_id' => $message_id,
        ), admin_url('admin-post.php'));
        return wp_nonce_url($url, 'gd_client_portal_message_file_download_' . $message_id);
    }
}

/**
 * Determine a safe MIME type for private file streaming.
 * The stored MIME value is treated as a hint, never as the sole authority.
 */
if (!function_exists('gd_client_portal_private_file_mime')) {
    function gd_client_portal_private_file_mime($path, $hint = '')
    {
        $type = '';
        if (function_exists('wp_check_filetype')) {
            $checked = wp_check_filetype(basename($path));
            if (!empty($checked['type'])) {
                $type = $checked['type'];
            }
        }
        if ($type === '' && is_string($hint) && preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $hint)) {
            $type = $hint;
        }
        return $type ?: 'application/octet-stream';
    }
}

/**
 * Send a private-file response using canonical path and header hardening.
 */
if (!function_exists('gd_client_portal_stream_private_path')) {
    function gd_client_portal_stream_private_path($path, $filename = '', $mime = '')
    {
        if (!gd_client_portal_verify_upload_path($path)) {
            wp_die(esc_html__('File unavailable.', 'gd-client-portal'), '', array('response' => 404));
        }
        $name = sanitize_file_name($filename ?: basename($path));
        if ($name === '') {
            $name = 'download';
        }
        $safe_mime = gd_client_portal_private_file_mime($path, $mime);
        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Content-Type: ' . $safe_mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        readfile($path);
        exit;
    }
}
