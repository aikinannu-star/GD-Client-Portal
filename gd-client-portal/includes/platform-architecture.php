<?php
if (!defined('ABSPATH')) exit;
function gdcp_platform_architecture_page(){
    if(!function_exists('gd_client_portal_user_can_access_admin') || !gd_client_portal_user_can_access_admin()) wp_die('Access denied.');
    $mods=class_exists('GDCP_Module_Registry') ? GDCP_Module_Registry::all() : array();
    echo '<div class="wrap"><h1>GD Client Portal — Platform Architecture</h1><p>v7.2 architecture foundation: module registry, centralized migration checkpoint, event bus, authorization facade, repositories and services.</p>';
    echo '<h2>Registered Domains</h2><table class="widefat striped" style="max-width:1000px"><thead><tr><th>Domain</th><th>Version</th><th>Dependencies</th><th>Status</th></tr></thead><tbody>';
    foreach($mods as $slug=>$m){$deps=implode(', ',(array)($m['dependencies']??array()));$ok=class_exists('GDCP_Module_Registry')&&GDCP_Module_Registry::bootable($slug);echo '<tr><td><strong>'.esc_html($slug).'</strong></td><td>'.esc_html($m['version']??'').'</td><td>'.esc_html($deps?:'—').'</td><td>'.($ok?'<span style="color:#16803a;font-weight:700">READY</span>':'<span style="color:#b32d2e;font-weight:700">DEPENDENCY GAP</span>').'</td></tr>';}
    echo '</tbody></table>';
    if(class_exists('GDCP_Application_Kernel')){
        $k=GDCP_Application_Kernel::status();
        echo '<h2>Application Lifecycle</h2><p>Phase: <strong>'.esc_html($k['phase']).'</strong> · Loaded modules/files: <strong>'.absint($k['loaded']).'</strong> · Recoverable bootstrap failures: <strong>'.absint($k['failures']).'</strong></p>';
        if(!empty($k['timings'])){ $parts=array(); foreach($k['timings'] as $phase=>$ms) $parts[]=esc_html($phase).': '.esc_html($ms).' ms'; echo '<p>Phase timings: '.implode(' · ',$parts).'</p>'; }
    }
    $module_validation=class_exists('GDCP_Module_Registry')&&method_exists('GDCP_Module_Registry','validate')?GDCP_Module_Registry::validate():array('valid'=>true,'errors'=>array());
    if(!$module_validation['valid']) echo '<div class="notice notice-warning inline"><p><strong>Module graph warnings:</strong> '.esc_html(implode(' | ',(array)$module_validation['errors'])).'</p></div>';
    echo '<h2>Tenant Security</h2><p>Security kernel: <strong>'.esc_html(get_option('gdcp_tenant_security_version','not migrated')).'</strong><br>Policy: <strong>'.esc_html(get_option('gdcp_tenant_security_policy','not set')).'</strong><br>Current context: <strong>'.esc_html(function_exists('gdcp_current_tenant_id')?gdcp_current_tenant_id():0).'</strong></p><h2>Event Bus</h2><p>Central event bus: <strong>'.esc_html(get_option('gdcp_event_bus_version','not migrated')).'</strong><br>Policy: <strong>'.esc_html(get_option('gdcp_event_bus_policy','not set')).'</strong></p><h2>Repository Layer</h2><p>Runtime repository policy: <strong>'.esc_html(get_option('gdcp_repository_policy','not migrated')).'</strong><br>Repository runtime version: <strong>'.esc_html(get_option('gdcp_repository_runtime_version','not migrated')).'</strong></p><h2>Migration</h2><p>Central schema checkpoint: <strong>'.esc_html(class_exists('GDCP_Migration_Manager')?GDCP_Migration_Manager::version():0).'</strong></p>';
    $err=get_option('gdcp_migration_error',array()); if($err) echo '<div class="notice notice-warning inline"><p>Last migration issue: '.esc_html($err['message']??'Unknown error').'</p></div>';
    echo '</div>';
}
add_action('admin_menu',function(){ add_submenu_page('gd-client-portal','Platform Architecture','Platform Architecture','gd_client_portal_access_admin','gd-client-portal-platform-architecture','gdcp_platform_architecture_page'); },26);

// v7.5 API availability helper; route registration lives in app/Core/Api.php.
if (!function_exists('gdcp_api_version')) { function gdcp_api_version(){ return '1.0'; } }
