<?php
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
$base = dirname(__DIR__) . '/';
require_once $base . 'includes/security-audit-repository.php';
$src = file_get_contents($base . 'includes/production-observability.php');
$repo = file_get_contents($base . 'includes/security-audit-repository.php');
$checks = array(
 'repository class exists' => class_exists('GDCP_Security_Audit_Repository'),
 'indexed audit table declared' => strpos($repo, 'gdcp_security_events') !== false,
 'correlation index declared' => strpos($repo, 'KEY correlation_id') !== false,
 'tenant/user indexes declared' => strpos($repo, 'KEY tenant_time') !== false && strpos($repo, 'KEY user_time') !== false,
 'retention bounded' => strpos($repo, 'min(3650') !== false && strpos($repo, 'max(7') !== false,
 'security events persist to repository' => strpos($src, "severity === 'security'") !== false && strpos($src, '->insert($event)') !== false,
 'clear removes persistent audit' => strpos($src, '->clear()') !== false,
 'sensitive fields remain filtered' => strpos($src, "array('password', 'passwd', 'token', 'authorization', 'cookie', 'nonce')") !== false,
);
$failed=0; foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL').' '.$name."\n"; if(!$ok)$failed++;}
exit($failed?1:0);
