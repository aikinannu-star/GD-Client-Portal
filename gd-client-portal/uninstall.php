<?php
/**
 * Uninstall script for the GD Client Portal plugin.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('gd_client_portal_installed');

if (function_exists('wp_clear_scheduled_hook')) wp_clear_scheduled_hook('gd_client_portal_sla_daily');
