<?php
/**
 * GD Client Portal v6.0 — Intelligent Portal Operating System.
 * Central platform health, migration orchestration and runtime safeguards.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_platform_version(){ return '7.6.0'; }
function gd_client_portal_platform_schema_version(){ return absint(get_option('gd_client_portal_platform_schema_version',0)); }
function gd_client_portal_platform_db_error($context=''){
    global $wpdb;
    if (empty($wpdb->last_error)) return;
    $errors=get_option('gd_client_portal_platform_db_errors',array());
    if(!is_array($errors)) $errors=array();
    $errors[]=array('context'=>sanitize_key($context),'message'=>sanitize_text_field($wpdb->last_error),'time'=>current_time('mysql'));
    update_option('gd_client_portal_platform_db_errors',array_slice($errors,-20),false);
    $wpdb->last_error='';
}
function gd_client_portal_platform_column_exists($table,$column){
    global $wpdb;
    $table=preg_replace('/[^A-Za-z0-9_]/','',$table); $column=preg_replace('/[^A-Za-z0-9_]/','',$column);
    return (bool)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME=%s",$table,$column));
}
function gd_client_portal_platform_table_exists($table){
    global $wpdb;
    $table=preg_replace('/[^A-Za-z0-9_]/','',(string)$table);
    if($table==='') return false;
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table)) === $table;
}
function gd_client_portal_platform_index_exists($table,$index){
    global $wpdb;
    $table=preg_replace('/[^A-Za-z0-9_]/','',(string)$table);
    $index=preg_replace('/[^A-Za-z0-9_]/','',(string)$index);
    if($table===''||$index==='') return false;
    return (bool)$wpdb->get_var($wpdb->prepare('SHOW INDEX FROM `'.$table.'` WHERE Key_name=%s',$index));
}
function gd_client_portal_platform_add_performance_indexes(){
    global $wpdb;
    $indexes=array(
        $wpdb->prefix.'gd_projects'=>array(
            'tenant_user'=>'tenant_id,user_id,id',
            'tenant_created'=>'tenant_id,created_at,id',
            'tenant_status'=>'tenant_id,status,id',
        ),
        $wpdb->prefix.'gd_project_messages'=>array(
            'project_created'=>'project_id,created_at,id',
            'tenant_created'=>'tenant_id,created_at,id',
        ),
        $wpdb->prefix.'gd_project_approvals'=>array(
            'project_status_id'=>'project_id,status,id',
            'tenant_status_id'=>'tenant_id,status,id',
        ),
        $wpdb->prefix.'gd_project_assignments'=>array(
            'project_role'=>'project_id,assignment_role,created_at',
            'tenant_user'=>'tenant_id,user_id,project_id',
        ),
        $wpdb->prefix.'gd_project_slas'=>array(
            'tenant_due'=>'tenant_id,due_date,project_id',
            'tenant_stage_due'=>'tenant_id,stage_due_date,project_id',
        ),
        $wpdb->prefix.'gd_project_deliveries'=>array(
            'project_status_version'=>'project_id,status,version,id',
        ),
        $wpdb->prefix.'gd_project_calendar'=>array(
            'tenant_start'=>'tenant_id,start_at,id',
            'project_start'=>'project_id,start_at,id',
        ),
        $wpdb->prefix.'gd_portal_documents'=>array(
            'tenant_project_status'=>'tenant_id,project_id,status,id',
            'tenant_user_status'=>'tenant_id,user_id,status,id',
        ),
        $wpdb->prefix.'gd_portal_document_requests'=>array(
            'project_user_status'=>'project_id,user_id,status,id',
            'tenant_status'=>'tenant_id,status,id',
        ),
        $wpdb->prefix.'gd_support_tickets'=>array(
            'tenant_status_updated'=>'tenant_id,status,updated_at,id',
            'user_status_updated'=>'user_id,status,updated_at,id',
            'project_status_updated'=>'project_id,status,updated_at,id',
            'status_due'=>'status,due_at,id',
        ),
        $wpdb->prefix.'gd_support_messages'=>array(
            'ticket_created'=>'ticket_id,created_at,id',
            'tenant_created'=>'tenant_id,created_at,id',
        ),
        $wpdb->prefix.'gd_portal_invoices'=>array(
            'tenant_status_due'=>'tenant_id,status,due_date,id',
            'user_status_due'=>'user_id,status,due_date,id',
            'project_status'=>'project_id,status,id',
        ),
        $wpdb->prefix.'gd_portal_quotes'=>array(
            'tenant_status'=>'tenant_id,status,id',
            'user_status'=>'user_id,status,id',
        ),
        $wpdb->prefix.'gd_portal_payments'=>array(
            'tenant_created'=>'tenant_id,created_at,id',
            'invoice_status'=>'invoice_id,status,id',
        ),
        $wpdb->prefix.'gd_portal_requests'=>array(
            'tenant_user_status'=>'tenant_id,user_id,status,id',
            'tenant_status_updated'=>'tenant_id,status,updated_at,id',
        ),
        $wpdb->prefix.'gd_client_onboarding'=>array(
            'project_updated'=>'project_id,updated_at,id',
            'tenant_status_updated'=>'tenant_id,status,updated_at,id',
        ),
        $wpdb->prefix.'gd_project_tasks'=>array(
            'project_status_due'=>'project_id,status,due_date,id',
            'tenant_status_due'=>'tenant_id,status,due_date,id',
        ),
        $wpdb->prefix.'gd_project_updates'=>array(
            'project_created'=>'project_id,created_at,id',
            'tenant_visibility_created'=>'tenant_id,visibility,created_at,id',
        ),
        $wpdb->prefix.'gd_project_internal_notes'=>array(
            'project_created'=>'project_id,created_at,id',
        ),
        $wpdb->prefix.'gd_portal_automation_queue'=>array(
            'status_run_at'=>'status,run_at,id',
            'tenant_status_run_at'=>'tenant_id,status,run_at,id',
            'project_status'=>'project_id,status,id',
        ),
        $wpdb->prefix.'gd_portal_automation_logs'=>array(
            'tenant_created'=>'tenant_id,created_at,id',
            'project_created'=>'project_id,created_at,id',
            'status_created'=>'status,created_at,id',
        ),
    );
    foreach($indexes as $table=>$table_indexes){
        if(!gd_client_portal_platform_table_exists($table)) continue;
        foreach($table_indexes as $name=>$columns){
            if(gd_client_portal_platform_index_exists($table,$name)) continue;
            $safe_table=preg_replace('/[^A-Za-z0-9_]/','',$table);
            $safe_name=preg_replace('/[^A-Za-z0-9_]/','',$name);
            $safe_columns=preg_replace('/[^A-Za-z0-9_,]/','',$columns);
            if($safe_table===''||$safe_name===''||$safe_columns==='') continue;
            $wpdb->query("ALTER TABLE `{$safe_table}` ADD KEY `{$safe_name}` ({$safe_columns})");
            gd_client_portal_platform_db_error('performance_index_'.$safe_name);
        }
    }
}
function gd_client_portal_platform_migrate(){
    global $wpdb;
    $current=gd_client_portal_platform_schema_version();
    if($current<1){
        if(function_exists('gd_client_portal_automation_activate_tables')) gd_client_portal_automation_activate_tables();
        if(function_exists('gd_client_portal_automation_table')){
            $logs=gd_client_portal_automation_table('logs'); $queue=gd_client_portal_automation_table('queue');
            require_once ABSPATH.'wp-admin/includes/upgrade.php';
            $charset=$wpdb->get_charset_collate();
            if(!gd_client_portal_platform_column_exists($logs,'duration_ms')) dbDelta("ALTER TABLE {$logs} ADD duration_ms bigint(20) unsigned NULL DEFAULT NULL");
            if(!gd_client_portal_platform_column_exists($queue,'locked_at')) dbDelta("ALTER TABLE {$queue} ADD locked_at datetime NULL");
            if(!gd_client_portal_platform_column_exists($queue,'last_attempt_at')) dbDelta("ALTER TABLE {$queue} ADD last_attempt_at datetime NULL");
            gd_client_portal_platform_db_error('platform_schema_1');
        }
        update_option('gd_client_portal_platform_schema_version',1,false); $current=1;
    }
    if($current<2){
        // Keep automation analytics compatible with older installations even if
        // a previous release created the logs table without duration_ms.
        if(function_exists('gd_client_portal_automation_table')){
            $logs=gd_client_portal_automation_table('logs');
            if(!gd_client_portal_platform_column_exists($logs,'duration_ms')){
                $wpdb->query("ALTER TABLE {$logs} ADD duration_ms bigint(20) unsigned NULL DEFAULT NULL");
                gd_client_portal_platform_db_error('automation_duration_ms');
            }
        }
        update_option('gd_client_portal_platform_schema_version',2,false);
    }
    if($current<3){
        update_option('gd_client_portal_platform_schema_version',3,false);
        $current=3;
    }
    if($current<4){
        // v6.1: normalize platform diagnostic state without destructive changes.
        if (get_option('gd_client_portal_platform_last_migration', false) === false) {
            update_option('gd_client_portal_platform_last_migration', current_time('mysql'), false);
        }
        update_option('gd_client_portal_platform_schema_version',4,false);
    }
    if($current<5){
        // v6.2: unified search is schema-free; record the platform migration checkpoint only.
        update_option('gd_client_portal_platform_last_migration', current_time('mysql'), false);
        update_option('gd_client_portal_platform_schema_version',5,false);
    }
    if($current<6){
        // v7.6 performance: add targeted compound indexes for the portal's
        // tenant/project/status/date access patterns. Existing installations
        // are upgraded in-place; no data is changed and missing tables are skipped.
        gd_client_portal_platform_add_performance_indexes();
        update_option('gd_client_portal_platform_schema_version',6,false);
        $current=6;
    }
    if($current<7){
        if(function_exists('gdcp_security_audit_repository')) gdcp_security_audit_repository()->install();
        if(function_exists('gdcp_security_incident_repository')) gdcp_security_incident_repository()->install();
        update_option('gd_client_portal_platform_schema_version',7,false);
    }
    return true;
}
function gd_client_portal_platform_activation(){
    try { gd_client_portal_platform_migrate(); return true; }
    catch(Throwable $e){
        $errors=get_option('gd_client_portal_platform_db_errors',array()); if(!is_array($errors))$errors=array();
        $errors[]=array('context'=>'platform_activation','message'=>sanitize_text_field($e->getMessage()),'time'=>current_time('mysql'));
        update_option('gd_client_portal_platform_db_errors',array_slice($errors,-20),false); error_log('[GD Client Portal] Platform migration failed: '.$e->getMessage()); return false;
    }
}
add_action('plugins_loaded',function(){
    // Upgrade-safe path: plugin updates do not always invoke activation hooks.
    // The migration runner is idempotent and therefore safe on every request.
    gd_client_portal_platform_migrate();
},18);

/**
 * Workflow readiness checks for the production build.
 * These checks are intentionally non-destructive: they verify that the core
 * workflow entry points are loaded and that their backing runtime services are
 * available without creating clients, projects, invoices, or payments.
 */
function gd_client_portal_platform_workflow_readiness(){
    global $wp_filter;

    $checks=array();
    $checks['Tenant workflow available']=function_exists('gd_client_portal_get_current_tenant_id') || class_exists('GDCP_Tenant_Service');
    $checks['Project workflow available']=function_exists('gd_client_portal_get_project') || class_exists('GDCP_Project_Service') || class_exists('GDCP_ProjectRepository');
    $checks['Document workflow available']=class_exists('GDCP_Document_Service') || function_exists('gd_client_portal_document_upload');
    $checks['Support workflow available']=class_exists('GDCP_Support_Service') || function_exists('gd_client_portal_support_create');
    $checks['Billing workflow available']=class_exists('GDCP_Billing_Service') || function_exists('gd_client_portal_payment_initialize');
    $checks['Authorization layer available']=class_exists('GDCP_Authorization') || class_exists('GDCP_AuthorizationService') || function_exists('gd_client_portal_verify_project_access');
    $checks['AJAX runtime available']=function_exists('wp_doing_ajax') && function_exists('wp_send_json_success');
    $checks['Protected file layer available']=function_exists('gd_client_portal_secure_stream_file') || function_exists('gd_client_portal_stream_private_file');
    $checks['Automation runtime available']=function_exists('gd_client_portal_automation_table') && !empty($wp_filter['gd_client_portal_automation_daily']);
    $checks['Admin capability layer available']=function_exists('gd_client_portal_user_can_access_admin') || function_exists('gd_client_portal_access_admin');

    return $checks;
}

function gd_client_portal_platform_workflow_summary(){
    $checks=gd_client_portal_platform_workflow_readiness();
    $passed=0;
    foreach($checks as $ok){ if($ok) $passed++; }
    return array('passed'=>$passed,'total'=>count($checks),'checks'=>$checks);
}



/**
 * Operational readiness checks. These are non-destructive checks intended to
 * catch production configuration problems before client workflows depend on
 * the affected subsystem.
 */
function gd_client_portal_platform_operational_readiness(){
    global $wpdb;
    $checks=array();
    $uploads=wp_upload_dir();
    $checks['HTTPS or local development'] = is_ssl() || in_array(wp_parse_url(home_url(), PHP_URL_HOST), array('localhost','127.0.0.1','::1'), true);
    $checks['Uploads writable'] = empty($uploads['error']) && !empty($uploads['basedir']) && is_writable($uploads['basedir']);
    $checks['WordPress cron callable'] = function_exists('wp_next_scheduled') && function_exists('wp_schedule_event');
    $checks['Daily automation scheduled'] = (bool) wp_next_scheduled('gd_client_portal_automation_daily');
    $checks['Database connection healthy'] = empty($wpdb->last_error);
    $checks['Required project table present'] = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->prefix.'gd_projects')) === $wpdb->prefix.'gd_projects');
    $checks['AJAX URL available'] = function_exists('admin_url') && (bool) admin_url('admin-ajax.php');
    $checks['REST URL available'] = function_exists('rest_url') && (bool) rest_url();
    $checks['PHP version supported'] = version_compare(PHP_VERSION,'7.4','>=');
    return $checks;
}

function gd_client_portal_platform_operational_summary(){
    $checks=gd_client_portal_platform_operational_readiness();
    $passed=0; foreach($checks as $ok){ if($ok) $passed++; }
    return array('passed'=>$passed,'total'=>count($checks),'checks'=>$checks);
}

function gd_client_portal_platform_health(){
    global $wpdb;
    $checks=array();
    $checks['WordPress version']=version_compare(get_bloginfo('version'),'6.0','>=');
    $checks['Plugin version']=defined('GD_CLIENT_PORTAL_VERSION') && version_compare(GD_CLIENT_PORTAL_VERSION,'6.0.0','>=');
    $checks['Core projects table']=($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->prefix.'gd_projects'))===$wpdb->prefix.'gd_projects');
    if (function_exists('gd_client_portal_automation_table')) {
        $automation_rules = gd_client_portal_automation_table('rules');
        $checks['Automation tables'] = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $automation_rules)) === $automation_rules);
    } else {
        $checks['Automation tables'] = false;
    }
    $checks['Platform schema current']=gd_client_portal_platform_schema_version()>=5;

    // Runtime readiness checks: these do not mutate state and are safe on
    // every admin request. They make common production failures visible
    // before a client workflow depends on the affected subsystem.
    $uploads = wp_upload_dir();
    $checks['Uploads directory available'] = empty($uploads['error']) && !empty($uploads['basedir']) && is_dir($uploads['basedir']) && wp_is_writable($uploads['basedir']);
    $checks['WordPress AJAX endpoint'] = function_exists('admin_url') && (bool) admin_url('admin-ajax.php');
    $checks['REST endpoint available'] = function_exists('rest_url') && (bool) rest_url();
    $checks['Daily automation scheduled'] = (bool) wp_next_scheduled('gd_client_portal_automation_daily');
    $checks['PHP JSON support'] = function_exists('wp_json_encode') || function_exists('json_encode');
    $checks['No recorded database errors'] = empty((array) get_option('gd_client_portal_platform_db_errors',array()));
    return $checks;
}
function gd_client_portal_platform_admin_page(){
    if(!function_exists('gd_client_portal_user_can_access_admin') || !gd_client_portal_user_can_access_admin()) wp_die('Access denied.');
    $checks=gd_client_portal_platform_health();
    echo '<div class="wrap"><h1>GD Client Portal — Platform Health</h1><p>Central operating-system status, migration state and activation diagnostics.</p><table class="widefat striped" style="max-width:900px"><thead><tr><th>Check</th><th>Status</th></tr></thead><tbody>';
    foreach($checks as $label=>$ok) echo '<tr><td>'.esc_html($label).'</td><td>'.($ok?'<span style="color:#16803a;font-weight:700">PASS</span>':'<span style="color:#b32d2e;font-weight:700">ATTENTION</span>').'</td></tr>';
    echo '</tbody></table>';
    $failures=get_option('gd_client_portal_activation_failures',array()); $boot=get_option('gd_client_portal_bootstrap_failures',array()); $db=get_option('gd_client_portal_platform_db_errors',array());
    echo '<h2>Diagnostics</h2><p>Activation failures: <strong>'.count((array)$failures).'</strong> · Bootstrap failures: <strong>'.count((array)$boot).'</strong> · Database migration errors: <strong>'.count((array)$db).'</strong></p>';
    echo '<p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-command-center')).'">Command Center</a></p></div>';
}
add_action('admin_menu',function(){ add_submenu_page('gd-client-portal','Platform Health','Platform Health','gd_client_portal_access_admin','gd-client-portal-platform-health','gd_client_portal_platform_admin_page'); },25);
