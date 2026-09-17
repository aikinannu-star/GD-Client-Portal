<?php
/**
 * Plugin Name: GD Client Portal
 * Description: Production-ready modular client portal for service projects, workflow tracking, deliverables, account access, and dashboard sections.
 * Version: 1.1.0
 * Author: Aikinannu
 * Text Domain: gd-client-portal
 * Requires at least: 6.0
 * Tested up to: 6.6
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('GD_CLIENT_PORTAL_VERSION')) {
    define('GD_CLIENT_PORTAL_VERSION', '1.1.0');
}

if (!defined('GD_CLIENT_PORTAL_PATH')) {
    define('GD_CLIENT_PORTAL_PATH', plugin_dir_path(__FILE__));
}

if (!defined('GD_CLIENT_PORTAL_URL')) {
    define('GD_CLIENT_PORTAL_URL', plugin_dir_url(__FILE__));
}

function gd_client_portal_bootstrap()
{
    require_once GD_CLIENT_PORTAL_PATH . 'includes/helpers.php';
    require_once GD_CLIENT_PORTAL_PATH . 'includes/urls.php';
    require_once GD_CLIENT_PORTAL_PATH . 'includes/security.php';
    require_once GD_CLIENT_PORTAL_PATH . 'includes/assets.php';
    require_once GD_CLIENT_PORTAL_PATH . 'includes/installer.php';
    require_once GD_CLIENT_PORTAL_PATH . 'includes/auth.php';
    require_once GD_CLIENT_PORTAL_PATH . 'includes/dashboard.php';
    require_once GD_CLIENT_PORTAL_PATH . 'includes/dashboard-sections.php';
    require_once GD_CLIENT_PORTAL_PATH . 'includes/service-projects.php';
    require_once GD_CLIENT_PORTAL_PATH . 'includes/admin.php';
    // Auto-load modules: require each modules/*/index.php if present.
    // If the admin setting `gd_client_portal_enabled_modules` is empty, fall back to loading all modules (legacy behavior).
    $modules_dir = GD_CLIENT_PORTAL_PATH . 'modules/';
    if (is_dir($modules_dir)) {
        $enabled_modules = get_option('gd_client_portal_enabled_modules', array());
        $enabled_modules = is_array($enabled_modules) ? $enabled_modules : array();
        $all_index_files = glob($modules_dir . '*/index.php');
        if (empty($enabled_modules)) {
            // legacy: load everything
            foreach ($all_index_files as $module_file) {
                if (is_file($module_file)) {
                    require_once $module_file;
                }
            }
        } else {
            // load only enabled modules
            foreach ($all_index_files as $module_file) {
                $slug = basename(dirname($module_file));
                if (in_array($slug, $enabled_modules, true)) {
                    require_once $module_file;
                }
            }
        }
    }
}

gd_client_portal_bootstrap();

function gd_client_portal_load_textdomain()
{
    load_plugin_textdomain('gd-client-portal', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_action('plugins_loaded', 'gd_client_portal_load_textdomain');

function gd_client_portal_admin_notice()
{
    if (function_exists('is_plugin_active') && is_plugin_active('woocommerce/woocommerce.php')) {
        return;
    }

    if (class_exists('WooCommerce')) {
        return;
    }

    echo '<div class="notice notice-warning"><p>' . esc_html__('GD Client Portal works best with WooCommerce enabled for service projects, invoices, and downloads.', 'gd-client-portal') . '</p></div>';
}

add_action('admin_notices', 'gd_client_portal_admin_notice');

register_activation_hook(__FILE__, 'gd_client_portal_activate');
register_deactivation_hook(__FILE__, 'gd_client_portal_deactivate');
