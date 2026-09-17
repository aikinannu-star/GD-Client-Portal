<?php
/** Static contract: onboarding persistence belongs to the onboarding repository/service boundary. */
if (!defined('ABSPATH')) exit;
function gdcp_onboarding_domain_boundary_contract(){
    $file=GD_CLIENT_PORTAL_PATH.'includes/onboarding.php';
    $src=is_readable($file)?file_get_contents($file):'';
    if($src==='') return false;
    $needles=array(
        '$wpdb->insert(gd_client_portal_onboarding_table()',
        '$wpdb->update(gd_client_portal_onboarding_table()',
        '$wpdb->insert(gd_client_portal_onboarding_documents_table()',
        '$wpdb->delete(gd_client_portal_onboarding_table()',
        '$wpdb->delete(gd_client_portal_onboarding_documents_table()'
    );
    foreach($needles as $needle){ if(strpos($src,$needle)!==false) return false; }
    return function_exists('gdcp_service') && class_exists('GDCP_Onboarding_Service') && class_exists('GDCP_Onboarding_Repository');
}
