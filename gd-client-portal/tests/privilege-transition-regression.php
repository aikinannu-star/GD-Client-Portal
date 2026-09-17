<?php
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }
$root=dirname(__DIR__);
$required=array(
 'app/Security/IdentityContext.php',
 'includes/identity-lifecycle.php',
 'app/Security/Authorization.php',
 'app/Services/TenantService.php',
 'app/Repositories/TenantRepository.php',
);
foreach($required as $f){ if(!file_exists($root.'/'.$f)) throw new Exception('Missing identity component: '.$f); }
$auth=file_get_contents($root.'/app/Security/Authorization.php');
$tenant=file_get_contents($root.'/app/Repositories/TenantRepository.php');
$service=file_get_contents($root.'/app/Services/TenantService.php');
$life=file_get_contents($root.'/includes/identity-lifecycle.php');
$ctx=file_get_contents($root.'/app/Security/IdentityContext.php');
$checks=array(
 'authorization resolves capabilities per request'=>strpos($auth,'current_user_can')!==false,
 'tenant assignment is re-resolved'=>strpos($tenant,'user_tenant')!==false && strpos($tenant,'exists($tenant_id,true)')!==false,
 'tenant access requires active tenant'=>strpos($service,'$this->get($id)!==null')!==false,
 'role changes invalidate identity cache'=>strpos($life,"set_user_role")!==false && strpos($life,'gdcp_flush_identity_cache')!==false,
 'role changes revoke existing sessions'=>strpos($life,'wp_destroy_all_sessions($user_id)')!==false,
 'tenant reassignment revokes existing sessions'=>strpos($life,'wp_destroy_all_sessions(absint($user_id))')!==false,
 'tenant meta changes invalidate identity cache'=>strpos($life,"gd_client_portal_tenant_id")!==false,
 'identity fingerprint includes roles capabilities tenant'=>strpos($ctx,"'roles'=>\$roles")!==false && strpos($ctx,"'caps'=>\$caps")!==false && strpos($ctx,"'tenant'=>\$tenant")!==false,
);
$pass=0; foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL').' - '.$name."\n"; if($ok)$pass++;}
echo "RESULT: {$pass}/".count($checks)." PASS\n";
if($pass!==count($checks)) exit(1);
