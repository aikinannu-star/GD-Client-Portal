<?php
if (!defined('ABSPATH')) exit;

/** Regression contract: approval persistence must remain behind ApprovalRepository. */
function gdcp_approval_domain_boundary_contract(){
    $files=glob(GD_CLIENT_PORTAL_PATH.'includes/*.php');
    $violations=array();
    foreach((array)$files as $file){
        $text=file_get_contents($file);
        if(basename($file)==='approvals.php') continue;
        if(stripos($text,'gd_project_approvals')!==false && preg_match('/\$wpdb->(?:insert|update|delete)\(|\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\b/i',$text)) $violations[]=basename($file);
    }
    return $violations;
}
