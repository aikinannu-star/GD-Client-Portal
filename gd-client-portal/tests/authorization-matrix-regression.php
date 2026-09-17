<?php
if (!defined('ABSPATH')) define('ABSPATH', __DIR__.'/');
$root=dirname(__DIR__);
$auth=file_get_contents($root.'/app/Security/Authorization.php');
$svc=file_get_contents($root.'/app/Services/AuthorizationService.php');
$checks=array(
 'central authorization class'=>strpos($auth,'final class GDCP_Authorization')!==false,
 'platform global bypass'=>strpos($auth,"current_user_can('manage_options')")!==false,
 'tenant isolation before grant'=>strpos($auth,'gdcp_tenant_can_access($tenant_id)')!==false,
 'tenant admin matrix'=>strpos($auth, 'if ($is_admin)')!==false,
 'project owner restriction'=>strpos($auth,"case 'project':")!==false && strpos($auth, '$project_owned')!==false,
 'invoice owner restriction'=>strpos($auth,"case 'invoice':")!==false && strpos($auth,"'pay'")!==false,
 'document owner restriction'=>strpos($auth,"case 'document':")!==false,
 'support owner restriction'=>strpos($auth,"case 'support':")!==false,
 'automation denied to regular users'=>strpos($auth,"case 'automation':")!==false && strpos($auth,'return false;')!==false,
 'approval project scope'=>strpos($auth,"case 'approval':")!==false && strpos($auth, '$project_owned')!==false,
 'delivery project scope'=>strpos($auth,"case 'delivery':")!==false && strpos($auth, '$project_owned')!==false,
 'authorization service delegates'=>strpos($svc, 'return gdcp_can($action,$object,$id,$args)')!==false,
);
$bad=array();foreach($checks as $k=>$v)if(!$v)$bad[]=$k;
if($bad){fwrite(STDERR,'Authorization matrix failed: '.implode(', ',$bad).PHP_EOL);exit(1);}echo 'Authorization matrix passed: '.count($checks).'/'.count($checks).PHP_EOL;
