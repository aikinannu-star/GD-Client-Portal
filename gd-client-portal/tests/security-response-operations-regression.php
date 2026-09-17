<?php
$root = dirname(__DIR__);
$src = file_get_contents($root . '/includes/security-response-operations.php');
$repo = file_get_contents($root . '/includes/security-incident-repository.php');
$main = file_get_contents($root . '/gd-client-portal.php');
$checks = array(
 'operations integration exists' => strpos($src, 'gdcp_security_response_notify_state_change') !== false,
 'acknowledged lifecycle covered' => strpos($src, "'acknowledged', 'resolved', 'reopened'") !== false,
 'tenant safe recipients reused' => strpos($src, 'gdcp_security_response_notification_recipients') !== false,
 'notification idempotency key' => strpos($src, 'event_base .') !== false && strpos($src, 'action_type') !== false,
 'action trail recorded' => strpos($src, "'state_notification_sent'") !== false,
 'repository action hook' => strpos($repo, 'gdcp_security_incident_action_recorded') !== false,
 'only lifecycle states trigger hook' => strpos($repo, "array('acknowledged','resolved','reopened')") !== false,
 'status passed to integration' => strpos($repo, "'status' => \$new_status") !== false,
 'operations loaded' => strpos($main, "includes/security-response-operations.php") !== false,
 'no destructive controls' => strpos($src, 'wp_destroy_current_session') === false && strpos($src, 'update_user_meta') === false,
);
$failed=array(); foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL')." - $name\n"; if(!$ok)$failed[]=$name;} if($failed)exit(1); echo count($checks).'/'.count($checks).' security response operations checks PASS\n';
