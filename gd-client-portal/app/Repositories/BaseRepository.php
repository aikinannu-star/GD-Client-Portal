<?php
if (!defined('ABSPATH')) exit;

abstract class GDCP_Base_Repository {
    protected function db() { global $wpdb; return $wpdb; }
    protected function tenant_id($tenant_id = 0) {
        $tenant_id = absint($tenant_id);
        if (function_exists('gd_client_portal_is_platform_admin') && gd_client_portal_is_platform_admin()) return $tenant_id;
        if ($tenant_id > 0) {
            if (function_exists('gdcp_tenant_can_access') && !gdcp_tenant_can_access($tenant_id)) return 0;
            return $tenant_id;
        }
        return function_exists('gdcp_current_tenant_id') ? absint(gdcp_current_tenant_id()) : (function_exists('gd_client_portal_get_current_tenant_id') ? absint(gd_client_portal_get_current_tenant_id()) : 0);
    }
    protected function scoped_where($tenant_id = 0, $alias = '') {
        if (function_exists('gd_client_portal_is_platform_admin') && gd_client_portal_is_platform_admin()) return array('', array());
        $tenant_id = $this->tenant_id($tenant_id);
        if ($tenant_id <= 0) return array(' AND 1=0', array());
        $field = ($alias ? $alias.'.' : '').'tenant_id';
        return array(' AND '.$field.'=%d', array($tenant_id));
    }
    protected function prepare($sql, $args = array()) {
        return $args ? $this->db()->prepare($sql, $args) : $sql;
    }
}
