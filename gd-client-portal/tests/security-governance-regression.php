<?php
if (!defined('ABSPATH')) exit;
$checks=array();
$checks['governance file']=file_exists(GD_CLIENT_PORTAL_PATH.'includes/security-incident-governance.php');
$repo=function_exists('gdcp_security_incident_repository')?gdcp_security_incident_repository():null;
$checks['owner method']=$repo && method_exists($repo,'assign_owner');
$checks['governed action method']=$repo && method_exists($repo,'governed_action');
$checks['governance scheduler']=has_action('gd_client_portal_security_governance_tick','gdcp_security_incident_governance_tick')!==false;
$checks['config deadlines']=function_exists('gdcp_security_incident_governance_config') && gdcp_security_incident_governance_config()['ack_minutes']>0;
foreach($checks as $name=>$ok) echo ($ok?'PASS':'FAIL').' '.$name."\n";
return !in_array(false,$checks,true);
