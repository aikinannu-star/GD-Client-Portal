<?php
/** Static contract: Support persistence is owned by the Support repository/service boundary. */
if(!defined('ABSPATH')) exit;
function gdcp_support_domain_boundary_contract(){
    $files=array(
        GD_CLIENT_PORTAL_PATH.'includes/support.php',
        GD_CLIENT_PORTAL_PATH.'includes/inbox.php',
        GD_CLIENT_PORTAL_PATH.'includes/command-center.php'
    );
    foreach($files as $file){
        $src=is_readable($file)?file_get_contents($file):'';
        if($src==='') return false;
        if(preg_match('/\$wpdb\s*->\s*(?:insert|update|delete)\s*\(\s*gd_client_portal_support_table\s*\(/i',$src)) return false;
    }
    return class_exists('GDCP_Support_Service') && class_exists('GDCP_Support_Repository');
}
