<?php
if (!defined('ABSPATH')) exit;

/**
 * Canonical project persistence boundary.
 *
 * This repository owns project reads. Legacy helper functions in
 * includes/service-projects.php delegate here for backward compatibility.
 */
final class GDCP_Project_Repository extends GDCP_Base_Repository {
    public function table(){ global $wpdb; return $wpdb->prefix.'gd_projects'; }

    /**
     * Conditional project update used by state transitions. The expected
     * current stage is part of the WHERE clause so concurrent requests cannot
     * both observe the same stage and emit duplicate transition events.
     */
    public function transition_fields($id,$expected_stage,$data){
        $id=absint($id); $expected_stage=sanitize_key($expected_stage);
        if(!$id || $expected_stage==='' || !is_array($data)) return false;
        $clean=array(); $formats=array();
        $allowed=array('current_stage','progress','status','updated_at');
        foreach($allowed as $field){
            if(!array_key_exists($field,$data)) continue;
            $value=$data[$field];
            if($field==='progress'){ $clean[$field]=max(0,min(100,absint($value))); $formats[]='%d'; }
            elseif($field==='updated_at'){ $clean[$field]=sanitize_text_field($value); $formats[]='%s'; }
            else { $clean[$field]=sanitize_text_field($value); $formats[]='%s'; }
        }
        if(!$clean) return false;
        return $this->db()->update($this->table(),$clean,array('id'=>$id,'current_stage'=>$expected_stage),$formats,array('%d','%s'));
    }

    /** Canonical project persistence boundary. Only approved project fields may be written here. */
    public function update_fields($id,$data){
        $id=absint($id); if(!$id || !is_array($data)) return false;
        $allowed=array('title','description','status','current_stage','progress','file_url','tenant_id','user_id','product_id','service_type');
        $clean=array(); $formats=array();
        foreach($allowed as $field){
            if(!array_key_exists($field,$data)) continue;
            $value=$data[$field];
            if(in_array($field,array('title','status','current_stage','service_type'),true)){ $clean[$field]=sanitize_text_field($value); $formats[]='%s'; }
            elseif($field==='description'){ $clean[$field]=sanitize_textarea_field($value); $formats[]='%s'; }
            elseif($field==='file_url'){ $clean[$field]=esc_url_raw($value); $formats[]='%s'; }
            else { $clean[$field]=absint($value); $formats[]='%d'; }
        }
        if(!$clean) return false;
        return $this->db()->update($this->table(),$clean,array('id'=>$id),$formats,array('%d'))!==false;
    }

    /** Create a project through the canonical persistence boundary. */
    public function create($data){
        if(!is_array($data)) return 0;
        $allowed=array('tenant_id','order_id','user_id','product_id','service_type','title','description','status','current_stage','progress','file_url','created_at');
        $clean=array(); $formats=array();
        foreach($allowed as $field){
            if(!array_key_exists($field,$data)) continue;
            $value=$data[$field];
            if(in_array($field,array('service_type','title','status','current_stage'),true)){ $clean[$field]=sanitize_text_field($value); $formats[]='%s'; }
            elseif(in_array($field,array('description','file_url'),true)){ $clean[$field]=$field==='file_url'?esc_url_raw($value):sanitize_textarea_field($value); $formats[]='%s'; }
            elseif($field==='created_at'){ $clean[$field]=sanitize_text_field($value); $formats[]='%s'; }
            else { $clean[$field]=absint($value); $formats[]='%d'; }
        }
        if(!$clean) return 0;
        if(!isset($clean['created_at'])){ $clean['created_at']=current_time('mysql'); $formats[]='%s'; }
        if(false===$this->db()->insert($this->table(),$clean,$formats)) return 0;
        return absint($this->db()->insert_id);
    }

    /** Find the first project associated with a WooCommerce order. */
    public function find_by_order_id($order_id){
        $order_id=absint($order_id); if(!$order_id) return null;
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->table().' WHERE order_id=%d ORDER BY id ASC LIMIT 1',$order_id));
    }

    public function find($id){
        $id=absint($id); if(!$id) return null;
        static $cache=array();
        if(array_key_exists($id,$cache)) return $cache[$id];
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->table().' WHERE id=%d LIMIT 1',$id));
        $cache[$id]=$row ?: null;
        return $cache[$id];
    }

    /** Fetch a bounded set of projects in one query, preserving input order. */
    public function find_many($ids){
        $ids=array_values(array_unique(array_filter(array_map('absint',(array)$ids))));
        if(!$ids) return array();
        $ids=array_slice($ids,0,500);
        static $cache=array();
        $missing=array(); $out=array();
        foreach($ids as $id){
            if(array_key_exists($id,$cache)){
                if($cache[$id]) $out[$id]=$cache[$id];
            } else {
                $missing[]=$id;
            }
        }
        if($missing){
            global $wpdb;
            $placeholders=implode(',',array_fill(0,count($missing),'%d'));
            $sql='SELECT * FROM '.$this->table().' WHERE id IN ('.$placeholders.')';
            $rows=$wpdb->get_results($wpdb->prepare($sql,$missing));
            foreach($missing as $id) $cache[$id]=null;
            foreach((array)$rows as $row){
                $id=absint($row->id); $cache[$id]=$row; $out[$id]=$row;
            }
        }
        return $out;
    }

    public function accessible($id){
        $project=$this->find($id);
        if(!$project) return null;
        if(function_exists('gd_client_portal_verify_project_access') && gd_client_portal_verify_project_access($project,true)) return $project;
        return null;
    }

    /**
     * Projects visible to the current portal user.
     * Platform admins see all; tenant admins see their tenant; regular users
     * see projects assigned to their account within their tenant.
     */
    public function visible($limit=200,$status='',$tenant_id=0,$user_id=null){
        global $wpdb;
        $sql='SELECT * FROM '.$this->table().' WHERE 1=1'; $args=array();
        if($status!==''){ $sql.=' AND status=%s'; $args[]=sanitize_key($status); }
        $platform=function_exists('gd_client_portal_is_platform_admin') && gd_client_portal_is_platform_admin();
        if(!$platform){
            $tenant=$this->tenant_id($tenant_id);
            if($tenant<=0) return array();
            $sql.=' AND tenant_id=%d'; $args[]=$tenant;
            $is_tenant_admin=function_exists('gd_client_portal_user_is_tenant_admin') && gd_client_portal_user_is_tenant_admin();
            if(!$is_tenant_admin){
                $uid=$user_id===null ? (function_exists('gd_client_portal_cached_current_user_id') ? gd_client_portal_cached_current_user_id() : get_current_user_id()) : absint($user_id);
                if($uid<=0) return array();
                $sql.=' AND user_id=%d'; $args[]=$uid;
            }
        }
        $sql.=' ORDER BY id DESC LIMIT %d'; $args[]=max(1,min(500,absint($limit)));
        return $wpdb->get_results($wpdb->prepare($sql,$args));
    }
}
