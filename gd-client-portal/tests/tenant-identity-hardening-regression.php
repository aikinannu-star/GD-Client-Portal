<?php
if (!defined('ABSPATH')) exit;
$repo = function_exists('gdcp_repository') ? gdcp_repository('tenant') : null;
$service = function_exists('gdcp_service') ? gdcp_service('tenant') : null;
$pass = 0; $fail = 0;
$check = function($ok,$label) use (&$pass,&$fail){ echo ($ok?'PASS':'FAIL')." - {$label}\n"; $ok?$pass++:$fail++; };
$check($repo && $service, 'Tenant repository/service available');
if ($repo && $service) {
    $check(method_exists($repo,'user_tenant') && method_exists($repo,'set_user_tenant'), 'Authoritative identity methods available');
    $check($repo->user_tenant(0) === 0, 'Invalid user id fails closed');
    $check($repo->user_tenant(PHP_INT_MAX) === 0, 'Deleted/nonexistent user fails closed');
    $check(is_array($service->all(false)), 'Tenant listing resolves through service');
    $check($service->get(0) === null, 'Invalid tenant id returns no tenant');
    $check($repo->set_user_tenant(0,0) === false, 'Invalid assignment target is rejected');
    $check($repo->set_user_tenant(PHP_INT_MAX,1) === false, 'Assignment to nonexistent user is rejected');
}
echo "Tenant identity hardening: {$pass}/".($pass+$fail)." PASS\n";
