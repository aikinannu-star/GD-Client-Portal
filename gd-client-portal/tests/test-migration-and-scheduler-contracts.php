<?php

class GDCP_Migration_And_Scheduler_Contracts_Test extends WP_UnitTestCase {

    public function test_version_or_schema_metadata_is_available() {
        $plugin_version = defined('GD_CLIENT_PORTAL_VERSION') ? GD_CLIENT_PORTAL_VERSION : '';
        $installed_version = get_option('gd_client_portal_version', '');
        $schema_version = get_option('gd_client_portal_schema_version', '');

        $this->assertTrue(
            $plugin_version !== '' || $installed_version !== '' || $schema_version !== ''
        );
    }

    public function test_wordpress_scheduler_contract_is_available() {
        $this->assertTrue(function_exists('wp_schedule_event'));
        $this->assertTrue(function_exists('wp_next_scheduled'));
        $this->assertTrue(function_exists('wp_clear_scheduled_hook'));
    }

    public function test_scheduler_round_trip_with_test_hook() {
        $hook = 'gdcp_phpunit_contract_hook';

        wp_clear_scheduled_hook($hook);
        $this->assertFalse((bool) wp_next_scheduled($hook));

        $scheduled = wp_schedule_single_event(time() + 60, $hook);
        $this->assertNotFalse($scheduled);
        $this->assertNotFalse(wp_next_scheduled($hook));

        wp_clear_scheduled_hook($hook);
        $this->assertFalse((bool) wp_next_scheduled($hook));
    }
}
