<?php
/** Operational controls for security incident lifecycle notifications. */
if (!defined('ABSPATH')) exit;

function gdcp_security_response_operational_recipients($tenant_id = 0) {
    if (function_exists('gdcp_security_response_notification_recipients')) {
        return gdcp_security_response_notification_recipients(absint($tenant_id));
    }
    return array();
}

function gdcp_security_response_notify_state_change($incident_id, $action_type, $status) {
    if (!function_exists('gdcp_security_incident_repository') || !function_exists('gd_client_portal_create_notification')) return 0;
    $incident = gdcp_security_incident_repository()->find(absint($incident_id));
    if (!$incident) return 0;
    $action_type = sanitize_key($action_type);
    if (!in_array($action_type, array('acknowledged', 'resolved', 'reopened'), true)) return 0;
    $status = sanitize_key($status);
    $labels = array('acknowledged' => 'acknowledged', 'resolved' => 'resolved', 'reopened' => 'reopened');
    $label = $labels[$action_type];
    $title = sprintf(__('Security incident #%d %s', 'gd-client-portal'), absint($incident->id), $label);
    $body = sprintf(__('Security incident #%d (%s) is now %s. Review Production Observability for the current status and integrity-protected action trail.', 'gd-client-portal'), absint($incident->id), strtoupper(sanitize_key($incident->severity)), $label);
    $event_base = 'security-incident-' . absint($incident->id) . '-state-' . $action_type;
    $sent = 0;
    foreach (gdcp_security_response_operational_recipients($incident->tenant_id) as $uid) {
        $key = $event_base . '-u' . absint($uid);
        $nid = gd_client_portal_create_notification($uid, $title, $body, 'security_incident', 0, absint($incident->tenant_id), $key);
        if ($nid) $sent++;
    }
    if ($sent) {
        gdcp_security_incident_repository()->add_action($incident->id, 'state_notification_sent', sprintf('Lifecycle notification delivered to %d authorized administrator recipient(s) for %s state.', $sent, $label), $event_base);
    }
    return $sent;
}

function gdcp_security_response_handle_incident_action($incident_id, $action_type, $context = array()) {
    return gdcp_security_response_notify_state_change($incident_id, $action_type, $context['status'] ?? '');
}
add_action('gdcp_security_incident_action_recorded', 'gdcp_security_response_handle_incident_action', 20, 3);
