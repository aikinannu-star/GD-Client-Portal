<?php
/** Security Response Control Center: governed incident operations UI. */
if (!defined('ABSPATH')) exit;

function gdcp_security_control_center_can_access($user_id = 0) {
    $user_id = absint($user_id ?: get_current_user_id());
    if (!$user_id) return false;
    return user_can($user_id, 'manage_options') || user_can($user_id, 'gd_client_portal_tenant_admin');
}

function gdcp_security_control_center_redirect($args = array()) {
    $url = admin_url('admin.php?page=gd-client-portal-security-control-center');
    if ($args) $url = add_query_arg($args, $url);
    wp_safe_redirect($url); exit;
}

function gdcp_security_control_center_page() {
    if (!gdcp_security_control_center_can_access()) wp_die(esc_html__('You are not authorized to access security incidents.', 'gd-client-portal'), 403);
    $repo = function_exists('gdcp_security_incident_repository') ? gdcp_security_incident_repository() : null;
    if (!$repo) return;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gdcp_security_control_action'])) {
        check_admin_referer('gdcp_security_control_center');
        $action = sanitize_key(wp_unslash($_POST['gdcp_security_control_action']));
        $id = absint($_POST['incident_id'] ?? 0);
        $incident = $repo->find($id);
        if (!$incident || !gdcp_security_incident_can_manage($incident)) {
            gdcp_security_control_center_redirect(array('gdcp_notice' => 'denied'));
        }
        if ($action === 'state') {
            $type = sanitize_key(wp_unslash($_POST['incident_state'] ?? ''));
            if (in_array($type, array('acknowledged','resolved','reopened'), true)) {
                $ok = $repo->governed_action($id, $type, wp_unslash($_POST['incident_note'] ?? ''), wp_unslash($_POST['incident_evidence'] ?? ''));
                gdcp_security_control_center_redirect(array('gdcp_notice' => $ok ? 'updated' : 'failed'));
            }
        }
        if ($action === 'assign') {
            $owner = absint($_POST['owner_user_id'] ?? 0);
            $owner_user = $owner ? get_userdata($owner) : false;
            $allowed = $owner_user && user_can($owner, 'manage_options');
            if (!$allowed && $owner_user && absint($incident->tenant_id) > 0) {
                $allowed = user_can($owner, 'gd_client_portal_tenant_admin') && function_exists('gd_client_portal_get_user_tenant_id') && absint(gd_client_portal_get_user_tenant_id($owner)) === absint($incident->tenant_id);
            }
            if (!$allowed) gdcp_security_control_center_redirect(array('gdcp_notice' => 'invalid_owner'));
            $ok = $repo->governed_assign_owner($id, $owner);
            gdcp_security_control_center_redirect(array('gdcp_notice' => $ok ? 'assigned' : 'failed'));
        }
    }

    $notice = sanitize_key($_GET['gdcp_notice'] ?? '');
    echo '<div class="wrap"><h1>Security Response Control Center</h1>';
    echo '<p>Governed security incident management. Access, ownership, lifecycle actions, deadlines, and integrity are enforced here.</p>';
    if ($notice) {
        $messages = array('updated'=>'Incident state updated.','assigned'=>'Incident owner assigned.','denied'=>'You are not authorized for that incident.','invalid_owner'=>'The selected owner is not an authorized administrator for this incident.','failed'=>'The requested operation could not be completed.');
        if (isset($messages[$notice])) { $class = in_array($notice, array('updated','assigned'), true) ? 'notice-success' : 'notice-error'; echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($messages[$notice]) . '</p></div>'; }
    }
    $tenant_filter = current_user_can('manage_options') ? absint($_GET['tenant_id'] ?? 0) : absint(gd_client_portal_get_current_tenant_id());
    $filters = array(); if ($tenant_filter) $filters['tenant_id'] = $tenant_filter;
    $status = sanitize_key($_GET['status'] ?? ''); if ($status) $filters['status'] = $status;
    $severity = sanitize_key($_GET['severity'] ?? ''); if ($severity) $filters['severity'] = $severity;
    $incidents = $repo->list_for_user(get_current_user_id(), $filters, 100);

    echo '<form method="get" style="margin:16px 0"><input type="hidden" name="page" value="gd-client-portal-security-control-center">';
    if (current_user_can('manage_options')) echo '<input type="number" min="1" name="tenant_id" placeholder="Tenant ID" value="' . esc_attr($tenant_filter ?: '') . '"> ';
    echo '<select name="status"><option value="">All status</option><option value="open"' . selected($status,'open',false) . '>Open</option><option value="acknowledged"' . selected($status,'acknowledged',false) . '>Acknowledged</option><option value="resolved"' . selected($status,'resolved',false) . '>Resolved</option></select> ';
    echo '<select name="severity"><option value="">All severity</option><option value="critical"' . selected($severity,'critical',false) . '>Critical</option><option value="high"' . selected($severity,'high',false) . '>High</option><option value="medium"' . selected($severity,'medium',false) . '>Medium</option><option value="low"' . selected($severity,'low',false) . '>Low</option></select> ';
    submit_button('Filter','secondary','submit',false); echo '</form>';

    if (!$incidents) { echo '<div class="notice notice-info"><p>No incidents are visible within your authorization scope.</p></div></div>'; return; }
    echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Created</th><th>Severity</th><th>Category</th><th>Status</th><th>Tenant</th><th>Owner</th><th>Deadline</th><th>Integrity</th><th>Actions</th></tr></thead><tbody>';
    foreach ($incidents as $inc) {
        $integrity = $repo->verify_actions($inc->id);
        $owner = $inc->owner_user_id ? get_userdata($inc->owner_user_id) : false;
        $deadline = $inc->resolve_due_at ?: $inc->ack_due_at;
        echo '<tr><td>#' . esc_html($inc->id) . '</td><td>' . esc_html($inc->created_at) . '</td><td>' . esc_html(strtoupper($inc->severity)) . '</td><td>' . esc_html($inc->category) . '</td><td>' . esc_html($inc->status) . '</td><td>' . esc_html($inc->tenant_id ?: 'Global') . '</td><td>' . esc_html($owner ? $owner->display_name : 'Unassigned') . '</td><td>' . esc_html($deadline ?: '—') . '</td><td>' . esc_html($integrity['ok'] ? 'PASS' : 'ATTENTION') . '</td><td>';
        echo '<details><summary>Manage</summary><form method="post" style="margin-top:8px">'; wp_nonce_field('gdcp_security_control_center');
        echo '<input type="hidden" name="gdcp_security_control_action" value="state"><input type="hidden" name="incident_id" value="' . esc_attr($inc->id) . '">';
        echo '<select name="incident_state"><option value="acknowledged">Acknowledge</option><option value="resolved">Resolve</option><option value="reopened">Reopen</option></select> <input name="incident_note" placeholder="Note" maxlength="1000"> <input name="incident_evidence" placeholder="Evidence" maxlength="120"> '; submit_button('Apply','secondary','submit',false); echo '</form>';
        $owners = get_users(array('number'=>-1,'fields'=>array('ID','display_name'),'orderby'=>'display_name','order'=>'ASC'));
        echo '<form method="post" style="margin-top:8px">'; wp_nonce_field('gdcp_security_control_center'); echo '<input type="hidden" name="gdcp_security_control_action" value="assign"><input type="hidden" name="incident_id" value="' . esc_attr($inc->id) . '"><select name="owner_user_id"><option value="0">Select owner</option>';
        foreach ($owners as $candidate) { $eligible = user_can($candidate->ID,'manage_options') || (absint($inc->tenant_id)>0 && user_can($candidate->ID,'gd_client_portal_tenant_admin') && function_exists('gd_client_portal_get_user_tenant_id') && absint(gd_client_portal_get_user_tenant_id($candidate->ID))===absint($inc->tenant_id)); if ($eligible) echo '<option value="' . esc_attr($candidate->ID) . '"' . selected($candidate->ID,$inc->owner_user_id,false) . '>' . esc_html($candidate->display_name) . '</option>'; }
        echo '</select> '; submit_button('Assign Owner','secondary','submit',false); echo '</form>';
        echo '<h4>Action history</h4><ul>';
        foreach ($repo->actions($inc->id,100) as $a) echo '<li>' . esc_html($a->action_time) . ' — <strong>' . esc_html($a->action_type) . '</strong> — ' . esc_html($a->note) . ' — hash ' . esc_html(substr($a->action_hash,0,12)) . '…</li>';
        echo '</ul></details></td></tr>';
    }
    echo '</tbody></table></div>';
}

add_action('admin_menu', function () {
    add_submenu_page('gd-client-portal','Security Response Control Center','Security Response','manage_options','gd-client-portal-security-control-center','gdcp_security_control_center_page');
}, 36);
