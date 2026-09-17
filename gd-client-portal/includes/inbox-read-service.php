<?php
if (!defined('ABSPATH')) exit;

/** Cross-domain read model for the portal inbox. Persistence remains owned by each domain repository. */
if (!class_exists('GDCP_Inbox_Read_Service')):
final class GDCP_Inbox_Read_Service {
    public function support($uid,$tenant,$tenant_admin,$platform,$limit){
        if (!class_exists('GDCP_Support_Repository')) return array();
        $repo=new GDCP_Support_Repository(); $where=array(); $args=array();
        if (!$platform) { if ($tenant<=0) return array(); $where[]='tenant_id=%d'; $args[]=$tenant; if (!$tenant_admin){$where[]='user_id=%d';$args[]=$uid;} }
        $where[]="status NOT IN ('closed')"; return $repo->list_tickets($where,$args,$limit);
    }
    public function invoices($uid,$tenant,$tenant_admin,$platform,$limit){
        if (!class_exists('GDCP_Billing_Repository')) return array();
        $repo=new GDCP_Billing_Repository(); return $repo->list_outstanding_for_scope($uid,$tenant,$tenant_admin,$platform,$limit);
    }
    public function automation($tenant,$platform,$limit){
        if (!function_exists('gdcp_automation_service')) return array(); return gdcp_automation_service()->list_queue_for_scope(array('failed','running'),$tenant,$platform,$limit);
    }
    public function approvals($uid,$tenant,$tenant_admin,$platform,$limit){
        if (!function_exists('gdcp_approval_service')) return array(); $rows=gdcp_approval_service()->pending_for_tenant($platform?0:$tenant,$limit*2); if($platform||$tenant_admin)return $rows;
        $out=array(); foreach($rows as $row) if(absint($row->requested_by)===absint($uid)) $out[]=$row; return array_slice($out,0,$limit);
    }
}
endif;
function gdcp_inbox_read_service(){static $s;if(!$s)$s=new GDCP_Inbox_Read_Service();return $s;}
