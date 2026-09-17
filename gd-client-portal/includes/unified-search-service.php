<?php
/** Canonical cross-domain read model for unified search. */
if (!defined('ABSPATH')) exit;

class GDCP_Unified_Search_Service {
    private function table_exists($table){ global $wpdb; return $table && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table)) === $table; }
    private function scope_where($column='tenant_id'){
        $w=array('1=1');$p=array();
        if(!gd_client_portal_is_platform_admin()){$tenant=absint(gd_client_portal_get_current_tenant_id());if($tenant<=0)return array('0=1',array());$w[]="$column=%d";$p[]=$tenant;}
        return array(implode(' AND ',$w),$p);
    }
    public function search($q,$limit=8){
        global $wpdb;
        if(!is_user_logged_in()||!gd_client_portal_verify_request()||strlen($q)<2)return array();
        $like='%'.$wpdb->esc_like($q).'%';$limit=max(1,min(12,absint($limit)));$out=array();
        $add=function($type,$id,$title,$meta,$url='')use(&$out){$out[]=array('type'=>$type,'id'=>absint($id),'title'=>wp_strip_all_tags($title),'meta'=>wp_strip_all_tags($meta),'url'=>$url);};
        $pt=gd_client_portal_get_project_table_name();
        if($this->table_exists($pt)){
            $where='(title LIKE %s OR service_type LIKE %s OR status LIKE %s OR current_stage LIKE %s)';$args=array($like,$like,$like,$like);
            if(!gd_client_portal_is_platform_admin()){$tenant=absint(gd_client_portal_get_current_tenant_id());if($tenant<=0)$where='0=1';else{$where.=' AND tenant_id=%d';$args[]=$tenant;if(!gd_client_portal_user_is_tenant_admin()){$where.=' AND user_id=%d';$args[]=gd_client_portal_cached_current_user_id();}}}
            $rows=$wpdb->get_results($wpdb->prepare("SELECT id,title,status,current_stage,progress FROM $pt WHERE $where ORDER BY id DESC LIMIT $limit",$args));
            foreach((array)$rows as $r)$add('Project',$r->id,$r->title,'#'.$r->id.' · '.ucwords(str_replace('_',' ',$r->current_stage)).' · '.intval($r->progress).'%',add_query_arg(array('project_id'=>intval($r->id)),gd_client_portal_get_dashboard_url()));
        }
        $users=$wpdb->users;$um=$wpdb->usermeta;$uw='(u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)';$ua=array($like,$like,$like);
        if(!gd_client_portal_is_platform_admin()){$tenant=absint(gd_client_portal_get_current_tenant_id());if(gd_client_portal_user_is_tenant_admin()&&$tenant>0){$uw.=' AND EXISTS (SELECT 1 FROM '.$um." ux WHERE ux.user_id=u.ID AND ux.meta_key='gd_client_portal_tenant_id' AND ux.meta_value=%s)";$ua[]=(string)$tenant;}else{$uw.=' AND u.ID=%d';$ua[]=gd_client_portal_cached_current_user_id();}}
        $userrows=$wpdb->get_results($wpdb->prepare("SELECT u.ID,u.display_name,u.user_email FROM $users u WHERE $uw ORDER BY u.display_name ASC LIMIT $limit",$ua));foreach((array)$userrows as $u)$add('Client / User',$u->ID,$u->display_name,$u->user_email);
        $sources=array(
            array('Invoice','billing_invoices','invoice_number','description','status','project_id','gd_client_portal_billing_invoices_table'),array('Quote','billing_quotes','quote_number','title','status','project_id','gd_client_portal_billing_quotes_table'),array('Contract','contracts','contract_number','title','status','project_id','gd_client_portal_contracts_table'),array('Document','documents','title','file_name','status','project_id','gd_client_portal_documents_table'),array('Support Ticket','support_tickets','subject','category','status','project_id',function(){return gd_client_portal_support_table('tickets');}),array('Service Request','requests','title','description','status','project_id','gd_client_portal_requests_table'),array('Task','tasks','title','description','status','project_id',function(){return gd_client_portal_collab_table('project_tasks');})
        );
        foreach($sources as $s){$fn=$s[6];$t=is_callable($fn)?call_user_func($fn):(function_exists($fn)?call_user_func($fn):'');if(!$t||!$this->table_exists($t))continue;$w="({$s[2]} LIKE %s OR {$s[3]} LIKE %s OR {$s[4]} LIKE %s)";$a=array($like,$like,$like);if(!gd_client_portal_is_platform_admin()){$tenant=absint(gd_client_portal_get_current_tenant_id());if($tenant<=0)continue;$w.=' AND tenant_id=%d';$a[]=$tenant;if(!gd_client_portal_user_is_tenant_admin()&&in_array($s[1],array('documents','billing_invoices','billing_quotes','contracts','support_tickets','requests'),true)){$w.=' AND user_id=%d';$a[]=gd_client_portal_cached_current_user_id();}}$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE $w ORDER BY id DESC LIMIT $limit",$a));foreach((array)$rows as $r){$title=$r->{$s[2]};$meta='#'.$r->id.' · '.ucwords(str_replace('_',' ',$r->status));$add($s[0],$r->id,$title,$meta);}}
        usort($out,function($a,$b){return strcmp($a['type'],$b['type'])?:($b['id']<=>$a['id']);});return array_slice($out,0,$limit*4);
    }
}
function gdcp_unified_search_service(){static $s;if(!$s)$s=new GDCP_Unified_Search_Service();return $s;}
