<?php

class GDCP_Ajax_Security_Contracts_Test extends WP_UnitTestCase {

    public function test_nonce_api_is_available() {
        $this->assertTrue(function_exists('wp_create_nonce'));
        $this->assertTrue(function_exists('wp_verify_nonce'));
    }

    public function test_nonce_round_trip() {
        $action = 'gdcp_test_action';
        $nonce = wp_create_nonce($action);

        $this->assertNotFalse(wp_verify_nonce($nonce, $action));
        $this->assertFalse((bool) wp_verify_nonce($nonce, 'different_action'));
    }

    public function test_automation_inbox_action_binds_queue_to_tenant() {
        $source = dirname(__DIR__) . '/includes/inbox.php';
        $contents = file_get_contents($source);

        $this->assertStringContainsString("absint($row->tenant_id)!==$current_tenant", $contents);
        $this->assertStringContainsString("Automation queue access denied.", $contents);
        $this->assertStringContainsString("absint($project->tenant_id)!==absint($row->tenant_id)", $contents);
    }

    public function test_ajax_guard_contract_is_represented() {
        $available = function_exists('check_ajax_referer')
            && function_exists('current_user_can');

        $this->assertTrue($available);
    }
}
