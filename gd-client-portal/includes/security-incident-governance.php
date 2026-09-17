<?php
/** Security incident governance: ownership, authorization, and escalation deadlines. */
if (!defined('ABSPATH')) exit;

function gdcp_security_incident_governance_config() {
    return array('ack_minutes' => 30, 'resolve_minutes' => 240, 'stale_minutes' => 60);
}

function gdcp_security_incident_can_manage($incident, $user_id = 0) {
    $user_id = absint($user_id ?: get_current_user_id());
    if (!$user_id || !$incident) return false;
    if (user_can($user_id, 'manage_options')) return true;
    $tenant_id = absint($incident->tenant_id ?? 0);
    if (!$tenant_id || !function_exists('gd_client_portal_get_user_tenant_id')) return false;
    if (absint(gd_client_portal_get_user_tenant_id($user_id)) !== $tenant_id) return false;
    return user_can($user_id, 'gd_client_portal_tenant_admin');
}

function gdcp_security_incident_governance_due_at($severity, $kind = 'ack', $from = 0) {
    $cfg = gdcp_security_incident_governance_config();
    $minutes = $kind === 'resolve' ? $cfg['resolve_minutes'] : $cfg['ack_minutes'];
    if ($severity === 'critical') $minutes = max(10, (int) floor($minutes / 2));
    return gmdate('Y-m-d H:i:s', ($from ?: time()) + ($minutes * MINUTE_IN_SECONDS));
}

function gdcp_security_incident_governance_tick() {
    if (!function_exists('gdcp_security_incident_repository')) return 0;
    $repo = gdcp_security_incident_repository();
    $rows = $repo->list(array('status' => 'open'), 200);
    $now = time(); $changed = 0;
    foreach ((array) $rows as $incident) {
        $created = strtotime((string) $incident->created_at);
        if (!$created) continue;
        $cfg = gdcp_security_incident_governance_config();
        $ack_due = strtotime(gdcp_security_incident_governance_due_at($incident->severity, 'ack', $created));
        if ($now >= $ack_due && $now < ($ack_due + MINUTE_IN_SECONDS * 10)) {
            $repo->add_action($incident->id, 'escalation_due', 'Incident has reached its acknowledgement deadline and requires administrator attention.', 'ack-due');
            $changed++;
        }
        if ($now >= $created + ($cfg['stale_minutes'] * MINUTE_IN_SECONDS) && $now < ($created + (($cfg['stale_minutes'] + 10) * MINUTE_IN_SECONDS))) {
            $repo->add_action($incident->id, 'stale_detected', 'Incident remains open beyond the configured stale-incident threshold.', 'stale-window');
            $changed++;
        }
    }
    return $changed;
}

add_action('gd_client_portal_security_governance_tick', 'gdcp_security_incident_governance_tick', 10);
