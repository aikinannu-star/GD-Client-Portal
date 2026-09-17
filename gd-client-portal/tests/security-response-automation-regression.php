<?php
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
$root = dirname(__DIR__);
$src = file_get_contents($root . '/includes/security-response-automation.php');
$main = file_get_contents($root . '/gd-client-portal.php');
$checks = array(
 'response automation exists' => strpos($src, 'gdcp_security_response_tick') !== false,
 'bounded 15 minute window' => strpos($src, "'window_minutes' => 15") !== false,
 'high threshold' => strpos($src, "'high_denials' => 5") !== false,
 'critical threshold' => strpos($src, "'critical_denials' => 10") !== false,
 'cross-tenant critical threshold' => strpos($src, "'critical_tenants' => 3") !== false,
 'authorization-only source events' => strpos($src, "'type' => 'authorization_denied'") !== false,
 'deterministic incident key' => strpos($src, "auth-burst-") !== false,
 'safe evidence reference' => strpos($src, "'evidence_ref' => " . '$' . "evidence") !== false,
 'automated action recorded' => strpos($src, "'auto_escalated'") !== false,
 'no destructive account action' => strpos($src, 'wp_destroy_current_session') === false && strpos($src, 'wp_set_auth_cookie') === false && strpos($src, 'update_user_meta') === false,
 'scheduled response tick' => strpos($main, 'gd_client_portal_security_response_tick') !== false,
);
$ok=true; foreach($checks as $n=>$v){echo ($v?'PASS ':'FAIL ').$n."\n";$ok=$ok&&$v;} exit($ok?0:1);
