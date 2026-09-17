<?php
if (!defined('ABSPATH')) exit;

/** Central resource authorization matrix for the v7.6 application layer. */
final class GDCP_Authorization {
    private static $actions=array('view','create','edit','delete','manage','assign','approve','reply','pay','download');

    public static function can($action,$object,$id=0,$args=array()) {
        $allowed = self::evaluate($action,$object,$id,$args);
        if (!$allowed && function_exists('gd_client_portal_security_audit_denial')) {
            gd_client_portal_security_audit_denial($action,$object,$id,$args);
        }
        return $allowed;
    }

    private static function evaluate($action,$object,$id=0,$args=array()) {
        $action=sanitize_key($action); $object=sanitize_key($object); $id=absint($id); $args=is_array($args)?$args:array();
        if (!is_user_logged_in() || !current_user_can('read') || !in_array($action,self::$actions,true)) return false;

        // Platform administrators are global by definition.
        if (current_user_can('manage_options')) return true;

        $is_admin=function_exists('gd_client_portal_user_is_tenant_admin') && gd_client_portal_user_is_tenant_admin();

        // Tenant-scoped resources: resolve the authoritative row before granting access.
        $row=self::resource($object,$id,$args);
        $tenant_id=self::resource_tenant($object,$row,$args);
        if ($tenant_id<=0 || !gdcp_tenant_can_access($tenant_id)) return false;

        // Tenant administrators can manage resources inside their own tenant.
        if ($is_admin) {
            return in_array($action,array('view','create','edit','delete','manage','assign','approve','reply','pay','download'),true);
        }

        // Regular tenant users/clients are deliberately narrower and ownership-aware.
        $owned=self::owned($object,$row,$args);
        $project_owned=self::project_owned($row,$args);

        switch ($object) {
            case 'project':
                return $project_owned && in_array($action,array('view','download','reply'),true);
            case 'document':
                return $owned && in_array($action,array('view','download'),true);
            case 'invoice':
                return $owned && in_array($action,array('view','download','pay'),true);
            case 'support':
                return $owned && in_array($action,array('view','reply'),true);
            case 'approval':
                return $project_owned && in_array($action,array('view','approve','reply'),true);
            case 'delivery':
                return $project_owned && in_array($action,array('view','download'),true);
            case 'task':
                return $project_owned && in_array($action,array('view','reply'),true);
            case 'tenant':
                // Tenant users never receive tenant-management capabilities.
                return $action==='view' && absint($args['user_tenant_id']??0)===absint($tenant_id);
            case 'automation':
                // Automation is an operational/admin surface, not a client surface.
                return false;
            case 'contract':
                return $project_owned && in_array($action,array('view','download','approve','reply'),true);
            case 'request':
                return $owned && in_array($action,array('view','reply'),true);
            case 'message':
                return $project_owned && in_array($action,array('view','reply','download'),true);
            default:
                return false;
        }
    }

    private static function resource($object,$id,$args) {
        if (!$id) return null;
        switch ($object) {
            case 'project':
                return function_exists('gd_client_portal_get_project_by_id') ? gd_client_portal_get_project_by_id($id) : null;
            case 'invoice':
                $r=gdcp_repository('billing'); return $r ? $r->invoice($id) : null;
            case 'document':
                $r=gdcp_repository('document'); return $r ? $r->find($id) : null;
            case 'support':
                $r=gdcp_repository('support'); return $r ? $r->ticket($id) : null;
            case 'automation':
                $r=gdcp_repository('automation'); return $r ? $r->find($id) : null;
            case 'delivery':
                $r=function_exists('gdcp_delivery_repository')?gdcp_delivery_repository():null; return $r ? $r->find($id) : null;
            case 'task':
                $r=function_exists('gdcp_collaboration_repository')?gdcp_collaboration_repository():null; return $r ? $r->find_task($id,absint($args['project_id']??0)) : null;
            case 'approval':
                $r=gdcp_repository('approval'); $project_id=absint($args['project_id']??0); return ($r&&$project_id) ? $r->find_active_for_project($project_id,0,false) : null;
            case 'contract':
                $r=function_exists('gdcp_contract_repository')?gdcp_contract_repository():null; return $r ? $r->find($id) : null;
            case 'request':
                $r=function_exists('gdcp_request_repository')?gdcp_request_repository():null; return $r ? $r->find($id) : null;
            case 'message':
                $r=function_exists('gdcp_message_repository')?gdcp_message_repository():null; return $r ? $r->find($id) : null;
            case 'tenant':
                $r=gdcp_repository('tenant'); return $r ? $r->find($id) : null;
        }
        return null;
    }

    private static function resource_tenant($object,$row,$args) {
        if ($object==='tenant') return $row ? absint(is_array($row)?($row['id']??0):($row->id??0)) : absint($args['tenant_id']??0);
        if ($row && isset($row->tenant_id)) return absint($row->tenant_id);
        if (is_array($row) && isset($row['tenant_id'])) return absint($row['tenant_id']);
        if (!empty($args['tenant_id'])) return absint($args['tenant_id']);
        if (!empty($args['project_id']) && function_exists('gd_client_portal_get_project_by_id')) {
            $p=gd_client_portal_get_project_by_id(absint($args['project_id'])); return $p ? absint($p->tenant_id) : 0;
        }
        return 0;
    }

    private static function owned($object,$row,$args) {
        $uid=gd_client_portal_cached_current_user_id();
        if (!$row) return false;
        foreach (array('user_id','client_id','created_by') as $field) {
            $value=is_array($row)?($row[$field]??0):($row->$field??0);
            if ($value && absint($value)===$uid) return true;
        }
        return false;
    }

    private static function project_owned($row,$args) {
        if ($row && isset($row->project_id) && absint($row->project_id)>0) $project_id=absint($row->project_id);
        elseif (is_array($row) && !empty($row['project_id'])) $project_id=absint($row['project_id']);
        else $project_id=absint($args['project_id']??0);
        if (!$project_id || !function_exists('gd_client_portal_get_project_by_id')) return false;
        $p=gd_client_portal_get_project_by_id($project_id);
        return $p ? gd_client_portal_verify_project_access($p,false) : false;
    }
}
function gdcp_can($action,$object,$id=0,$args=array()){ return GDCP_Authorization::can($action,$object,$id,$args); }
