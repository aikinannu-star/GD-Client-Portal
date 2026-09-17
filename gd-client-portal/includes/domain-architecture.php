<?php
if (!defined('ABSPATH')) exit;
function gd_client_portal_domain_architecture_page(){
    if(!function_exists('gd_client_portal_user_can_access_admin') || !gd_client_portal_user_can_access_admin()) wp_die(esc_html__('You are not allowed to access this page.','gd-client-portal'));
    $domains=function_exists('gdcp_domain_registry')?gdcp_domain_registry():array();
    $legacy=get_option('gdcp_legacy_modules_enabled',array());
    echo '<div class="wrap"><h1>Domain Architecture</h1><p>v7 uses canonical domain implementations first. Legacy modules are compatibility-only and opt-in.</p><table class="widefat striped"><thead><tr><th>Domain</th><th>Source</th><th>Status</th></tr></thead><tbody>';
    foreach($domains as $slug=>$d) echo '<tr><td><strong>'.esc_html($slug).'</strong></td><td>'.esc_html($d['source']).'</td><td>'.esc_html($d['status']).'</td></tr>';
    echo '</tbody></table><h2>Legacy compatibility</h2><p>'.esc_html($legacy?'Enabled: '.implode(', ',$legacy):'No legacy modules enabled').'</p></div>';
}
add_action('admin_menu',function(){ add_submenu_page('gd-client-portal','Domain Architecture','Domain Architecture','gd_client_portal_access_admin','gdcp-domain-architecture','gd_client_portal_domain_architecture_page'); },30);
