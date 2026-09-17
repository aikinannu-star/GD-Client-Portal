<?php
if (!defined('ABSPATH')) define('ABSPATH', __DIR__.'/');
$root=dirname(__DIR__);
$checks=array();
$checks['project repository has order lookup']=strpos(file_get_contents($root.'/app/Repositories/ProjectRepository.php'),'function find_by_order_id')!==false;
$checks['project service exposes order lookup']=strpos(file_get_contents($root.'/app/Services/ProjectService.php'),'function get_by_order_id')!==false;
$checks['legacy order lookup delegates to project service']=strpos(file_get_contents($root.'/includes/service-projects.php'),"gdcp_service('project')")!==false;
$billing=file_get_contents($root.'/includes/billing-service.php');
$checks['billing service does not query project table directly']=strpos($billing,"gd_projects")===false && strpos($billing,'SELECT id FROM $projects')===false;
$experience=file_get_contents($root.'/includes/experience-automation.php');
$checks['experience automation does not query feedback table directly']=strpos($experience,'gd_client_portal_feedback_table')===false && strpos($experience,'SELECT * FROM')===false;
foreach($checks as $name=>$ok) echo ($ok?'PASS':'FAIL')." — $name\n";
$failed=array_filter($checks,fn($v)=>!$v); exit($failed?1:0);
