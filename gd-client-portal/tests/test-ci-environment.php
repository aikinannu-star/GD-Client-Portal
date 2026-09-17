<?php

class GDCP_CI_Environment_Test extends WP_UnitTestCase {

    public function test_wordpress_test_environment_is_loaded() {
        $this->assertTrue(defined('ABSPATH'));
        $this->assertTrue(function_exists('get_option'));
        $this->assertTrue(function_exists('wp_insert_user'));
    }

    public function test_plugin_is_loaded() {
        $this->assertTrue(defined('GD_CLIENT_PORTAL_VERSION'));
        $this->assertTrue(function_exists('gd_client_portal_runtime_integration_contracts'));
    }

    public function test_database_connection_is_available() {
        global $wpdb;

        $this->assertNotEmpty($wpdb->dbh);
        $this->assertNotEmpty($wpdb->prefix);
    }
}
