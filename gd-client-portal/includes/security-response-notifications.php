<?php
/** Security incident notification integration. */
if (!defined('ABSPATH')) exit;

function gdcp_security_response_notification_recipients($tenant_id = 0) {
    $ids = array();
    $tenant_id = absint($tenant_id);

    // Platform administrators receive cross-tenant incidents and are always
    // eligible for global security response notifications.
    $admins = get_users(array('number' => -1, 'fields' => 'ID', 'capability' => 'manage_options'));
    foreach ((array) $admins as $uid) $ids[] = absint($uid);

    // A tenant-specific incident is additionally visible only to tenant admins
    // belonging to that same tenant. Never broadcast a cross-tenant incident to
    // individual tenant administrators.
    if ($tenant_id > 0) {
        $users = get_users(array('number' => -1, 'fields' => 'ID', 'meta_key' => 'gd_client_portal_tenant_id', 'meta_value' => $tenant_id));
        foreach ((array) $users as $uid) {
            $uid = absint($uid);
            if ($uid && user_can($uid, 'gd_client_portal_tenant_admin')) $ids[] = $uid;
        }
    }

    return array_values(array_unique(array_filter($ids)));
}

function gdcp_security_response_notify_incident($incident_id, $context = array()) {
    if (!function_exists('gdcp_security_incident_repository') || !function_exists('gd_client_portal_create_notification')) return 0;
    $incident = gdcp_security_incident_repository()->find(absint($incident_id));
    if (!$incident) return 0;

    $severity = sanitize_key($incident->severity);
    $title = sprintf(__('Security incident #%d: %s','gd-client-portal'), absint($incident->id), $incident->title);
    $body = sprintf(__('A %s-severity security incident was automatically classified. Review Production Observability for the incident and its integrity-protected evidence trail.','gd-client-portal'), strtoupper($severity));
    $event_base = 'security-incident-' . absint($incident->id) . '-notification';
    $sent = 0;
    foreach (gdcp_security_response_notification_recipients($incident->tenant_id) as $uid) {
        $key = $event_base . '-u' . absint($uid);
        $id = gd_client_portal_create_notification($uid, $title, $body, 'security_incident', 0, absint($incident->tenant_id), $key);
        if ($id) $sent++;
    }

    if ($sent && function_exists('gdcp_security_incident_repository')) {
        gdcp_security_incident_repository()->add_action(
            $incident->id,
            'notification_sent',
            sprintf('Security response notification delivered to %d authorized administrator recipient(s).', $sent),
            $event_base
        );
    }
    return $sent;
}

function gdcp_security_response_handle_auto_escalated($incident_id, $context = array()) {
    $severity = sanitize_key($context['severity'] ?? '');
    if (!in_array($severity, array('high','critical'), true)) return 0;
    return gdcp_security_response_notify_incident($incident_id, $context);
}
add_action('gdcp_security_incident_auto_escalated', 'gdcp_security_response_handle_auto_escalated', 20, 2);
