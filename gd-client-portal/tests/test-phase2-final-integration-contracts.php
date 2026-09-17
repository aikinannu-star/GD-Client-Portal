<?php
/** Consolidated Phase 2 integration contract inventory. */
class GDCP_Phase2_Final_Integration_Contracts_Test extends WP_UnitTestCase {
    public function test_required_runtime_surfaces_are_loaded() {
        foreach (array(
            'gdcp_notification_service',
            'gdcp_payment_service',
            'gdcp_contract_service',
            'gdcp_delivery_service',
            'gdcp_automation_service',
        ) as $fn) $this->assertTrue(function_exists($fn), 'Missing runtime service: '.$fn);
    }
    public function test_cron_schedules_are_registered() {
        foreach (array('gd_client_portal_automation_daily','gd_client_portal_automation_queue_runner','gd_client_portal_security_response_tick') as $hook) {
            $this->assertTrue(has_action($hook) !== false, 'Missing runtime hook: '.$hook);
        }
    }
    public function test_phase2_runtime_test_files_exist() {
        $root = dirname(__DIR__);
        foreach (array('test-tenant-isolation-contracts.php','test-project-authorization-contracts.php','test-protected-resource-runtime-contracts.php','test-authenticated-ajax-runtime-contracts.php','test-payment-runtime-contracts.php','test-contract-approval-delivery-runtime.php','test-workflow-runtime-contracts.php','test-failure-recovery-runtime-contracts.php','test-automation-scheduler-runtime-contracts.php','test-notification-idempotency-runtime-contracts.php','test-woocommerce-runtime-contracts.php') as $file) $this->assertFileExists($root.'/tests/'.$file);
    }
}
