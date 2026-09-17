<?php
/**
 * Controlled security response automation.
 * Creates bounded incidents from high-confidence authorization-denial bursts.
 * Automated response is deliberately non-destructive: it classifies/escalates
 * and records evidence, but never locks accounts or changes privileges.
 */
if (!defined('ABSPATH')) exit;

function gdcp_security_response_thresholds() {
    return array('window_minutes' => 15, 'high_denials' => 5, 'critical_denials' => 10, 'critical_tenants' => 3);
}

function gdcp_security_response_tick() {
    if (!function_exists('gdcp_security_audit_repository') || !function_exists('gdcp_security_incident_repository')) return 0;
    $cfg = gdcp_security_response_thresholds();
    $repo = gdcp_security_audit_repository();
    $inc = gdcp_security_incident_repository();
    $rows = $repo->recent(500, array(
        'from' => gmdate('Y-m-d H:i:s', time() - ($cfg['window_minutes'] * MINUTE_IN_SECONDS)),
        'type' => 'authorization_denied',
    ));
    $candidates = array();
    foreach ((array) $rows as $row) {
        $uid = absint($row->user_id ?? 0);
        if (!$uid) continue;
        $candidates[$uid][] = $row;
    }
    $created = 0;
    foreach ($candidates as $uid => $events) {
        $count = count($events);
        if ($count < $cfg['high_denials']) continue;
        $tenants = array(); $correlations = array();
        foreach ($events as $event) {
            $tid = absint($event->tenant_id ?? 0);
            if ($tid) $tenants[$tid] = true;
            $cid = sanitize_key($event->correlation_id ?? '');
            if ($cid) $correlations[$cid] = true;
        }
        $critical = $count >= $cfg['critical_denials'] || count($tenants) >= $cfg['critical_tenants'];
        $severity = $critical ? 'critical' : 'high';
        $bucket = (int) floor(time() / ($cfg['window_minutes'] * MINUTE_IN_SECONDS));
        $key = 'auth-burst-' . $uid . '-' . $bucket;
        $tenant_id = count($tenants) === 1 ? (int) array_key_first($tenants) : 0;
        $title = sprintf('Automated authorization-denial burst for user %d', $uid);
        $evidence = 'user:' . $uid . '-events:' . $count;
        $id = $inc->create(array(
            'incident_key' => $key,
            'tenant_id' => $tenant_id,
            'severity' => $severity,
            'category' => 'authorization',
            'title' => $title,
            'evidence_ref' => $evidence,
        ));
        if (!$id) continue;
        $inc->add_action($id, 'auto_escalated', sprintf('Automated classification: %d authorization denials in %d minutes; %d tenant context(s).', $count, $cfg['window_minutes'], count($tenants)), $evidence);
        do_action('gdcp_security_incident_auto_escalated', $id, array('user_id'=>$uid,'severity'=>$severity,'denials'=>$count,'tenant_count'=>count($tenants),'correlation_count'=>count($correlations)));
        $created++;
    }
    return $created;
}

add_action('gd_client_portal_security_response_tick', 'gdcp_security_response_tick', 10);
