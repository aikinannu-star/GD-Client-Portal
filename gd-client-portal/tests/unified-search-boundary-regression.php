<?php
if (!defined('ABSPATH')) define('ABSPATH', dirname(__DIR__).'/');
$service=file_get_contents(dirname(__DIR__).'/includes/unified-search-service.php');
$entry=file_get_contents(dirname(__DIR__).'/includes/unified-search.php');
$checks=array(
 'canonical search service exists'=>strpos($service,'class GDCP_Unified_Search_Service')!==false,
 'search entry point delegates to service'=>strpos($entry,'gdcp_unified_search_service()->search($q,$limit)')!==false,
 'legacy search implementation removed from controller'=>substr_count($entry,'$wpdb->get_results')===0,
 'service is bootstrapped before controller'=>strpos(file_get_contents(dirname(__DIR__).'/gd-client-portal.php'),"'unified-search-service','unified-search'")!==false,
 'tenant scoping retained'=>strpos($service,"tenant_id=%d")!==false,
 'client project scoping retained'=>strpos($service,"user_id=%d")!==false,
);
$ok=0;foreach($checks as $name=>$pass){echo ($pass?'PASS':'FAIL').' — '.$name."\n";if($pass)$ok++;}
exit($ok===count($checks)?0:1);
