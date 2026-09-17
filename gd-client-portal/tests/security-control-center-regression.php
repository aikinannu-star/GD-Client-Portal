<?php
/** Static contract for Security Response Control Center. */
$root = dirname(__DIR__);
$repo = file_get_contents($root . '/includes/security-incident-repository.php');
$ui = file_get_contents($root . '/includes/security-control-center.php');
$boot = file_get_contents($root . '/gd-client-portal.php');
$checks = array(
 'ui_loaded' => strpos($boot, "includes/security-control-center.php") !== false,
 'access_guard' => strpos($ui, 'gdcp_security_control_center_can_access') !== false,
 'incident_scope' => strpos($ui, 'list_for_user') !== false,
 'governed_state' => strpos($ui, 'governed_action') !== false,
 'governed_owner' => strpos($ui, 'governed_assign_owner') !== false,
 'tenant_owner_check' => strpos($repo, 'gd_client_portal_get_user_tenant_id($owner_user_id)') !== false,
 'integrity_visible' => strpos($ui, 'verify_actions') !== false,
 'history_visible' => strpos($ui, 'actions($inc->id') !== false,
 'nonce' => strpos($ui, "check_admin_referer('gdcp_security_control_center')") !== false,
 'global_admin_scope' => strpos($repo, "user_can(\$user_id, 'manage_options')") !== false || strpos($repo, "manage_options") !== false,
);
$fail=0; foreach($checks as $k=>$v){echo ($v?'PASS':'FAIL')." $k\n"; if(!$v)$fail++;} exit($fail?1:0);
