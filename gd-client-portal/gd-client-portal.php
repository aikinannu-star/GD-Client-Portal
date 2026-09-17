<?php
/**
 * SPDX-License-Identifier: GPL-2.0-or-later
 */
/**
 * Plugin Name: GD Client Portal
 * Description: Production-ready modular client portal for service projects, workflow tracking, deliverables, account access, and dashboard sections.
 * Version: 7.6.0
 * Author: Aikinannu
 * Text Domain: gd-client-portal
 * Requires at least: 6.0
 * Tested up to: 7.1
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('GD_CLIENT_PORTAL_VERSION')) {
    define('GD_CLIENT_PORTAL_VERSION', '7.6.0');
}

if (!defined('GD_CLIENT_PORTAL_PATH')) {
    define('GD_CLIENT_PORTAL_PATH', plugin_dir_path(__FILE__));
}

if (!defined('GD_CLIENT_PORTAL_URL')) {
    define('GD_CLIENT_PORTAL_URL', plugin_dir_url(__FILE__));
}

function gd_client_portal_safe_require($file, $label = '')
{
    if (!is_string($file) || !is_file($file)) return false;
    try {
        require_once $file;
        if (class_exists('GDCP_Application_Kernel')) { GDCP_Application_Kernel::record_loaded($label ?: basename($file), $file); }
        return true;
    } catch (Throwable $e) {
        $failures = get_option('gd_client_portal_bootstrap_failures', array());
        $failures = is_array($failures) ? $failures : array();
        $failures[] = array('module'=>sanitize_text_field($label ?: basename($file)), 'message'=>sanitize_text_field($e->getMessage()), 'file'=>sanitize_text_field($e->getFile()), 'line'=>absint($e->getLine()), 'time'=>function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'));
        update_option('gd_client_portal_bootstrap_failures', array_slice($failures, -20), false);
        if (class_exists('GDCP_Application_Kernel')) { GDCP_Application_Kernel::record_failure($label ?: basename($file), $e->getMessage(), $e->getFile(), $e->getLine()); }
        error_log('[GD Client Portal] Module bootstrap failed: '.($label ?: basename($file)).' — '.$e->getMessage());
        return false;
    }
}

// Load the installer before the rest of the module graph and register the
// activation hooks immediately. This prevents a broken optional module from
// ever preventing WordPress from registering the plugin activation callback.
require_once GD_CLIENT_PORTAL_PATH . 'includes/installer.php';
register_activation_hook(__FILE__, 'gd_client_portal_activate');
register_deactivation_hook(__FILE__, 'gd_client_portal_deactivate');

function gdcp_register_event_bus_legacy_bridges(){
    if(!function_exists('gdcp_register_legacy_event_bridges')) return;
    gdcp_register_legacy_event_bridges(array(
        'project_created','project_claimed','project_stage_changed','requirements_submitted','project_message_added',
        'approval_requested','approval_decided','delivery_published','delivery_finalized','project_assigned',
        'project_milestone_created','project_milestone_deleted','sla_escalated','client_reminder_sent',
        'service_request_created','support_ticket_created','support_ticket_status_changed','feedback_submitted',
        'document_requested','document_uploaded','task_created','lifecycle_gap','client_experience_at_risk',
        'quote_accepted','payment_received','payment_overdue','intake_completed','onboarding_completed'
    ));
}
add_action('plugins_loaded','gdcp_register_event_bus_legacy_bridges',25);

function gd_client_portal_bootstrap()
{
    gd_client_portal_safe_require(GD_CLIENT_PORTAL_PATH . 'app/Core/ApplicationKernel.php', 'platform_application_kernel');
    if (class_exists('GDCP_Application_Kernel')) GDCP_Application_Kernel::start();
    // Every remaining dependency is isolated. Parse/runtime failures in one
    // module are recorded and skipped rather than aborting plugin activation.
    $core_files = array('helpers','urls','security','assets');
    if (class_exists('GDCP_Application_Kernel')) GDCP_Application_Kernel::phase('core');
    foreach ($core_files as $core) {
        gd_client_portal_safe_require(GD_CLIENT_PORTAL_PATH . 'includes/' . $core . '.php', 'core_' . $core);
    }

    // v6.7 platform foundation. These classes are loaded before feature modules
    // so future domains can migrate to a consistent registry/service/repository
    // architecture without breaking legacy modules.
    if (class_exists('GDCP_Application_Kernel')) GDCP_Application_Kernel::phase('platform');
    $platform_files = array(
        'app/Core/ModuleRegistry.php',
        'app/Core/EventBus.php',
        'app/Core/MigrationManager.php',
        'app/Core/DomainKernel.php',
        'app/Core/Api.php',
        'app/Security/Authorization.php',
        'app/Security/TenantContext.php',
        'app/Repositories/BaseRepository.php',
        'app/Repositories/ProjectRepository.php',
        'app/Repositories/TenantRepository.php',
        'app/Repositories/AutomationRepository.php',
        'app/Repositories/ApprovalRepository.php',
        'app/Repositories/OnboardingRepository.php',
        'app/Repositories/RepositoryRegistry.php',
        'app/Services/ServiceBase.php',
        'app/Services/ProjectService.php',
        'app/Services/ApprovalService.php',
        'app/Services/OnboardingService.php',
        'app/Services/TenantService.php',
        'app/Services/BillingService.php',
        'app/Services/DocumentService.php',
        'app/Services/SupportService.php',
        'app/Services/AuthorizationService.php',
        'app/Services/ServiceRegistry.php',
        'app/Controllers/BaseController.php',
        'app/Controllers/ProjectController.php',
        'app/Controllers/BillingController.php',
        'app/Controllers/SupportController.php',
        'app/Controllers/AjaxAdapters.php',
        'includes/domain-registry.php',
    );
    foreach ($platform_files as $platform_file) {
        gd_client_portal_safe_require(GD_CLIENT_PORTAL_PATH . $platform_file, 'platform_' . basename($platform_file, '.php'));
    }

    if (class_exists('GDCP_Module_Registry')) {
        $metadata = array(
            'core'=>array('version'=>'7.0.0'), 'tenants'=>array('version'=>'1.3.1','dependencies'=>array('core')),
            'projects'=>array('version'=>'1.5.0','dependencies'=>array('core','tenants')),
            'billing'=>array('version'=>'3.3.0','dependencies'=>array('core','tenants')),
            'documents'=>array('version'=>'3.5.0','dependencies'=>array('core','tenants','projects')),
            'support'=>array('version'=>'3.7.0','dependencies'=>array('core','tenants')),
            'automation'=>array('version'=>'4.9.0','dependencies'=>array('core','projects')),
            'lifecycle'=>array('version'=>'5.9.0','dependencies'=>array('core','projects','billing')),
            'inbox'=>array('version'=>'6.5.0','dependencies'=>array('core')),
            'domain-kernel'=>array('version'=>'7.6.0','dependencies'=>array('core','projects','tenants')),
            'security-kernel'=>array('version'=>'7.4.0','dependencies'=>array('core','tenants')),
        );
        foreach ($metadata as $slug => $args) gdcp_module_register($slug, $args);
    }

    if (class_exists('GDCP_Module_Registry') && method_exists('GDCP_Module_Registry','validate')) {
        $validation = GDCP_Module_Registry::validate();
        if (!empty($validation['errors'])) update_option('gdcp_module_architecture_errors', $validation['errors'], false);
    }
    if (class_exists('GDCP_Application_Kernel')) GDCP_Application_Kernel::phase('features');
    $files = array(
        'platform','platform-architecture','executive-repository','executive-service','assignment-repository','assignment-service','calendar-repository','calendar-service','domain-architecture','auth','dashboard','dashboard-sections','service-projects','approvals','delivery-repository','delivery-service','delivery','woo-automation','assignments','sla-repository','sla-service','sla','calendar','reporting','audit','communications','branding','onboarding','intake-builder','intake-repository','intake-service','requests','request-repository','request-service','automation-service','notification-repository','notification-service','collaboration-repository','collaboration-service','billing-repository','billing-service','billing','contract-repository','contract-service','message-repository','message-service','support-repository','support-service','payment-repository','payment-service','payments','contracts','document-repository','document-service','documents','collaboration','support','feedback-repository','feedback-service','feedback','executive','automation','smart-data','governance','studio','insights','reliability','event-intelligence','predictive','command-center','lifecycle','lifecycle-automation','communication-intelligence','experience-intelligence','experience-automation','operations','platform-control','unified-search-service','unified-search','inbox','admin'
    );
    foreach ($files as $file) gd_client_portal_safe_require(GD_CLIENT_PORTAL_PATH.'includes/'.$file.'.php', $file);

    // v7 domain consolidation: canonical implementations in includes/ take
    // precedence. Legacy modules remain available only as explicit opt-ins.
    if (class_exists('GDCP_Application_Kernel')) GDCP_Application_Kernel::phase('compatibility');
    if (function_exists('gdcp_boot_legacy_modules')) {
        gdcp_boot_legacy_modules();
    }
    if (class_exists('GDCP_Application_Kernel')) GDCP_Application_Kernel::finish();
}

gd_client_portal_bootstrap();

// Central v6.7 migration checkpoint. Feature-specific legacy installers remain
// compatible while new platform migrations use this versioned manager.
add_action('plugins_loaded', function () {
    if (class_exists('GDCP_Migration_Manager')) {
        GDCP_Migration_Manager::run();
    }
}, 19);

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

// v4.7 reliability heartbeat: every 5 minutes, with a safe fallback if the event already exists.
add_filter('cron_schedules', function($s){ if(!isset($s['gdcp_5min'])) $s['gdcp_5min']=array('interval'=>300,'display'=>'Every 5 minutes'); return $s; });
add_action('init', function(){ if(!wp_next_scheduled('gd_client_portal_automation_reliability_tick')) wp_schedule_event(time()+300,'gdcp_5min','gd_client_portal_automation_reliability_tick'); },40);
add_action('init', function(){ if(!wp_next_scheduled('gd_client_portal_security_audit_retention')) wp_schedule_event(time()+900,'daily','gd_client_portal_security_audit_retention'); },41);
add_action('init', function(){ if(!wp_next_scheduled('gd_client_portal_security_response_tick')) wp_schedule_event(time()+120,'gdcp_5min','gd_client_portal_security_response_tick'); if(!wp_next_scheduled('gd_client_portal_security_governance_tick')) wp_schedule_event(time()+180,'gdcp_5min','gd_client_portal_security_governance_tick'); },42);
add_action('gd_client_portal_security_audit_retention', function(){
    if(function_exists('gdcp_security_audit_repository')) {
        $days = absint(get_option('gdcp_security_audit_retention_days', 90));
        gdcp_security_audit_repository()->purge_older_than($days ?: 90);
    }
});


require_once GD_CLIENT_PORTAL_PATH . 'includes/release-governance.php';
require_once GD_CLIENT_PORTAL_PATH . 'includes/release-candidate.php';
require_once GD_CLIENT_PORTAL_PATH . 'includes/upgrade-rollback-readiness.php';
require_once GD_CLIENT_PORTAL_PATH . 'includes/deployment-lifecycle.php';
require_once GD_CLIENT_PORTAL_PATH . 'includes/security-audit-repository.php';
require_once GD_CLIENT_PORTAL_PATH . 'includes/security-incident-repository.php';
require_once GD_CLIENT_PORTAL_PATH . 'includes/security-response-automation.php';
require_once GD_CLIENT_PORTAL_PATH . 'includes/security-response-notifications.php';
require_once GD_CLIENT_PORTAL_PATH . 'includes/security-response-operations.php';
require_once GD_CLIENT_PORTAL_PATH . 'includes/security-incident-governance.php';
require_once GD_CLIENT_PORTAL_PATH . 'includes/security-control-center.php';
require_once GD_CLIENT_PORTAL_PATH . 'includes/security-response-forensics.php';
require_once GD_CLIENT_PORTAL_PATH . 'includes/production-observability.php';
require_once GD_CLIENT_PORTAL_PATH . 'includes/production-hardening.php';
require_once GD_CLIENT_PORTAL_PATH . 'includes/integration-test-readiness.php';
require_once GD_CLIENT_PORTAL_PATH . 'includes/product-health.php';
require_once GD_CLIENT_PORTAL_PATH . 'includes/admin-ux.php';
require_once GD_CLIENT_PORTAL_PATH . 'includes/admin-navigation.php';

require_once GD_CLIENT_PORTAL_PATH . 'includes/getting-started.php';


// Feature UX styles are loaded only when their shortcodes are present.
// This keeps the public frontend from paying the asset cost on unrelated pages.
function gdcp_page_has_shortcode($shortcodes) {
    if (is_admin() || !is_singular()) {
        return false;
    }
    global $post;
    if (!$post || !isset($post->post_content)) {
        return false;
    }
    foreach ((array) $shortcodes as $shortcode) {
        if (has_shortcode($post->post_content, $shortcode)) {
            return true;
        }
    }
    return false;
}

add_action('wp_enqueue_scripts', function () {
    if (gdcp_page_has_shortcode(array('gd_collaboration', 'gd_project_collaboration'))) {
        wp_enqueue_style(
            'gdcp-collaboration-approvals-ux',
            GD_CLIENT_PORTAL_URL . 'assets/collaboration-approvals-ux.css',
            array(),
            GD_CLIENT_PORTAL_VERSION
        );
    }
    if (gdcp_page_has_shortcode(array('gd_billing', 'gd_client_billing', 'gd_support', 'gd_project_completion'))) {
        wp_enqueue_style(
            'gdcp-billing-support-completion-ux',
            GD_CLIENT_PORTAL_URL . 'assets/billing-support-completion-ux.css',
            array(),
            GD_CLIENT_PORTAL_VERSION
        );
    }
}, 30);

require_once GD_CLIENT_PORTAL_PATH . 'includes/project-completion.php';
