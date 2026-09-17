<?php
if (!defined('ABSPATH')) exit;
final class GDCP_Tenant_Repository extends GDCP_Base_Repository {
    public function all($active_only = false) {
        $tenants = get_option('gd_client_portal_tenants', array());
        if (!is_array($tenants)) $tenants = array();
        if (empty($tenants)) {
            $default = absint(get_option('gd_client_portal_default_tenant_id', 0));
            if ($default > 0) $tenants = array(array('id'=>$default,'name'=>__('Default tenant','gd-client-portal'),'slug'=>'default-tenant'));
        }
        $out=array();
        foreach ($tenants as $tenant) {
            if (!is_array($tenant)) continue;
            $id=absint($tenant['id']??0); $name=sanitize_text_field($tenant['name']??'');
            if ($id<=0 || $name==='') continue;
            $status=in_array(($tenant['status']??'active'),array('active','inactive'),true)?$tenant['status']:'active';
            if ($active_only && $status!=='active') continue;
            $out[$id]=array('id'=>$id,'name'=>$name,'slug'=>sanitize_title($tenant['slug']??$name),'status'=>$status,
                'logo_url'=>esc_url_raw($tenant['logo_url']??''),'primary_contact'=>sanitize_text_field($tenant['primary_contact']??''),
                'email'=>sanitize_email($tenant['email']??''),'phone'=>sanitize_text_field($tenant['phone']??''),
                'address'=>sanitize_textarea_field($tenant['address']??''),'created_at'=>sanitize_text_field($tenant['created_at']??''),
                'primary_color'=>sanitize_hex_color($tenant['primary_color']??'') ?: '#2563eb',
                'accent_color'=>sanitize_hex_color($tenant['accent_color']??'') ?: '#0f172a',
                'portal_title'=>sanitize_text_field($tenant['portal_title']??''),'portal_welcome'=>sanitize_textarea_field($tenant['portal_welcome']??''),
                'button_label'=>sanitize_text_field($tenant['button_label']??''));
        }
        if (function_exists('gd_client_portal_is_platform_admin') && gd_client_portal_is_platform_admin()) return $out;
        $id=$this->tenant_id(); return ($id>0 && isset($out[$id])) ? array($id=>$out[$id]) : array();
    }
    public function find($id) { $id=absint($id); $all=$this->all(); return $id>0 && isset($all[$id])?$all[$id]:null; }
    public function exists($id,$active_only=false){ $row=$this->find($id); return (bool)$row && (!$active_only || ($row['status']??'active')==='active'); }
    public function user_tenant($user_id){
        $user_id=absint($user_id);
        if($user_id<=0 || !get_userdata($user_id)) return 0;
        // Platform administrators are global identities, never tenant-scoped.
        if(function_exists('gd_client_portal_is_platform_admin') && gd_client_portal_is_platform_admin($user_id)) return 0;
        $tenant_id=absint(get_user_meta($user_id,'gd_client_portal_tenant_id',true));
        // Stale/deleted/inactive tenant assignments fail closed.
        if($tenant_id<=0 || !$this->exists($tenant_id,true)) return 0;
        return $tenant_id;
    }
    public function set_user_tenant($user_id,$tenant_id){
        $user_id=absint($user_id); $tenant_id=absint($tenant_id); if($user_id<=0 || !get_userdata($user_id)) return false;
        if(function_exists('gd_client_portal_is_platform_admin') && gd_client_portal_is_platform_admin($user_id)) return $tenant_id===0 ? (bool)delete_user_meta($user_id,'gd_client_portal_tenant_id') : false;
        if($tenant_id>0 && !$this->exists($tenant_id,true)) return false;
        if($tenant_id>0) return (bool)update_user_meta($user_id,'gd_client_portal_tenant_id',$tenant_id);
        return (bool)delete_user_meta($user_id,'gd_client_portal_tenant_id');
    }
}
