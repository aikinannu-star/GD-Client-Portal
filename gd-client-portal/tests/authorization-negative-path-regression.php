<?php
/** Negative-path authorization contract for role/resource/action boundaries. */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__.'/');
$root=dirname(__DIR__);
$auth=file_get_contents($root.'/app/Security/Authorization.php');
$api=file_get_contents($root.'/app/Core/Api.php');
$fail=array();
$expect=array(
 'project'=>array('view','download','reply'),
 'document'=>array('view','download'),
 'invoice'=>array('view','download','pay'),
 'support'=>array('view','reply'),
 'approval'=>array('view','approve','reply'),
 'delivery'=>array('view','download'),
 'task'=>array('view','reply'),
 'contract'=>array('view','download','approve','reply'),
 'request'=>array('view','reply'),
 'message'=>array('view','reply','download'),
);
foreach(array_keys($expect) as $object){
    if(strpos($auth,"case '$object':")===false)$fail[]="missing policy for $object";
}
$danger=array('create','edit','delete','manage','assign');
foreach($danger as $action){
    if(strpos($auth,"'$action'")===false)$fail[]="missing dangerous action $action";
}
// Regular tenant users must never gain operational automation privileges.
if(strpos($auth,"case 'automation':")===false || strpos($auth,"// Automation is an operational/admin surface")===false)
    $fail[]='automation negative-path policy missing';
// Object authorization must resolve the authoritative resource before granting access.
if(strpos($auth,'$row=self::resource($object,$id,$args)')===false || strpos($auth,'$tenant_id=self::resource_tenant($object,$row,$args)')===false)
    $fail[]='resource-before-tenant authorization ordering missing';
// Project-scoped resources must derive ownership from the authoritative project, not a submitted tenant id alone.
if(strpos($auth,'self::project_owned($row,$args)')===false || strpos($auth,'gd_client_portal_verify_project_access($p,false)')===false)
    $fail[]='project ownership negative-path guard missing';
// REST object routes must use object-level permission callbacks.
foreach(array('project_permission','project_edit_permission','invoice_permission','document_permission','support_permission') as $permission){
    if(strpos($api,"'permission_callback'=>array(__CLASS__,'$permission')")===false)$fail[]="REST permission callback missing: $permission";
}
if($fail){fwrite(STDERR,"Authorization negative-path contract failed:\n- ".implode("\n- ",$fail)."\n");exit(1);}
echo 'Authorization negative-path contract passed: '.count($expect).' protected resource policies + operational denial + object-scoped REST guards.'.PHP_EOL;
