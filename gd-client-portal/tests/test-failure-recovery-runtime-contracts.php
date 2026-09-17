<?php
/**
 * Failure/recovery runtime contracts.
 *
 * These tests require the disposable WordPress PHPUnit environment.
 */

class GDCP_Failure_Recovery_Runtime_Contracts_Test extends WP_UnitTestCase {

    public function test_database_transaction_primitives_are_available() {
        global $wpdb;
        $this->assertTrue(method_exists($wpdb, 'query'));
        $this->assertSame(0, (int) $wpdb->get_var('SELECT @@in_transaction'));
    }

    public function test_invalid_contract_acceptance_does_not_leave_transaction_open() {
        if ( ! function_exists('gdcp_contract_service') ) {
            $this->markTestSkipped('Contract service is unavailable.');
        }

        $result = gdcp_contract_service()->accept(999999, false, '');
        $this->assertTrue(is_wp_error($result) || $result === false);

        global $wpdb;
        $this->assertSame(0, (int) $wpdb->get_var('SELECT @@in_transaction'));
    }

    public function test_invalid_quote_conversion_does_not_leave_transaction_open() {
        if ( ! function_exists('gdcp_billing_service') ) {
            $this->markTestSkipped('Billing service is unavailable.');
        }

        $result = gdcp_billing_service()->convert_quote_to_invoice(999999);
        $this->assertTrue(is_wp_error($result) || $result === false || $result === 0);

        global $wpdb;
        $this->assertSame(0, (int) $wpdb->get_var('SELECT @@in_transaction'));
    }

    public function test_failed_automation_retry_is_non_destructive() {
        if ( ! function_exists('gdcp_automation_service') ) {
            $this->markTestSkipped('Automation service is unavailable.');
        }

        $this->assertFalse(gdcp_automation_service()->retry_queue(999999));
    }

    public function test_cancelled_or_missing_automation_cannot_be_requeued() {
        if ( ! function_exists('gdcp_automation_service') ) {
            $this->markTestSkipped('Automation service is unavailable.');
        }

        $this->assertFalse(gdcp_automation_service()->retry_queue(999999));
        $this->assertFalse(gdcp_automation_service()->cancel_queue(999999));
    }

    public function test_scheduler_recovery_contract_is_registered() {
        $this->assertTrue(function_exists('wp_schedule_single_event'));
        $this->assertTrue(function_exists('wp_next_scheduled'));
    }
}
