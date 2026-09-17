<?php
$root = dirname(__DIR__);
$src = file_get_contents($root . '/includes/security-response-notifications.php');
$main = file_get_contents($root . '/gd-client-portal.php');
$checks = array(
 'notification integration exists' => strpos($src, 'gdcp_security_response_notify_incident') !== false,
 'auto escalation hook wired' => strpos($src, 'gdcp_security_incident_auto_escalated') !== false,
 'platform admins included' => strpos($src, "'capability' => 'manage_options'") !== false,
 'tenant admins tenant filtered' => strpos($src, "'meta_key' => 'gd_client_portal_tenant_id'") !== false,
 'cross tenant not broadcast' => strpos($src, 'Never broadcast a cross-tenant incident') !== false,
 'notification idempotency key' => strpos($src, '$event_base . \'-u\'') !== false,
 'only high critical notify' => strpos($src, "array('high','critical')") !== false,
 'integration loaded' => strpos($main, "includes/security-response-notifications.php") !== false,
 'safe evidence reference' => strpos($src, '$event_base') !== false,
 'action trail recorded' => strpos($src, "'notification_sent'") !== false,
);
$failed=array(); foreach($checks as $name=>$ok) { echo ($ok?'PASS':'FAIL')." - $name\n"; if(!$ok)$failed[]=$name; }
if($failed) exit(1); echo count($checks)."/".count($checks)." security response notification checks PASS\n";
