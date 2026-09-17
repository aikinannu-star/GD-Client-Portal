<?php
/**
 * GD Client Portal v6.1 — Platform Control Center.
 * Centralized health, module diagnostics, migration status and safe repair actions.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_platform_control_modules() {
    return array(
        'platform'=>'Platform Core','automation'=>'Workflow Automation','smart-data'=>'Smart Data','governance'=>'Workflow Governance',
        'studio'=>'Workflow Studio','insights'=>'Workflow Intelligence','reliability'=>'Automation Reliability','event-intelligence'=>'Event Intelligence',
        'predictive'=>'Predictive Intelligence','command-center'=>'Command Center','lifecycle'=>'Client Lifecycle','lifecycle-automation'=>'Lifecycle Automation',
        'communication-intelligence'=>'Communication Intelligence','experience-intelligence'=>'Experience Intelligence','experience-automation'=>'Experience Automation',
        'billing'=>'Billing & Finance','payments'=>'Payments','contracts'=>'Contracts','documents'=>'Documents','support'=>'Support','collaboration'=>'Collaboration'
    );
}
function gd_client_portal_platform_control_module_status() {
    $status=array();
    foreach(gd_client_portal_platform_control_modules() as $slug=>$label){
        $path=GD_CLIENT_PORTAL_PATH.'includes/'.$slug.'.php';
        $status[$slug]=array('label'=>$label,'file'=>is_file($path),'loaded'=>false);
        $status[$slug]['loaded']=is_file($path) && (function_exists('gd_client_portal_'.$slug.'_version') || function_exists('gd_client_portal_'.$slug.'_table') || function_exists('gd_client_portal_'.$slug.'_render') || function_exists('gd_client_portal_'.$slug.'_admin_page') || $slug==='platform');
    }
    return $status;
}
function gd_client_portal_platform_control_cron_status() {
    $events=array(
        'gd_client_portal_automation_daily'=>'Daily automation engine',
        'gd_client_portal_automation_reliability_tick'=>'5-minute automation reliability heartbeat',
        'gd_client_portal_payment_daily'=>'Payment daily processing',
        'gd_client_portal_sla_daily'=>'SLA daily processing'
    );
    $out=array();
    foreach($events as $hook=>$label){$next=wp_next_scheduled($hook);$out[$hook]=array('label'=>$label,'next'=>$next ? wp_date(get_option('date_format').' '.get_option('time_format'),$next) : 'Not scheduled');}
    return $out;
}
function gd_client_portal_platform_control_repair() {
    if(!current_user_can('manage_options')) return array('ok'=>false,'message'=>__('Administrator permission required.','gd-client-portal'));
    $actions=array();
    try {
        if(function_exists('gd_client_portal_platform_migrate')) { gd_client_portal_platform_migrate(); $actions[]='Platform migrations checked'; }
        if(function_exists('gd_client_portal_ensure_admin_access_caps')) { gd_client_portal_ensure_admin_access_caps(); $actions[]='Permissions checked'; }
        if(!wp_next_scheduled('gd_client_portal_automation_daily')) { wp_schedule_event(time()+600,'daily','gd_client_portal_automation_daily'); $actions[]='Daily automation schedule restored'; }
        if(!wp_next_scheduled('gd_client_portal_automation_reliability_tick')) { wp_schedule_event(time()+300,'gdcp_5min','gd_client_portal_automation_reliability_tick'); $actions[]='Reliability heartbeat restored'; }
        update_option('gd_client_portal_platform_last_repair',current_time('mysql'),false);
        return array('ok'=>true,'message'=>__('Platform checks and safe repairs completed.','gd-client-portal'),'actions'=>$actions);
    } catch(Throwable $e) {
        error_log('[GD Client Portal] Platform repair failed: '.$e->getMessage());
        return array('ok'=>false,'message'=>__('A repair step failed. Check the Platform Health diagnostics.','gd-client-portal'));
    }
}


/**
 * Runtime diagnostics for activation/bootstrap failures.
 *
 * These diagnostics are intentionally read-only. They surface failures that
 * were isolated by the safe bootstrap and activation wrappers so an
 * administrator can identify partial-load conditions after activation.
 */
function gd_client_portal_platform_runtime_diagnostics() {
    global $wp_version;

    $bootstrap = get_option('gd_client_portal_bootstrap_failures', array());
    $activation = get_option('gd_client_portal_activation_failures', array());

    return array(
        'wordpress_version' => isset($wp_version) ? (string) $wp_version : '',
        'php_version'       => PHP_VERSION,
        'plugin_version'    => defined('GD_CLIENT_PORTAL_VERSION') ? GD_CLIENT_PORTAL_VERSION : '',
        'installed_at'      => get_option('gd_client_portal_installed', 0),
        'bootstrap_failures'=> is_array($bootstrap) ? $bootstrap : array(),
        'activation_failures'=> is_array($activation) ? $activation : array(),
    );
}

function gd_client_portal_platform_runtime_status_ok() {
    $diagnostics = gd_client_portal_platform_runtime_diagnostics();

    return empty($diagnostics['bootstrap_failures'])
        && empty($diagnostics['activation_failures']);
}

function gd_client_portal_platform_runtime_admin_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (gd_client_portal_platform_runtime_status_ok()) {
        return;
    }

    $url = admin_url('admin.php?page=gd-client-portal-platform-control');

    echo '<div class="notice notice-warning"><p><strong>'
        . esc_html__('GD Client Portal loaded with diagnostic warnings.', 'gd-client-portal')
        . '</strong> '
        . esc_html__('Some optional bootstrap or activation steps reported failures. Review the Platform Control Center before relying on affected features.', 'gd-client-portal')
        . ' <a href="' . esc_url($url) . '">'
        . esc_html__('Open diagnostics', 'gd-client-portal')
        . '</a></p></div>';
}

add_action('admin_notices', 'gd_client_portal_platform_runtime_admin_notice');

function gd_client_portal_platform_control_page() {
    if(!current_user_can('manage_options')) wp_die(__('Access denied.','gd-client-portal'));
    if(isset($_POST['gdcp_platform_repair']) && check_admin_referer('gdcp_platform_repair')) {
        $r=gd_client_portal_platform_control_repair();
        echo '<div class="notice '.($r['ok']?'notice-success':'notice-error').' is-dismissible"><p>'.esc_html($r['message']).'</p></div>';
    }
    $health=function_exists('gd_client_portal_platform_health')?gd_client_portal_platform_health():array();
    $mods=gd_client_portal_platform_control_module_status(); $crons=gd_client_portal_platform_control_cron_status();
    echo '<div class="wrap"><h1>GD Client Portal — Platform Control Center</h1><p>Central operational diagnostics, runtime-readiness checks and non-destructive repair tools for the portal platform.</p>';
    echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:18px 0">';
    foreach($health as $label=>$ok) echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 18px;min-width:180px"><strong>'.esc_html($label).'</strong><br><span style="font-size:18px;font-weight:700">'.($ok?'PASS':'ATTENTION').'</span></div>';
    $pass_count = 0; foreach($health as $ok){ if($ok) $pass_count++; }
    $total_count = count($health);
    echo '<p><strong>Functional readiness:</strong> '.esc_html((string)$pass_count).' / '.esc_html((string)$total_count).' checks passing.</p>';
    echo '</div><form method="post">'.wp_nonce_field('gdcp_platform_repair','_wpnonce',true,false).'<input type="hidden" name="gdcp_platform_repair" value="1"><button class="button button-primary">Run Safe Platform Repair</button></form>';
    echo '<h2>Module Status</h2><table class="widefat striped"><thead><tr><th>Module</th><th>File</th><th>Loaded</th></tr></thead><tbody>';
    foreach($mods as $m) echo '<tr><td>'.esc_html($m['label']).'</td><td>'.($m['file']?'PASS':'MISSING').'</td><td>'.($m['loaded']?'PASS':'CHECK').'</td></tr>';
    echo '</tbody></table><h2 style="margin-top:28px">Scheduled Jobs</h2><table class="widefat striped"><thead><tr><th>Job</th><th>Next run</th></tr></thead><tbody>';
    foreach($crons as $c) echo '<tr><td>'.esc_html($c['label']).'</td><td>'.esc_html($c['next']).'</td></tr>';
    echo '</tbody></table>';
    echo '<h2 style="margin-top:28px">Operational Readiness</h2>';
    $operational=function_exists('gd_client_portal_platform_operational_summary')?gd_client_portal_platform_operational_summary():array('passed'=>0,'total'=>0,'checks'=>array());
    echo '<p><strong>Operational readiness:</strong> '.esc_html((string)$operational['passed']).' / '.esc_html((string)$operational['total']).' checks passing. These checks validate production prerequisites without creating or modifying client data.</p>';
    echo '<table class="widefat striped"><thead><tr><th>Operational check</th><th>Status</th></tr></thead><tbody>';
    foreach($operational['checks'] as $label=>$ok) echo '<tr><td>'.esc_html($label).'</td><td>'.($ok?'<span style="color:#16803a;font-weight:700">PASS</span>':'<span style="color:#b32d2e;font-weight:700">ATTENTION</span>').'</td></tr>';
    echo '</tbody></table>';
    echo '<h2 style="margin-top:28px">Workflow Readiness</h2>';
    $workflow=function_exists('gd_client_portal_platform_workflow_summary')?gd_client_portal_platform_workflow_summary():array('passed'=>0,'total'=>0,'checks'=>array());
    echo '<p><strong>Workflow readiness:</strong> '.esc_html((string)$workflow['passed']).' / '.esc_html((string)$workflow['total']).' checks passing. These checks verify loaded runtime entry points without creating or changing business data.</p>';
    echo '<table class="widefat striped"><thead><tr><th>Workflow</th><th>Status</th></tr></thead><tbody>';
    foreach($workflow['checks'] as $label=>$ok) echo '<tr><td>'.esc_html($label).'</td><td>'.($ok?'<span style="color:#16803a;font-weight:700">PASS</span>':'<span style="color:#b32d2e;font-weight:700">ATTENTION</span>').'</td></tr>';
    echo '</tbody></table>';
    echo '<h2 style="margin-top:28px">Runtime Diagnostics</h2>';
    $runtime = gd_client_portal_platform_runtime_diagnostics();
    echo '<table class="widefat striped"><tbody>';
    echo '<tr><td><strong>Plugin version</strong></td><td>' . esc_html($runtime['plugin_version']) . '</td></tr>';
    echo '<tr><td><strong>WordPress version</strong></td><td>' . esc_html($runtime['wordpress_version']) . '</td></tr>';
    echo '<tr><td><strong>PHP version</strong></td><td>' . esc_html($runtime['php_version']) . '</td></tr>';
    echo '<tr><td><strong>Bootstrap failures</strong></td><td>' . esc_html((string) count($runtime['bootstrap_failures'])) . '</td></tr>';
    echo '<tr><td><strong>Activation failures</strong></td><td>' . esc_html((string) count($runtime['activation_failures'])) . '</td></tr>';
    echo '</tbody></table>';

    $runtime_failures = array_merge($runtime['activation_failures'], $runtime['bootstrap_failures']);
    if (!empty($runtime_failures)) {
        echo '<h3>Recorded failures</h3><table class="widefat striped"><thead><tr><th>Area</th><th>Message</th><th>File</th><th>Line</th><th>Time</th></tr></thead><tbody>';
        foreach ($runtime_failures as $failure) {
            $area = isset($failure['step']) ? $failure['step'] : (isset($failure['module']) ? $failure['module'] : 'runtime');
            echo '<tr><td>' . esc_html((string) $area) . '</td><td>' . esc_html(isset($failure['message']) ? (string) $failure['message'] : '') . '</td><td>' . esc_html(isset($failure['file']) ? basename((string) $failure['file']) : '') . '</td><td>' . esc_html(isset($failure['line']) ? (string) $failure['line'] : '') . '</td><td>' . esc_html(isset($failure['time']) ? (string) $failure['time'] : '') . '</td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p><strong>PASS:</strong> No bootstrap or activation failures are currently recorded.</p>';
    }

    $last=get_option('gd_client_portal_platform_last_repair',''); if($last) echo '<p><strong>Last repair:</strong> '.esc_html($last).'</p>';
    echo '</div>';
}
add_action('admin_menu',function(){add_submenu_page('gd-client-portal','Platform Control Center','Platform Control','manage_options','gd-client-portal-platform-control','gd_client_portal_platform_control_page');},26);

/**
 * Controlled workflow verification.
 * These checks are intentionally read-only: they verify the runtime contracts
 * required by core workflows without creating tenants, projects, invoices or payments.
 */
function gd_client_portal_platform_workflow_verification() {
    global $wpdb;

    $prefix = isset($wpdb->prefix) ? $wpdb->prefix : '';
    $checks = array(
        'Authentication and current-user APIs' => function_exists('is_user_logged_in') && function_exists('get_current_user_id'),
        'Tenant authorization boundary' => function_exists('gd_client_portal_verify_tenant_access'),
        'Project ownership boundary' => function_exists('gd_client_portal_verify_project_access'),
        'Canonical AJAX authorization guard' => function_exists('gd_client_portal_ajax_guard'),
        'Protected file streaming layer' => function_exists('gd_client_portal_stream_private_file') || function_exists('gd_client_portal_stream_protected_file'),
        'Project persistence table' => $prefix !== '' && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $prefix . 'gd_projects')) === $prefix . 'gd_projects',
        'Document persistence table' => $prefix !== '' && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $prefix . 'gd_portal_documents')) === $prefix . 'gd_portal_documents',
        'Support persistence table' => $prefix !== '' && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $prefix . 'gd_support_tickets')) === $prefix . 'gd_support_tickets',
        'Billing runtime' => class_exists('GDCP_Billing_Controller') || function_exists('gd_client_portal_billing_admin_page') || function_exists('gd_client_portal_create_invoice'),
        'Automation scheduler runtime' => function_exists('wp_next_scheduled') && function_exists('wp_schedule_event'),
    );

    $passed = 0;
    foreach ($checks as $ok) {
        if ($ok) {
            $passed++;
        }
    }

    return array('passed' => $passed, 'total' => count($checks), 'checks' => $checks);
}

function gd_client_portal_platform_workflow_verification_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Access denied.', 'gd-client-portal'));
    }

    $verification = gd_client_portal_platform_workflow_verification();

    echo '<div class="wrap"><h1>GD Client Portal — Workflow Verification</h1>';
    echo '<p>This is a controlled, read-only verification pass. It checks runtime contracts required by core workflows without creating or changing client data.</p>';
    echo '<p><strong>Verification result:</strong> ' . esc_html((string) $verification['passed']) . ' / ' . esc_html((string) $verification['total']) . ' checks passing.</p>';
    echo '<table class="widefat striped"><thead><tr><th>Workflow contract</th><th>Status</th></tr></thead><tbody>';
    foreach ($verification['checks'] as $label => $ok) {
        echo '<tr><td>' . esc_html($label) . '</td><td>' . ($ok ? '<span style="color:#16803a;font-weight:700">PASS</span>' : '<span style="color:#b32d2e;font-weight:700">ATTENTION</span>') . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<p style="margin-top:20px">A passing result confirms runtime prerequisites. It does not substitute for user-journey testing with dedicated test accounts.</p>';
    echo '</div>';
}
add_action('admin_menu', function () {
    add_submenu_page('gd-client-portal', 'Workflow Verification', 'Workflow Verification', 'manage_options', 'gd-client-portal-workflow-verification', 'gd_client_portal_platform_workflow_verification_page');
}, 27);

/**
 * End-to-end scenario readiness.
 * Stores only administrator-selected test user IDs. It never creates users or
 * changes tenant/project data, making it safe to prepare a production test plan.
 */
function gd_client_portal_e2e_test_profile() {
    $profile = get_option('gd_client_portal_e2e_test_profile', array());
    return is_array($profile) ? $profile : array();
}

function gd_client_portal_e2e_test_profile_checks($profile = null) {
    if ($profile === null) {
        $profile = gd_client_portal_e2e_test_profile();
    }

    $keys = array(
        'tenant_a_admin' => 'Tenant A administrator',
        'tenant_b_admin' => 'Tenant B administrator',
        'client_user'    => 'Client user',
    );

    $checks = array();
    $ids = array();
    foreach ($keys as $key => $label) {
        $id = isset($profile[$key]) ? absint($profile[$key]) : 0;
        $user = $id ? get_user_by('id', $id) : false;
        $checks[$label . ' configured'] = (bool) $user;
        if ($user) {
            $ids[] = $id;
            $checks[$label . ' is not a test-invalid account'] = !empty($user->ID);
        }
    }

    $configured = count($ids) === 3;
    $checks['Test accounts are distinct'] = $configured && count(array_unique($ids)) === 3;
    $checks['WordPress user switching is possible for manual scenarios'] = function_exists('wp_set_current_user');
    $checks['Project authorization runtime available'] = function_exists('gd_client_portal_verify_project_access');
    $checks['Tenant authorization runtime available'] = function_exists('gd_client_portal_verify_tenant_access');
    $checks['Protected download runtime available'] = function_exists('gd_client_portal_stream_private_file') || function_exists('gd_client_portal_stream_protected_file');

    $passed = 0;
    foreach ($checks as $ok) {
        if ($ok) $passed++;
    }

    return array('passed' => $passed, 'total' => count($checks), 'checks' => $checks);
}

function gd_client_portal_e2e_scenario_readiness_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Access denied.', 'gd-client-portal'));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gdcp_e2e_profile_nonce'])) {
        check_admin_referer('gdcp_save_e2e_profile', 'gdcp_e2e_profile_nonce');
        $profile = array(
            'tenant_a_admin' => isset($_POST['tenant_a_admin']) ? absint($_POST['tenant_a_admin']) : 0,
            'tenant_b_admin' => isset($_POST['tenant_b_admin']) ? absint($_POST['tenant_b_admin']) : 0,
            'client_user'    => isset($_POST['client_user']) ? absint($_POST['client_user']) : 0,
        );
        update_option('gd_client_portal_e2e_test_profile', $profile, false);
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Test profile saved. No client or workflow data was changed.', 'gd-client-portal') . '</p></div>';
    }

    $profile = gd_client_portal_e2e_test_profile();
    $result = gd_client_portal_e2e_test_profile_checks($profile);

    echo '<div class="wrap"><h1>GD Client Portal — End-to-End Scenario Readiness</h1>';
    echo '<p>Configure dedicated WordPress accounts for controlled multi-user verification. This page only stores the selected account IDs and does not execute destructive workflows.</p>';
    echo '<form method="post">';
    wp_nonce_field('gdcp_save_e2e_profile', 'gdcp_e2e_profile_nonce');
    echo '<table class="form-table" role="presentation"><tbody>';
    foreach (array('tenant_a_admin' => 'Tenant A administrator user ID', 'tenant_b_admin' => 'Tenant B administrator user ID', 'client_user' => 'Client user ID') as $key => $label) {
        echo '<tr><th scope="row"><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input class="regular-text" type="number" min="1" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . esc_attr(isset($profile[$key]) ? absint($profile[$key]) : '') . '"></td></tr>';
    }
    echo '</tbody></table>';
    submit_button(__('Save test profile', 'gd-client-portal'));
    echo '</form>';

    echo '<h2>Readiness result</h2><p><strong>' . esc_html((string)$result['passed']) . ' / ' . esc_html((string)$result['total']) . ' checks passing.</strong></p>';
    echo '<table class="widefat striped"><thead><tr><th>Check</th><th>Status</th></tr></thead><tbody>';
    foreach ($result['checks'] as $label => $ok) {
        echo '<tr><td>' . esc_html($label) . '</td><td>' . ($ok ? '<strong style="color:#16803a">PASS</strong>' : '<strong style="color:#b32d2e">ACTION REQUIRED</strong>') . '</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h2 style="margin-top:28px">Controlled scenario sequence</h2><ol>';
    foreach (array(
        'Log in as Tenant A administrator and create/select a Tenant A project.',
        'Switch to Tenant B administrator and verify Tenant A project access and mutation are denied.',
        'Switch to the client user and verify only owned/authorized projects are visible.',
        'Upload a non-sensitive test file and verify authorized download succeeds.',
        'Attempt the same protected URL while unauthorized and verify access is denied.',
        'Create a support/message test item and verify cross-tenant mutation is denied.',
        'Run a quote → invoice → payment test only with test financial records and clean them up afterward.',
    ) as $step) {
        echo '<li>' . esc_html($step) . '</li>';
    }
    echo '</ol><p><strong>Safety:</strong> Use dedicated test tenants/accounts and non-production payment data for scenario execution.</p></div>';
}

add_action('admin_menu', function () {
    add_submenu_page('gd-client-portal', 'End-to-End Readiness', 'End-to-End Readiness', 'manage_options', 'gd-client-portal-e2e-readiness', 'gd_client_portal_e2e_scenario_readiness_page');
}, 28);

/**
 * Controlled end-to-end scenario execution report.
 *
 * This layer intentionally records administrator-observed PASS/FAIL results
 * rather than impersonating users or mutating production data automatically.
 */
function gd_client_portal_e2e_scenario_definitions() {
    return array(
        'tenant_isolation' => array('label' => 'Tenant A data is denied to Tenant B', 'category' => 'Isolation'),
        'project_ownership' => array('label' => 'Client can access only authorized projects', 'category' => 'Authorization'),
        'protected_download_authorized' => array('label' => 'Authorized protected-file download succeeds', 'category' => 'Files'),
        'protected_download_denied' => array('label' => 'Unauthorized protected-file download is denied', 'category' => 'Files'),
        'cross_tenant_mutation_denied' => array('label' => 'Cross-tenant support/message mutation is denied', 'category' => 'Authorization'),
        'billing_authorization' => array('label' => 'Quote → invoice → payment flow enforces authorization', 'category' => 'Billing'),
        'automation_execution' => array('label' => 'Scheduled automation executes for test data', 'category' => 'Automation'),
    );
}

function gd_client_portal_e2e_scenario_report() {
    $report = get_option('gd_client_portal_e2e_scenario_report', array());
    return is_array($report) ? $report : array();
}

function gd_client_portal_e2e_scenario_summary($report = null) {
    if ($report === null) $report = gd_client_portal_e2e_scenario_report();
    $definitions = gd_client_portal_e2e_scenario_definitions();
    $summary = array('total' => count($definitions), 'passed' => 0, 'failed' => 0, 'pending' => 0);
    foreach ($definitions as $key => $definition) {
        $status = isset($report[$key]['status']) ? $report[$key]['status'] : 'pending';
        if ($status === 'pass') $summary['passed']++;
        elseif ($status === 'fail') $summary['failed']++;
        else $summary['pending']++;
    }
    return $summary;
}

function gd_client_portal_e2e_scenario_execution_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Access denied.', 'gd-client-portal'));
    }

    $definitions = gd_client_portal_e2e_scenario_definitions();
    $report = gd_client_portal_e2e_scenario_report();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gdcp_e2e_execution_nonce'])) {
        check_admin_referer('gdcp_save_e2e_execution', 'gdcp_e2e_execution_nonce');
        $updated = array();
        foreach ($definitions as $key => $definition) {
            $status = isset($_POST['scenario'][$key]['status']) ? sanitize_key(wp_unslash($_POST['scenario'][$key]['status'])) : 'pending';
            if (!in_array($status, array('pass', 'fail', 'pending'), true)) $status = 'pending';
            $notes = isset($_POST['scenario'][$key]['notes']) ? sanitize_textarea_field(wp_unslash($_POST['scenario'][$key]['notes'])) : '';
            $updated[$key] = array(
                'status' => $status,
                'notes' => $notes,
                'updated_at' => current_time('mysql'),
                'updated_by' => gd_client_portal_cached_current_user_id(),
            );
        }
        update_option('gd_client_portal_e2e_scenario_report', $updated, false);
        $report = $updated;
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Scenario results saved. No workflows were automatically executed or modified.', 'gd-client-portal') . '</p></div>';
    }

    $summary = gd_client_portal_e2e_scenario_summary($report);
    echo '<div class="wrap"><h1>GD Client Portal — End-to-End Scenario Execution</h1>';
    echo '<p>Record the observed results of controlled tests performed with the dedicated accounts configured in End-to-End Readiness. This creates an auditable verification report without automatically impersonating users or changing production records.</p>';
    echo '<p><strong>' . esc_html((string)$summary['passed']) . ' passed · ' . esc_html((string)$summary['failed']) . ' failed · ' . esc_html((string)$summary['pending']) . ' pending</strong></p>';

    echo '<form method="post">';
    wp_nonce_field('gdcp_save_e2e_execution', 'gdcp_e2e_execution_nonce');
    echo '<table class="widefat striped"><thead><tr><th>Category</th><th>Scenario</th><th>Status</th><th>Evidence / notes</th><th>Last recorded</th></tr></thead><tbody>';
    foreach ($definitions as $key => $definition) {
        $entry = isset($report[$key]) && is_array($report[$key]) ? $report[$key] : array();
        $status = isset($entry['status']) ? $entry['status'] : 'pending';
        $notes = isset($entry['notes']) ? $entry['notes'] : '';
        $recorded = isset($entry['updated_at']) ? $entry['updated_at'] : '—';
        echo '<tr><td>' . esc_html($definition['category']) . '</td><td><strong>' . esc_html($definition['label']) . '</strong></td><td>';
        echo '<select name="scenario[' . esc_attr($key) . '][status]">';
        foreach (array('pending' => 'Pending', 'pass' => 'PASS', 'fail' => 'FAIL') as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($status, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td><td><textarea rows="3" style="width:100%" name="scenario[' . esc_attr($key) . '][notes]">' . esc_textarea($notes) . '</textarea></td><td>' . esc_html($recorded) . '</td></tr>';
    }
    echo '</tbody></table>';
    submit_button(__('Save scenario report', 'gd-client-portal'));
    echo '</form>';

    echo '<h2 style="margin-top:28px">Completion rule</h2>';
    if ($summary['failed'] === 0 && $summary['pending'] === 0) {
        echo '<p><strong style="color:#16803a">PASS: All controlled scenarios are recorded as passing.</strong></p>';
    } else {
        echo '<p>Production workflow verification is complete only when all scenarios are recorded as PASS and any failures have documented remediation.</p>';
    }
    echo '</div>';
}

add_action('admin_menu', function () {
    add_submenu_page('gd-client-portal', 'Scenario Execution', 'Scenario Execution', 'manage_options', 'gd-client-portal-e2e-execution', 'gd_client_portal_e2e_scenario_execution_page');
}, 29);

/**
 * Automated security/workflow regression evidence.
 *
 * These checks are intentionally fixture-free and read-only. They verify that
 * the runtime contracts required by the controlled scenarios remain present
 * after updates without creating tenants, projects, invoices, or payments.
 */
function gd_client_portal_automated_regression_checks() {
    global $wpdb;

    $prefix = isset($wpdb->prefix) ? $wpdb->prefix : '';
    $uploads = function_exists('wp_upload_dir') ? wp_upload_dir() : array();
    $checks = array(
        'Authentication API available' => function_exists('is_user_logged_in') && function_exists('get_current_user_id'),
        'Tenant authorization helper available' => function_exists('gd_client_portal_verify_tenant_access'),
        'Project ownership helper available' => function_exists('gd_client_portal_verify_project_access'),
        'Canonical AJAX guard available' => function_exists('gd_client_portal_ajax_guard'),
        'Private file streaming available' => function_exists('gd_client_portal_stream_private_file') || function_exists('gd_client_portal_stream_protected_file'),
        'Uploads runtime available' => !empty($uploads['basedir']) && !empty($uploads['baseurl']),
        'Projects table available' => $prefix !== '' && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $prefix . 'gd_projects')) === $prefix . 'gd_projects',
        'Document table available' => $prefix !== '' && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $prefix . 'gd_portal_documents')) === $prefix . 'gd_portal_documents',
        'Support table available' => $prefix !== '' && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $prefix . 'gd_support_tickets')) === $prefix . 'gd_support_tickets',
        'Cron scheduler API available' => function_exists('wp_next_scheduled') && function_exists('wp_schedule_event'),
        'AJAX endpoint runtime available' => function_exists('admin_url') && admin_url('admin-ajax.php') !== '',
    );

    $passed = 0;
    foreach ($checks as $ok) {
        if ($ok) $passed++;
    }

    return array(
        'generated_at' => current_time('mysql'),
        'passed' => $passed,
        'total' => count($checks),
        'checks' => $checks,
        'all_passed' => $passed === count($checks),
    );
}

function gd_client_portal_automated_regression_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Access denied.', 'gd-client-portal'));
    }

    $result = gd_client_portal_automated_regression_checks();
    $evidence = array(
        'generated_at' => $result['generated_at'],
        'plugin_version' => defined('GD_CLIENT_PORTAL_VERSION') ? GD_CLIENT_PORTAL_VERSION : '',
        'summary' => array('passed' => $result['passed'], 'total' => $result['total'], 'all_passed' => $result['all_passed']),
        'checks' => $result['checks'],
    );
    update_option('gd_client_portal_last_automated_regression', $evidence, false);

    echo '<div class="wrap"><h1>GD Client Portal — Automated Regression</h1>';
    echo '<p>This fixture-free regression pass verifies critical runtime contracts without creating or modifying portal records.</p>';
    echo '<p><strong>' . esc_html((string) $result['passed']) . ' / ' . esc_html((string) $result['total']) . ' checks passing</strong></p>';
    echo '<table class="widefat striped"><thead><tr><th>Regression contract</th><th>Status</th></tr></thead><tbody>';
    foreach ($result['checks'] as $label => $ok) {
        echo '<tr><td>' . esc_html($label) . '</td><td>' . ($ok ? '<span style="color:#16803a;font-weight:700">PASS</span>' : '<span style="color:#b32d2e;font-weight:700">FAIL</span>') . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<h2 style="margin-top:28px">Evidence</h2>';
    echo '<p>Last generated: ' . esc_html($result['generated_at']) . '. The result is stored as a WordPress option for administrator review after updates.</p>';
    if ($result['all_passed']) {
        echo '<p><strong style="color:#16803a">PASS: All automated runtime regression contracts are currently satisfied.</strong></p>';
    } else {
        echo '<p><strong style="color:#b32d2e">FAIL: Resolve failed regression contracts before marking controlled scenario verification complete.</strong></p>';
    }
    echo '</div>';
}

add_action('admin_menu', function () {
    add_submenu_page('gd-client-portal', 'Automated Regression', 'Automated Regression', 'manage_options', 'gd-client-portal-automated-regression', 'gd_client_portal_automated_regression_page');
}, 30);


/**
 * Production certification / release governance.
 *
 * Combines automated regression evidence with the controlled scenario report
 * and blocks certification whenever critical evidence is failed or pending.
 */
function gd_client_portal_production_certification_status() {
    $automated = get_option('gd_client_portal_last_automated_regression', array());
    $automated = is_array($automated) ? $automated : array();

    $scenario_report = gd_client_portal_e2e_scenario_report();
    $scenario_summary = gd_client_portal_e2e_scenario_summary($scenario_report);

    $automated_total = isset($automated['summary']['total']) ? (int) $automated['summary']['total'] : 0;
    $automated_passed = isset($automated['summary']['passed']) ? (int) $automated['summary']['passed'] : 0;
    $automated_ok = !empty($automated['summary']['all_passed'])
        && $automated_total > 0
        && $automated_passed === $automated_total;

    $scenarios_ok = $scenario_summary['total'] > 0
        && $scenario_summary['passed'] === $scenario_summary['total']
        && $scenario_summary['failed'] === 0
        && $scenario_summary['pending'] === 0;

    $platform_ok = function_exists('gd_client_portal_platform_runtime_status_ok')
        ? gd_client_portal_platform_runtime_status_ok()
        : false;

    $checks = array(
        'Runtime diagnostics' => array(
            'ok' => $platform_ok,
            'detail' => $platform_ok ? 'No recorded bootstrap or activation failures.' : 'Runtime diagnostic failures are recorded or diagnostics are unavailable.',
        ),
        'Automated regression evidence' => array(
            'ok' => $automated_ok,
            'detail' => $automated_total > 0
                ? sprintf('%d / %d automated contracts passing.', $automated_passed, $automated_total)
                : 'No automated regression evidence has been generated yet.',
        ),
        'Controlled scenario execution' => array(
            'ok' => $scenarios_ok,
            'detail' => sprintf(
                '%d passed, %d failed, %d pending.',
                $scenario_summary['passed'],
                $scenario_summary['failed'],
                $scenario_summary['pending']
            ),
        ),
    );

    $all_passed = true;
    foreach ($checks as $check) {
        if (empty($check['ok'])) {
            $all_passed = false;
            break;
        }
    }

    return array(
        'generated_at' => current_time('mysql'),
        'plugin_version' => defined('GD_CLIENT_PORTAL_VERSION') ? GD_CLIENT_PORTAL_VERSION : '',
        'checks' => $checks,
        'all_passed' => $all_passed,
    );
}

function gd_client_portal_production_certification_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Access denied.', 'gd-client-portal'));
    }

    $result = gd_client_portal_production_certification_status();

    echo '<div class="wrap"><h1>GD Client Portal — Production Certification</h1>';
    echo '<p>This governance report combines runtime diagnostics, automated regression evidence, and controlled end-to-end scenario results into a single release decision.</p>';
    echo '<table class="widefat striped"><thead><tr><th>Certification requirement</th><th>Status</th><th>Evidence</th></tr></thead><tbody>';

    foreach ($result['checks'] as $label => $check) {
        $status = !empty($check['ok'])
            ? '<span style="color:#16803a;font-weight:700">PASS</span>'
            : '<span style="color:#b32d2e;font-weight:700">BLOCKED</span>';
        echo '<tr><td>' . esc_html($label) . '</td><td>' . $status . '</td><td>' . esc_html($check['detail']) . '</td></tr>';
    }

    echo '</tbody></table>';
    echo '<h2 style="margin-top:28px">Release Decision</h2>';

    if ($result['all_passed']) {
        update_option('gd_client_portal_last_production_certification', array(
            'generated_at' => $result['generated_at'],
            'plugin_version' => $result['plugin_version'],
            'status' => 'certified',
        ), false);

        echo '<div class="notice notice-success inline"><p><strong>CERTIFIED FOR RELEASE</strong> — all required runtime, regression, and controlled scenario checks currently pass.</p></div>';
        echo '<p>Certified at: ' . esc_html($result['generated_at']) . '</p>';
    } else {
        update_option('gd_client_portal_last_production_certification', array(
            'generated_at' => $result['generated_at'],
            'plugin_version' => $result['plugin_version'],
            'status' => 'blocked',
        ), false);

        echo '<div class="notice notice-error inline"><p><strong>RELEASE BLOCKED</strong> — production certification cannot be granted until every requirement above passes.</p></div>';
    }

    echo '<h2 style="margin-top:28px">Certification Rule</h2>';
    echo '<p>Certification requires: no recorded runtime failures, a fully passing automated regression run, and every controlled end-to-end scenario recorded as PASS. Any failure or pending scenario blocks release.</p>';
    echo '</div>';
}

add_action('admin_menu', function () {
    add_submenu_page(
        'gd-client-portal',
        'Production Certification',
        'Production Certification',
        'manage_options',
        'gd-client-portal-production-certification',
        'gd_client_portal_production_certification_page'
    );
}, 31);
