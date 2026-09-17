<?php
if (!defined('ABSPATH')) exit;
/** Cross-domain read boundary contract for automation analytics. */
function gdcp_cross_domain_read_boundary_regression(){
    $root=dirname(__DIR__); $checks=array();
    $repo=file_get_contents($root.'/app/Repositories/AutomationRepository.php');
    $service=file_get_contents($root.'/includes/automation-service.php');
    $insights=file_get_contents($root.'/includes/insights.php');
    $events=file_get_contents($root.'/includes/event-intelligence.php');
    $checks['automation repository exposes workflow analytics']=$repo!==false && strpos($repo,'function analytics(')!==false;
    $checks['automation repository exposes event analytics']=$repo!==false && strpos($repo,'function event_analytics(')!==false;
    $checks['automation service exposes workflow analytics']=$service!==false && strpos($service,'analytics($days=30)')!==false;
    $checks['automation service exposes event analytics']=$service!==false && strpos($service,'event_analytics($days=30)')!==false;
    $checks['workflow insights uses automation service']=$insights!==false && strpos($insights,'gdcp_automation_service()->analytics(30)')!==false && strpos($insights,'$wpdb->')===false;
    $checks['event intelligence uses automation service']=$events!==false && strpos($events,'gdcp_automation_service()->event_analytics($days)')!==false && strpos($events,'$wpdb->')===false;
    $passed=0; foreach($checks as $name=>$ok){if($ok)$passed++;echo ($ok?'PASS':'FAIL').' - '.$name."\n";}
    echo "Cross-domain read boundary contract: {$passed}/".count($checks)."\n"; return $passed===count($checks);
}
if(defined('GDCP_RUN_CROSS_DOMAIN_READ_BOUNDARY_REGRESSION')&&GDCP_RUN_CROSS_DOMAIN_READ_BOUNDARY_REGRESSION)gdcp_cross_domain_read_boundary_regression();
