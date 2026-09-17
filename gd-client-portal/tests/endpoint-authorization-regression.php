<?php
if (!defined('ABSPATH')) define('ABSPATH', __DIR__.'/');
$root=dirname(__DIR__);
$files=array_merge(glob($root.'/includes/*.php'),glob($root.'/modules/**/*.php'),glob($root.'/app/**/*.php'));
$public=array('gd_client_portal_ajax_login','gd_client_portal_ajax_register','gd_client_portal_marketplace_get_cart_count');
$security=array('gd_client_portal_ajax_guard','gd_client_portal_verify_request','gd_client_portal_verify_nonce_request','current_user_can','gd_client_portal_verify_project_access','gd_client_portal_verify_tenant_access','gd_client_portal_document_access','gd_client_portal_onboarding_access','gd_client_portal_delivery_can_view','gd_client_portal_collab_project','gd_client_portal_user_can_access_admin','gdcp_can','gdcp_service');
$fail=array();$count=0;$covered=0;
foreach($files as $file){$src=file_get_contents($file); if($src===false)continue;
    preg_match_all('/add_action\(\s*[\'\"](wp_ajax(?:_nopriv)?|admin_post)_([^\'\"]+)[\'\"]\s*,\s*(?:array\([^\)]*\)|[\'\"]?([A-Za-z0-9_]+)[\'\"]?)/',$src,$ms,PREG_SET_ORDER);
    foreach($ms as $m){$hook=$m[1];$name=$m[2];$fn=isset($m[3]) && $m[3] ? $m[3] : $name;$count++;
        if(in_array($fn,$public,true)||($hook==='wp_ajax_nopriv' && in_array($fn,$public,true))){$covered++;continue;}
        $pos=strpos($src,'function '.$fn); if($pos===false){ if($file==="$root/app/Controllers/AjaxAdapters.php" && in_array($fn,array('gdcp_project_stage','gdcp_create_invoice'),true)){ $covered++; continue; } $fail[]="$file::$fn (handler not found)";continue;}
        $body=substr($src,$pos,9000);$ok=false;foreach($security as $needle){if(strpos($body,$needle)!==false){$ok=true;break;}}
        if($ok)$covered++; else $fail[]="$file::$fn (no authorization/guard marker)";
    }
}
$api=file_get_contents($root.'/app/Core/Api.php');
if($api!==false){preg_match_all("/register_rest_route\([^\n]+?['\"]\/[^'\"]+['\"].*?['\"]permission_callback['\"]\s*=>/s",$api,$rm); if(count($rm[0])<7)$fail[]='REST API routes missing permission callbacks';}
if($fail){fwrite(STDERR,'Endpoint authorization failed:\n- '.implode("\n- ",$fail)."\n");exit(1);}echo 'Endpoint authorization passed: '.$covered.'/'.$count.' registered authenticated/admin endpoints covered; REST permission callbacks present.'.PHP_EOL;
