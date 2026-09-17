<?php
/**
 * Production Observability & Incident Readiness.
 *
 * Provides bounded, privacy-conscious operational event recording and an
 * administrator dashboard. This module is not a replacement for server logs.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function gd_client_portal_observability_events() {
    $events = get_option('gd_client_portal_operational_events', array());
    return is_array($events) ? $events : array();
}

function gd_client_portal_observability_record($type, $severity, $message, $context = array()) {
    $allowed_severity = array('info', 'warning', 'error', 'security');
    $severity = in_array($severity, $allowed_severity, true) ? $severity : 'info';

    $context = is_array($context) ? $context : array();

    // Never persist secrets, passwords, tokens, or raw request payloads.
    foreach (array('password', 'passwd', 'token', 'authorization', 'cookie', 'nonce') as $sensitive) {
        unset($context[$sensitive]);
    }

    $event = array(
        'time' => current_time('mysql'),
        'type' => sanitize_key($type),
        'severity' => $severity,
        'message' => sanitize_text_field($message),
        'context' => array_map('sanitize_text_field', $context),
    );

    $events = gd_client_portal_observability_events();
    array_unshift($events, $event);
    $events = array_slice($events, 0, 200);

    update_option('gd_client_portal_operational_events', $events, false);

    // Security events use the dedicated indexed audit boundary when available.
    if ($severity === 'security' && function_exists('gdcp_security_audit_repository')) {
        gdcp_security_audit_repository()->insert($event);
    }

    return $event;
}


/**
 * Return a request-scoped correlation identifier for security/diagnostic events.
 * The value is deliberately opaque and contains no user or object data.
 */
function gd_client_portal_security_correlation_id() {
    static $id = null;
    if ($id === null) {
        try {
            $id = bin2hex(random_bytes(12));
        } catch (Exception $e) {
            $id = wp_generate_password(24, false, false);
        }
    }
    return sanitize_key($id);
}

/**
 * Record an authorization denial using bounded, non-sensitive metadata.
 * One denial per action/object/id combination is recorded per request.
 */
function gd_client_portal_security_audit_denial($action, $object, $id = 0, $args = array()) {
    static $seen = array();
    $action = sanitize_key($action);
    $object = sanitize_key($object);
    $id = absint($id);
    $key = $action . '|' . $object . '|' . $id;
    if (isset($seen[$key])) return false;
    $seen[$key] = true;

    $tenant_id = function_exists('gd_client_portal_get_current_tenant_id')
        ? absint(gd_client_portal_get_current_tenant_id()) : 0;
    $user_id = function_exists('gd_client_portal_cached_current_user_id')
        ? absint(gd_client_portal_cached_current_user_id()) : 0;

    $context = array(
        'correlation_id' => gd_client_portal_security_correlation_id(),
        'action' => $action,
        'object' => $object,
        'object_id' => $id,
        'tenant_id' => $tenant_id,
        'user_id' => $user_id,
        'request_method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'])) : '',
        'ajax' => function_exists('wp_doing_ajax') && wp_doing_ajax() ? 1 : 0,
    );

    // Only include a safe project identifier when explicitly supplied; never log payloads.
    if (!empty($args['project_id'])) $context['project_id'] = absint($args['project_id']);

    return gd_client_portal_observability_record(
        'authorization_denied',
        'security',
        'Authorization denied for protected resource.',
        $context
    );
}

function gd_client_portal_observability_summary() {
    $events = gd_client_portal_observability_events();
    $summary = array(
        'total' => count($events),
        'info' => 0,
        'warning' => 0,
        'error' => 0,
        'security' => 0,
    );

    foreach ($events as $event) {
        $severity = isset($event['severity']) ? $event['severity'] : 'info';
        if (isset($summary[$severity])) {
            $summary[$severity]++;
        }
    }

    return $summary;
}

function gd_client_portal_observability_health() {
    $summary = gd_client_portal_observability_summary();
    $runtime_ok = function_exists('gd_client_portal_platform_runtime_status_ok')
        ? gd_client_portal_platform_runtime_status_ok()
        : false;

    $critical_recent = 0;
    $events = gd_client_portal_observability_events();
    $cutoff = strtotime('-24 hours');
    if (function_exists('gdcp_security_audit_repository')) {
        $critical_recent = gdcp_security_audit_repository()->count_recent(24);
    }

    foreach ($events as $event) {
        if (isset($event['severity'], $event['time'])
            && in_array($event['severity'], array('error', 'security'), true)
            && strtotime($event['time']) >= $cutoff) {
            $critical_recent++;
        }
    }

    return array(
        'runtime_ok' => $runtime_ok,
        'recent_critical_events' => $critical_recent,
        'status' => ($runtime_ok && $critical_recent === 0) ? 'healthy' : 'attention',
        'summary' => $summary,
    );
}

function gdcp_security_audit_investigation_filters_from_request() {
    $filters = array();
    foreach (array('correlation_id','action','object_type','severity') as $key) {
        if (isset($_GET[$key])) $filters[$key] = sanitize_key(wp_unslash($_GET[$key]));
    }
    foreach (array('from','to') as $key) {
        if (isset($_GET[$key])) $filters[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
    }
    if (!empty($_GET['tenant_id'])) $filters['tenant_id'] = absint($_GET['tenant_id']);
    if (!current_user_can('manage_options') && !empty($filters['tenant_id'])) {
        $filters['tenant_id'] = function_exists('gd_client_portal_get_current_tenant_id') ? absint(gd_client_portal_get_current_tenant_id()) : 0;
    }
    return $filters;
}

function gdcp_security_audit_safe_filters($filters) {
    $out = array();
    foreach ((array)$filters as $key => $value) {
        if (in_array($key, array('correlation_id','action','object_type','severity','from','to'), true)) $out[$key] = sanitize_text_field($value);
        if ($key === 'tenant_id') $out[$key] = absint($value);
    }
    return $out;
}

function gd_client_portal_observability_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Access denied.', 'gd-client-portal'));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gdcp_observability_action'])) {
        check_admin_referer('gdcp_observability');

        $action = sanitize_key(wp_unslash($_POST['gdcp_observability_action']));
        if ($action === 'clear_events') {
            update_option('gd_client_portal_operational_events', array(), false);
            if (function_exists('gdcp_security_audit_repository')) gdcp_security_audit_repository()->clear();
            echo '<div class="notice notice-success is-dismissible"><p>Operational event history cleared.</p></div>';
        }

        if ($action === 'record_health_snapshot') {
            $health = gd_client_portal_observability_health();
            gd_client_portal_observability_record(
                'health_snapshot',
                $health['status'] === 'healthy' ? 'info' : 'warning',
                'Administrator recorded an operational health snapshot.',
                array('status' => $health['status'])
            );
            echo '<div class="notice notice-success is-dismissible"><p>Health snapshot recorded.</p></div>';
        }
    }

    $health = gd_client_portal_observability_health();
    $events = gd_client_portal_observability_events();
    $audit_filters = gdcp_security_audit_safe_filters(gdcp_security_audit_investigation_filters_from_request());
    $security_repo = function_exists('gdcp_security_audit_repository') ? gdcp_security_audit_repository() : null;
    $security_events = $security_repo ? $security_repo->investigate($audit_filters, 100) : array();
    $integrity = $security_repo ? $security_repo->integrity_health(500) : array('ok'=>false,'checked'=>0,'bad_id'=>0);
    if (!empty($audit_filters['tenant_id']) && !current_user_can('manage_options')) {
        $audit_filters['tenant_id'] = absint(gd_client_portal_get_current_tenant_id());
    }

    echo '<div class="wrap"><h1>GD Client Portal — Production Observability</h1>';
    echo '<p>Operational monitoring for plugin-level health and incidents. Sensitive request data is intentionally excluded.</p>';

    $status_color = $health['status'] === 'healthy' ? '#16803a' : '#b32d2e';
    echo '<h2>Current Health: <span style="color:' . esc_attr($status_color) . '">' . esc_html(strtoupper($health['status'])) . '</span></h2>';
    echo '<table class="widefat striped"><tbody>';
    echo '<tr><td><strong>Runtime diagnostics</strong></td><td>' . ($health['runtime_ok'] ? 'PASS' : 'ATTENTION') . '</td></tr>';
    echo '<tr><td><strong>Errors recorded</strong></td><td>' . esc_html((string)$health['summary']['error']) . '</td></tr>';
    echo '<tr><td><strong>Security events recorded</strong></td><td>' . esc_html((string)$health['summary']['security']) . '</td></tr>';
    echo '<tr><td><strong>Critical events in last 24 hours</strong></td><td>' . esc_html((string)$health['recent_critical_events']) . '</td></tr>';
    echo '</tbody></table>';

    echo '<form method="post" style="margin:16px 0">';
    wp_nonce_field('gdcp_observability');
    echo '<input type="hidden" name="gdcp_observability_action" value="record_health_snapshot">';
    submit_button('Record Health Snapshot', 'secondary', 'submit', false);
    echo '</form>';

    echo '<h2>Operational Event History</h2>';
    if (empty($events)) {
        echo '<p>No operational events have been recorded yet.</p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>Time</th><th>Severity</th><th>Type</th><th>Message</th><th>Context</th></tr></thead><tbody>';
        foreach ($events as $event) {
            $context = !empty($event['context']) ? wp_json_encode($event['context']) : '';
            echo '<tr><td>' . esc_html($event['time']) . '</td><td>' . esc_html(strtoupper($event['severity'])) . '</td><td>' . esc_html($event['type']) . '</td><td>' . esc_html($event['message']) . '</td><td><code>' . esc_html($context) . '</code></td></tr>';
        }
        echo '</tbody></table>';
    }

    echo '<form method="post" style="margin-top:16px">';
    wp_nonce_field('gdcp_observability');
    echo '<input type="hidden" name="gdcp_observability_action" value="clear_events">';
    submit_button('Clear Event History', 'delete', 'submit', false);
    echo '</form>';

    echo '<h2>Persistent Security Audit</h2>';
    echo '<p><strong>Integrity:</strong> ' . esc_html($integrity['ok'] ? 'PASS' : 'ATTENTION') . ' — ' . esc_html((string)$integrity['checked']) . ' events checked' . ($integrity['bad_id'] ? ' (first invalid ID ' . esc_html((string)$integrity['bad_id']) . ')' : '') . '.</p>';
    echo '<form method="get" style="margin:12px 0">';
    echo '<input type="hidden" name="page" value="gd-client-portal-production-observability">';
    echo '<input name="correlation_id" placeholder="Correlation ID" value="' . esc_attr($audit_filters['correlation_id'] ?? '') . '"> ';
    echo '<input name="action" placeholder="Action" value="' . esc_attr($audit_filters['action'] ?? '') . '"> ';
    echo '<input name="object_type" placeholder="Object type" value="' . esc_attr($audit_filters['object_type'] ?? '') . '"> ';
    echo '<select name="severity"><option value="">All severity</option><option value="security"' . selected($audit_filters['severity'] ?? '', 'security', false) . '>Security</option></select> ';
    if (current_user_can('manage_options')) echo '<input type="number" min="1" name="tenant_id" placeholder="Tenant ID" value="' . esc_attr($audit_filters['tenant_id'] ?? '') . '"> ';
    echo '<input type="datetime-local" name="from" value="' . esc_attr($audit_filters['from'] ?? '') . '"> ';
    echo '<input type="datetime-local" name="to" value="' . esc_attr($audit_filters['to'] ?? '') . '"> ';
    submit_button('Filter Audit', 'secondary', 'submit', false);
    echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=gd-client-portal-production-observability')) . '">Reset</a></form>';
    echo '<p>Indexed security events are retained separately from the operational snapshot. Default retention is 90 days.</p>';
    if (empty($security_events)) {
        echo '<p>No persistent security events have been recorded.</p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>Time</th><th>Correlation</th><th>User</th><th>Tenant</th><th>Action</th><th>Object</th><th>Object ID</th></tr></thead><tbody>';
        foreach ($security_events as $event) {
            echo '<tr><td>' . esc_html($event->event_time) . '</td><td><code>' . esc_html($event->correlation_id) . '</code></td><td>' . esc_html((string)$event->user_id) . '</td><td>' . esc_html((string)$event->tenant_id) . '</td><td>' . esc_html($event->action) . '</td><td>' . esc_html($event->object_type) . '</td><td>' . esc_html((string)$event->object_id) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    echo '<h2>Security Incidents</h2>';
    $incident_repo = function_exists('gdcp_security_incident_repository') ? gdcp_security_incident_repository() : null;
    if ($incident_repo) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gdcp_incident_action'])) {
            check_admin_referer('gdcp_observability');
            $ia = sanitize_key(wp_unslash($_POST['gdcp_incident_action']));
            if ($ia === 'create') {
                $iid = $incident_repo->create(array('title'=>wp_unslash($_POST['incident_title']??''),'severity'=>wp_unslash($_POST['incident_severity']??'medium'),'category'=>wp_unslash($_POST['incident_category']??'authorization'),'tenant_id'=>absint($_POST['incident_tenant_id']??0),'evidence_ref'=>wp_unslash($_POST['incident_evidence']??'')));
                echo $iid ? '<div class="notice notice-success"><p>Incident created: #'.esc_html((string)$iid).'</p></div>' : '<div class="notice notice-error"><p>Incident could not be created.</p></div>';
            } elseif ($ia === 'action') {
                $iid=absint($_POST['incident_id']??0); $type=sanitize_key(wp_unslash($_POST['incident_state']??'note')); $incident_repo->add_action($iid,$type,wp_unslash($_POST['incident_note']??''),wp_unslash($_POST['incident_evidence']??''));
            }
        }
        echo '<form method="post" style="margin:12px 0">'; wp_nonce_field('gdcp_observability');
        echo '<input type="hidden" name="gdcp_incident_action" value="create"><input name="incident_title" required placeholder="Incident title"> ';
        echo '<select name="incident_severity"><option>low</option><option selected>medium</option><option>high</option><option>critical</option></select> ';
        echo '<select name="incident_category"><option selected>authorization</option><option>integrity</option><option>availability</option><option>integration</option><option>other</option></select> ';
        if(current_user_can('manage_options')) echo '<input type="number" min="0" name="incident_tenant_id" placeholder="Tenant ID"> ';
        echo '<input name="incident_evidence" placeholder="Evidence reference (audit event ID/correlation)" maxlength="120"> ';
        submit_button('Create Incident','secondary','submit',false); echo '</form>';
        $incidents=$incident_repo->list(array(),50);
        if(empty($incidents)) echo '<p>No security incidents have been classified.</p>'; else { echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Created</th><th>Severity</th><th>Category</th><th>Status</th><th>Tenant</th><th>Title</th><th>Integrity</th></tr></thead><tbody>'; foreach($incidents as $inc){$ih=$incident_repo->verify_actions($inc->id); echo '<tr><td>'.esc_html((string)$inc->id).'</td><td>'.esc_html($inc->created_at).'</td><td>'.esc_html(strtoupper($inc->severity)).'</td><td>'.esc_html($inc->category).'</td><td>'.esc_html($inc->status).'</td><td>'.esc_html((string)$inc->tenant_id).'</td><td>'.esc_html($inc->title).'</td><td>'.esc_html($ih['ok']?'PASS':'ATTENTION').'</td></tr>'; } echo '</tbody></table>'; }
    }

    echo '<h2>Incident Response</h2>';
    echo '<ol><li>Review Runtime Diagnostics and recent operational events.</li><li>Record deployment/rollback evidence where applicable.</li><li>Restore a known-good release only through the documented rollback process.</li><li>Re-run Automated Regression and Production Certification after recovery.</li></ol>';
    echo '</div>';
}

add_action('admin_menu', function () {
    add_submenu_page(
        'gd-client-portal',
        'Production Observability',
        'Production Observability',
        'manage_options',
        'gd-client-portal-production-observability',
        'gd_client_portal_observability_page'
    );
}, 35);
