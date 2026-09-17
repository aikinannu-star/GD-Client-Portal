<?php
if (!defined('ABSPATH')) exit;

final class GDCP_Module_Registry {
    private static $modules = array();
    public static function register($slug, $args = array()) {
        $slug = sanitize_key($slug); if (!$slug) return false;
        self::$modules[$slug] = wp_parse_args($args, array('version'=>'1.0.0','dependencies'=>array(),'status'=>'active'));
        return true;
    }
    public static function all() { return self::$modules; }
    public static function get($slug) { return self::$modules[sanitize_key($slug)] ?? null; }
    public static function bootable($slug) {
        $m=self::get($slug); if (!$m) return false;
        foreach ((array)$m['dependencies'] as $dep) if (!self::get($dep)) return false;
        return true;
    }

    /** Validate the module graph without changing boot order. */
    public static function validate() {
        $errors=array(); $states=array();
        foreach(self::$modules as $slug=>$module){
            foreach((array)($module['dependencies']??array()) as $dep){
                if(!self::get($dep)) $errors[]=$slug.': missing dependency '.$dep;
            }
        }
        $visit=function($slug,$path=array()) use (&$visit,&$states,&$errors){
            if(($states[$slug]??'')==='done') return;
            if(($states[$slug]??'')==='visiting'){ $errors[]='dependency cycle: '.implode(' -> ',array_merge($path,array($slug))); return; }
            $states[$slug]='visiting'; $module=self::get($slug);
            foreach((array)($module['dependencies']??array()) as $dep) if(self::get($dep)) $visit($dep,array_merge($path,array($slug)));
            $states[$slug]='done';
        };
        foreach(array_keys(self::$modules) as $slug) $visit($slug);
        return array('valid'=>empty($errors),'errors'=>array_values(array_unique($errors)),'count'=>count(self::$modules));
    }
}
if (!function_exists('gdcp_module_register')) { function gdcp_module_register($slug,$args=array()){ return GDCP_Module_Registry::register($slug,$args); } }
if (!function_exists('gdcp_module_registry')) { function gdcp_module_registry(){ return GDCP_Module_Registry::all(); } }
