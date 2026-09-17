<?php
/** Static contract for bounded security analytics and forensics. */
$root=dirname(__DIR__); $src=file_get_contents($root.'/includes/security-response-forensics.php'); $boot=file_get_contents($root.'/gd-client-portal.php');
$checks=array(
 'bootstrap loaded'=>strpos($boot,'includes/security-response-forensics.php')!==false,
 'tenant authorization'=>strpos($src,'gdcp_security_forensics_scope')!==false,
 'audit repository boundary'=>strpos($src,'gdcp_security_audit_repository()')!==false,
 'summary metrics'=>strpos($src,'COUNT(DISTINCT user_id)')!==false,
 'timeline analytics'=>strpos($src,'gdcp_security_forensics_timeline')!==false,
 'correlation analysis'=>strpos($src,"breakdown('correlation_id'")!==false,
 'bounded limits'=>strpos($src,'min(200, max(1, absint($limit)))')!==false,
 'csv export'=>strpos($src,'Content-Type: text/csv')!==false,
 'nonce protected export'=>strpos($src,"check_admin_referer('gdcp_security_forensics_export')")!==false,
 'no client role access'=>strpos($src,"gd_client_portal_tenant_admin")!==false,
);
$fail=0;foreach($checks as $k=>$v){echo ($v?'PASS':'FAIL')." $k\n";if(!$v)$fail++;}exit($fail?1:0);
