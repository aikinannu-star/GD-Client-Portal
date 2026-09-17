<?php
if (!defined('ABSPATH')) exit;

/** v7.0 canonical domain map and legacy compatibility gate. */
final class GDCP_Domain_Registry {
    private static $domains = array(
        'tenants'=>array('source'=>'includes','status'=>'canonical'),
        'projects'=>array('source'=>'includes','status'=>'canonical'),
        'approvals'=>array('source'=>'includes','status'=>'canonical'),
        'delivery'=>array('source'=>'includes','status'=>'canonical'),
        'billing'=>array('source'=>'includes','status'=>'canonical'),
        'payments'=>array('source'=>'includes','status'=>'canonical'),
        'contracts'=>array('source'=>'includes','status'=>'canonical'),
        'documents'=>array('source'=>'includes','status'=>'canonical'),
        'support'=>array('source'=>'includes','status'=>'canonical'),
        'feedback'=>array('source'=>'includes','status'=>'canonical'),
        'requests'=>array('source'=>'includes','status'=>'canonical'),
        'collaboration'=>array('source'=>'includes','status'=>'canonical'),
        'notifications'=>array('source'=>'includes','status'=>'canonical'),
        'automation'=>array('source'=>'includes','status'=>'canonical'),
        'lifecycle'=>array('source'=>'includes','status'=>'canonical'),
        'intake'=>array('source'=>'includes','status'=>'canonical'),
        'marketplace'=>array('source'=>'modules','status'=>'compatibility'),
        'account'=>array('source'=>'modules','status'=>'compatibility'),
        'meetings'=>array('source'=>'modules','status'=>'compatibility'),
    );
    public static function all(){ return self::$domains; }
    public static function is_canonical($slug){ return isset(self::$domains[$slug]) && self::$domains[$slug]['status']==='canonical'; }
    public static function legacy_allowed($slug){
        $enabled=get_option('gdcp_legacy_modules_enabled', array());
        return is_array($enabled) && in_array(sanitize_key($slug), $enabled, true);
    }
}
function gdcp_domain_registry(){ return GDCP_Domain_Registry::all(); }
function gdcp_boot_legacy_modules(){
    $dir=GD_CLIENT_PORTAL_PATH.'modules/';
    if(!is_dir($dir)) return;
    $legacy=get_option('gdcp_legacy_modules_enabled', array());
    $legacy=is_array($legacy)?$legacy:array();
    // Preserve the pre-v7 setting as an opt-in source of compatibility modules.
    $old=get_option('gd_client_portal_enabled_modules', array());
    if(is_array($old) && $old) $legacy=array_values(array_unique(array_merge($legacy,$old)));
    foreach($legacy as $slug){
        $slug=sanitize_key($slug);
        if(!$slug || GDCP_Domain_Registry::is_canonical($slug)) continue;
        $file=$dir.$slug.'/index.php';
        if(is_file($file)) gd_client_portal_safe_require($file,'legacy_'.$slug);
    }
}
