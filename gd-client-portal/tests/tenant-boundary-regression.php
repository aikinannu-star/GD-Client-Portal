<?php
if (!defined('ABSPATH')) define('ABSPATH', __DIR__.'/');
$root=dirname(__DIR__);
$repo=file_get_contents($root.'/app/Repositories/TenantRepository.php');
$svc=file_get_contents($root.'/app/Services/TenantService.php');
$helpers=file_get_contents($root.'/includes/helpers.php');
$context=file_get_contents($root.'/app/Security/TenantContext.php');
$checks=array(
 'tenant repository exists'=>strpos($repo,'final class GDCP_Tenant_Repository')!==false,
 'tenant service exists'=>strpos($svc,'final class GDCP_Tenant_Service')!==false,
 'tenant registry mapping'=>strpos(file_get_contents($root.'/app/Repositories/RepositoryRegistry.php'),"'tenant'=>'GDCP_Tenant_Repository'")!==false,
 'current tenant delegates to service'=>strpos($helpers,"gdcp_service('tenant')->current_id")!==false,
 'tenant writes delegate to service'=>strpos($helpers,'set_user_tenant($user_id, $tenant_id)')!==false && strpos($helpers,'$service->set_user_tenant')!==false,
 'tenant access checks context'=>strpos($context,'function gdcp_tenant_can_access')!==false,
 'repository validates tenant before assignment'=>strpos($repo,'if($tenant_id>0 && !$this->exists($tenant_id,true)) return false;')!==false,
 'platform remains global'=>strpos($svc,'gd_client_portal_is_platform_admin')!==false
);
$bad=array(); foreach($checks as $k=>$v)if(!$v)$bad[]=$k; if($bad){fwrite(STDERR,'Tenant boundary failed: '.implode(', ',$bad).PHP_EOL);exit(1);} echo 'Tenant boundary passed: '.count($checks).'/'.count($checks).PHP_EOL;
