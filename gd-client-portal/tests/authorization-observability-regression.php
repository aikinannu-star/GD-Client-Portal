<?php

$auth = file_get_contents(dirname(__DIR__) . '/app/Security/Authorization.php');
$obs  = file_get_contents(dirname(__DIR__) . '/includes/production-observability.php');
$ok = true;

$checks = array(
    'authorization wrapper records denials' => strpos($auth, 'gd_client_portal_security_audit_denial') !== false,
    'denial event has correlation id' => strpos($obs, "'correlation_id'") !== false,
    'denial event uses security severity' => strpos($obs, "'security'") !== false,
    'request payload is not logged' => strpos($obs, 'never log payloads') !== false,
    'denials are bounded per request' => strpos($obs, 'static $seen = array();') !== false,
    'sensitive fields remain filtered' => strpos($obs, "'authorization', 'cookie', 'nonce'") !== false,
);
foreach ($checks as $label => $pass) { echo ($pass ? 'PASS' : 'FAIL') . " - {$label}\n"; if (!$pass) $ok = false; }
exit($ok ? 0 : 1);
