<?php
/**
 * Persistent security audit repository.
 * Stores bounded, indexed authorization/security events separately from the
 * operational option store so security investigations remain queryable.
 */
if (!defined('ABSPATH')) exit;

final class GDCP_Security_Audit_Repository {
    public function table() { global $wpdb; return $wpdb->prefix . 'gdcp_security_events'; }

    public function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        $t = $this->table();
        $sql = "CREATE TABLE {$t} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_time datetime NOT NULL,
            correlation_id varchar(40) NOT NULL DEFAULT '',
            type varchar(60) NOT NULL DEFAULT 'authorization_denied',
            severity varchar(20) NOT NULL DEFAULT 'security',
            action varchar(40) NOT NULL DEFAULT '',
            object_type varchar(60) NOT NULL DEFAULT '',
            object_id bigint(20) unsigned NOT NULL DEFAULT 0,
            tenant_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            project_id bigint(20) unsigned NOT NULL DEFAULT 0,
            request_method varchar(10) NOT NULL DEFAULT '',
            is_ajax tinyint(1) unsigned NOT NULL DEFAULT 0,
            message varchar(255) NOT NULL DEFAULT '',
            previous_hash char(64) NOT NULL DEFAULT '',
            event_hash char(64) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            KEY event_time(event_time),
            KEY correlation_id(correlation_id),
            KEY type(type),
            KEY action(action),
            KEY object_lookup(object_type,object_id),
            KEY tenant_time(tenant_id,event_time),
            KEY user_time(user_id,event_time),
            KEY project_time(project_id,event_time),
            KEY event_hash(event_hash)
        ) {$c};";
        dbDelta($sql);
        return empty($wpdb->last_error);
    }

    public function insert($event) {
        global $wpdb;
        $event = is_array($event) ? $event : array();
        $context = isset($event['context']) && is_array($event['context']) ? $event['context'] : array();
        $data = array(
            'event_time' => isset($event['time']) ? sanitize_text_field($event['time']) : current_time('mysql'),
            'correlation_id' => sanitize_key($context['correlation_id'] ?? ''),
            'type' => sanitize_key($event['type'] ?? 'security_event'),
            'severity' => sanitize_key($event['severity'] ?? 'security'),
            'action' => sanitize_key($context['action'] ?? ''),
            'object_type' => sanitize_key($context['object'] ?? ''),
            'object_id' => absint($context['object_id'] ?? 0),
            'tenant_id' => absint($context['tenant_id'] ?? 0),
            'user_id' => absint($context['user_id'] ?? 0),
            'project_id' => absint($context['project_id'] ?? 0),
            'request_method' => sanitize_key($context['request_method'] ?? ''),
            'is_ajax' => !empty($context['ajax']) ? 1 : 0,
            'message' => sanitize_text_field($event['message'] ?? ''),
        );
        // Append-only integrity chain. This does not make the database immutable,
        // but makes silent historical alteration detectable during verification.
        $previous_hash = (string) $wpdb->get_var('SELECT event_hash FROM ' . $this->table() . ' ORDER BY id DESC LIMIT 1');
        $canonical = wp_json_encode(array(
            'event_time' => $data['event_time'], 'correlation_id' => $data['correlation_id'],
            'type' => $data['type'], 'severity' => $data['severity'], 'action' => $data['action'],
            'object_type' => $data['object_type'], 'object_id' => $data['object_id'],
            'tenant_id' => $data['tenant_id'], 'user_id' => $data['user_id'],
            'project_id' => $data['project_id'], 'request_method' => $data['request_method'],
            'is_ajax' => $data['is_ajax'], 'message' => $data['message'], 'previous_hash' => $previous_hash,
        ));
        $event_hash = hash_hmac('sha256', (string) $canonical, wp_salt('auth'));
        $data['previous_hash'] = $previous_hash;
        $data['event_hash'] = $event_hash;
        $ok = $wpdb->insert($this->table(), $data, array('%s','%s','%s','%s','%s','%s','%d','%d','%d','%d','%s','%d','%s','%s','%s'));
        return $ok ? absint($wpdb->insert_id) : false;
    }

    public function recent($limit = 100, $filters = array()) {
        global $wpdb;
        $limit = min(500, max(1, absint($limit)));
        $where = array('1=1'); $args = array();
        if (!empty($filters['correlation_id'])) { $where[] = 'correlation_id = %s'; $args[] = sanitize_key($filters['correlation_id']); }
        if (!empty($filters['user_id'])) { $where[] = 'user_id = %d'; $args[] = absint($filters['user_id']); }
        if (!empty($filters['tenant_id'])) { $where[] = 'tenant_id = %d'; $args[] = absint($filters['tenant_id']); }
        if (!empty($filters['object_type'])) { $where[] = 'object_type = %s'; $args[] = sanitize_key($filters['object_type']); }
        if (!empty($filters['severity'])) { $where[] = 'severity = %s'; $args[] = sanitize_key($filters['severity']); }
        if (!empty($filters['action'])) { $where[] = 'action = %s'; $args[] = sanitize_key($filters['action']); }
        if (!empty($filters['type'])) { $where[] = 'type = %s'; $args[] = sanitize_key($filters['type']); }
        if (!empty($filters['from'])) { $where[] = 'event_time >= %s'; $args[] = sanitize_text_field($filters['from']); }
        if (!empty($filters['to'])) { $where[] = 'event_time <= %s'; $args[] = sanitize_text_field($filters['to']); }
        $sql = 'SELECT * FROM ' . $this->table() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . $limit;
        return $args ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);
    }

    public function investigate($filters = array(), $limit = 100) {
        return $this->recent($limit, $filters);
    }

    public function integrity_health($limit = 500) {
        return $this->verify_chain($limit);
    }

    public function count_recent($hours = 24) {
        global $wpdb;
        $hours = max(1, min(8760, absint($hours)));
        return absint($wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $this->table() . ' WHERE event_time >= %s', gmdate('Y-m-d H:i:s', time() - ($hours * HOUR_IN_SECONDS)))));
    }

    public function purge_older_than($days = 90) {
        global $wpdb;
        $days = max(7, min(3650, absint($days)));
        return $wpdb->query($wpdb->prepare('DELETE FROM ' . $this->table() . ' WHERE event_time < %s', gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS))));
    }


    public function verify_chain($limit = 500) {
        global $wpdb;
        $limit = min(5000, max(1, absint($limit)));
        $rows = $wpdb->get_results('SELECT * FROM ' . $this->table() . ' ORDER BY id ASC LIMIT ' . $limit);
        $previous = '';
        foreach ((array) $rows as $row) {
            $canonical = wp_json_encode(array(
                'event_time' => $row->event_time, 'correlation_id' => $row->correlation_id,
                'type' => $row->type, 'severity' => $row->severity, 'action' => $row->action,
                'object_type' => $row->object_type, 'object_id' => (int) $row->object_id,
                'tenant_id' => (int) $row->tenant_id, 'user_id' => (int) $row->user_id,
                'project_id' => (int) $row->project_id, 'request_method' => $row->request_method,
                'is_ajax' => (int) $row->is_ajax, 'message' => $row->message, 'previous_hash' => $previous,
            ));
            $expected = hash_hmac('sha256', (string) $canonical, wp_salt('auth'));
            if (!hash_equals((string) $row->event_hash, $expected) || (string) $row->previous_hash !== $previous) {
                return array('ok' => false, 'checked' => count($rows), 'bad_id' => absint($row->id));
            }
            $previous = (string) $row->event_hash;
        }
        return array('ok' => true, 'checked' => count($rows), 'bad_id' => 0);
    }

    public function clear() {
        global $wpdb;
        return $wpdb->query('TRUNCATE TABLE ' . $this->table());
    }
}
function gdcp_security_audit_repository() { static $r = null; if ($r === null) $r = new GDCP_Security_Audit_Repository(); return $r; }
