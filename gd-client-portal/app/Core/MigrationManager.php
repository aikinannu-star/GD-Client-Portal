<?php
if (!defined('ABSPATH')) exit;

final class GDCP_Migration_Manager {
    const OPTION = 'gdcp_schema_version';
    const ERROR_OPTION = 'gdcp_migration_error';
    const LOCK = 'gdcp_migration_lock';

    public static function version(){ return absint(get_option(self::OPTION,0)); }
    public static function run(){
        if (get_transient(self::LOCK)) return true;
        set_transient(self::LOCK, 1, 60);
        $current=self::version();
        $migrations=array(
            1=>'gdcp_migration_001_core_checkpoint',
            2=>'gdcp_migration_002_platform_checkpoint',
            3=>'gdcp_migration_003_legacy_schema_consolidation',
            4=>'gdcp_migration_004_tenant_data_checkpoint',
            5=>'gdcp_migration_005_repository_architecture_checkpoint',
            6=>'gdcp_migration_006_service_authorization_checkpoint',
            7=>'gdcp_migration_007_domain_consolidation_checkpoint',
            8=>'gdcp_migration_008_v7_runtime_checkpoint',
            9=>'gdcp_migration_009_event_bus_migration',
            10=>'gdcp_migration_010_repository_runtime_migration',
            11=>'gdcp_migration_011_service_runtime_migration',
            12=>'gdcp_migration_012_tenant_security_enforcement',
        );
        foreach($migrations as $version=>$callback){
            if($current >= $version) continue;
            try {
                if(!is_callable($callback)) throw new Exception('Migration callback unavailable: '.$callback);
                $result=call_user_func($callback);
                if($result===false) throw new Exception('Migration returned false: '.$callback);
                update_option(self::OPTION,$version,false);
                delete_option(self::ERROR_OPTION);
                update_option('gdcp_last_successful_migration',array('version'=>$version,'callback'=>$callback,'time'=>current_time('mysql')),false);
                $current=$version;
            } catch(Throwable $e) {
                update_option(self::ERROR_OPTION,array('version'=>$version,'callback'=>$callback,'message'=>sanitize_text_field($e->getMessage()),'time'=>current_time('mysql')),false);
                delete_transient(self::LOCK);
                return false;
            }
        }
        delete_transient(self::LOCK);
        return true;
    }
}
function gdcp_migration_001_core_checkpoint(){ update_option('gdcp_schema_started_at',current_time('mysql'),false); return true; }
function gdcp_migration_002_platform_checkpoint(){ update_option('gdcp_schema_platform_checkpoint',defined('GD_CLIENT_PORTAL_VERSION')?GD_CLIENT_PORTAL_VERSION:'7.0.0',false); return true; }

function gdcp_migration_003_legacy_schema_consolidation(){
    $steps=array(
        'audit'=>'gd_client_portal_activate_audit_table','approvals'=>'gd_client_portal_activate_approval_table','sla'=>'gd_client_portal_activate_sla_table',
        'contracts'=>'gd_client_portal_contracts_activate_table','contract_signatures'=>'gd_client_portal_contracts_activate_signature_table',
        'notifications'=>'gd_client_portal_notifications_activate','delivery'=>'gd_client_portal_activate_delivery_table','assignments'=>'gd_client_portal_activate_assignments_table',
        'calendar'=>'gd_client_portal_activate_calendar_table','onboarding'=>'gd_client_portal_activate_onboarding_tables','collaboration'=>'gd_client_portal_activate_collaboration_tables',
        'requests'=>'gd_client_portal_activate_requests_table','billing'=>'gd_client_portal_billing_activate_tables','payments'=>'gd_client_portal_payment_activate_table',
        'documents'=>'gd_client_portal_documents_activate_tables','feedback'=>'gd_client_portal_feedback_install','support'=>'gd_client_portal_support_install','intake'=>'gd_client_portal_activate_intake_tables',
    );
    $failed=array();
    foreach($steps as $name=>$callback){
        if(!function_exists($callback)) continue;
        try { $result=call_user_func($callback); if($result===false) $failed[]=$name; }
        catch(Throwable $e){ $failed[]=$name; }
    }
    // Legacy feature version markers are now advisory; the central checkpoint is authoritative.
    if($failed){ update_option('gdcp_migration_failed_steps',$failed,false); throw new Exception('Schema setup incomplete: '.implode(', ',$failed)); }
    delete_option('gdcp_migration_failed_steps');
    update_option('gd_client_portal_deferred_install_version','6.8.0-complete',false);
    return true;
}
function gdcp_migration_004_tenant_data_checkpoint(){
    if(function_exists('gd_client_portal_migrate_tenant_assignments')) gd_client_portal_migrate_tenant_assignments();
    update_option('gdcp_tenant_data_checkpoint',1,false);
    return true;
}
function gdcp_migration_005_repository_architecture_checkpoint(){
    update_option('gdcp_repository_architecture_version','1.0.0',false);
    return true;
}

function gdcp_migration_006_service_authorization_checkpoint(){ update_option('gdcp_service_layer_version','1.0.0',false); update_option('gdcp_authorization_layer_version','1.0.0',false); return true; }

function gdcp_migration_007_domain_consolidation_checkpoint(){
    update_option('gdcp_domain_architecture_version','1.0.0',false);
    update_option('gdcp_legacy_module_policy','canonical_includes_first',false);
    $enabled=get_option('gdcp_legacy_modules_enabled',null);
    if(!is_array($enabled)) update_option('gdcp_legacy_modules_enabled',array(),false);
    return true;
}
function gdcp_migration_008_v7_runtime_checkpoint(){
    update_option('gdcp_platform_version','7.1.0',false);
    update_option('gdcp_v7_runtime_ready',1,false);
    return true;
}
function gdcp_migration_009_event_bus_migration(){
    update_option('gdcp_event_bus_version','1.0.0',false);
    update_option('gdcp_event_bus_policy','legacy_hooks_bridged_to_central_bus',false);
    return true;
}

function gdcp_migration_010_repository_runtime_migration(){
    update_option('gdcp_repository_runtime_version','2.0.0',false);
    update_option('gdcp_repository_policy','repositories_are_canonical_read_path',false);
    update_option('gdcp_repository_domains',array('project','tenant','notification','billing','document','support','automation'),false);
    return true;
}

function gdcp_migration_011_service_runtime_migration(){
    update_option('gdcp_service_layer_version','2.0.0',false);
    update_option('gdcp_service_policy','services_are_canonical_write_path',false);
    update_option('gdcp_service_domains',array('project','tenant','billing','document','support','authorization'),false);
    return true;
}

function gdcp_migration_012_tenant_security_enforcement(){ update_option('gdcp_tenant_security_version','1.0.0',false); update_option('gdcp_tenant_security_policy','central_context_and_repository_enforcement',false); return true; }
