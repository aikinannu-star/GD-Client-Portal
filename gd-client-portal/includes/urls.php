<?php
/**
 * URL helpers for the GD Client Portal plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gd_client_portal_get_dashboard_url')) {
    function gd_client_portal_get_dashboard_url()
    {
        return home_url('/dashboard/');
    }
}
