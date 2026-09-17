<?php
if(!defined('ABSPATH')) define('ABSPATH',__DIR__.'/');
$base=dirname(__DIR__);
require_once $base.'/includes/executive-repository.php';
require_once $base.'/includes/executive-service.php';
$checks=array();
$checks['repository_class']=class_exists('GDCP_Executive_Analytics_Repository');
$checks['service_class']=class_exists('GDCP_Executive_Analytics_Service');
$checks['executive_no_wpdb']=strpos((string)file_get_contents($base.'/includes/executive.php'),'$wpdb->')===false;
$checks['registry_mapping']=strpos((string)file_get_contents($base.'/app/Repositories/RepositoryRegistry.php'),"'executive'=>'GDCP_Executive_Analytics_Repository'")!==false;
$checks['financial_method']=method_exists('GDCP_Executive_Analytics_Repository','financial_metrics');
$checks['monthly_method']=method_exists('GDCP_Executive_Analytics_Repository','monthly_revenue');
foreach($checks as $k=>$v)echo ($v?'PASS':'FAIL')." $k\n";
if(in_array(false,$checks,true)) exit(1);
