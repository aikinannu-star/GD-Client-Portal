<?php
/**
 * Automation scheduler runtime contracts.
 * Requires disposable WordPress PHPUnit environment.
 */
class GDCP_Automation_Scheduler_Runtime_Contracts_Test extends WP_UnitTestCase {
    public function test_core_automation_hooks_are_registered() {
        $required = array(
            'gd_client_portal_automation_daily',
            'gd_client_portal_automation_reliability_tick',
            'gd_client_portal_automation_queue_runner',
        );
        foreach ($required as $hook) {
            $this->assertTrue(has_action($hook) !== false, 'Expected registered hook: ' . $hook);
        }
    }

    public function test_reliability_hook_can_be_scheduled_and_cleared() {
        $hook = 'gd_client_portal_automation_reliability_tick';
        wp_clear_scheduled_hook($hook);
        $this->assertFalse((bool) wp_next_scheduled($hook));
        $scheduled = wp_schedule_single_event(time() + 60, $hook);
        $this->assertNotFalse($scheduled);
        $this->assertNotFalse(wp_next_scheduled($hook));
        wp_clear_scheduled_hook($hook);
        $this->assertFalse((bool) wp_next_scheduled($hook));
    }

    public function test_queue_runner_missing_item_is_safe() {
        $this->assertTrue(function_exists('gd_client_portal_automation_queue_runner'));
        $this->assertNull(gd_client_portal_automation_queue_runner(999999));
    }

    public function test_reliability_recovery_is_callable() {
        $this->assertTrue(function_exists('gd_client_portal_automation_recover_stale_queue'));
        $result = gd_client_portal_automation_recover_stale_queue();
        $this->assertNull($result);
    }

    public function test_security_response_tick_is_registered_when_module_loaded() {
        if (function_exists('gdcp_security_response_tick')) {
            $this->assertTrue(has_action('gd_client_portal_security_response_tick') !== false);
        } else {
            $this->markTestSkipped('Security response automation module is not loaded in this test bootstrap.');
        }
    }
}
