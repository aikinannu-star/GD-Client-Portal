<?php

class GDCP_Runtime_Contracts_Test extends WP_UnitTestCase {

    public function test_plugin_runtime_contract_suite_exists() {
        $this->assertTrue(function_exists('gd_client_portal_runtime_integration_contracts'));
    }

    public function test_runtime_contracts_return_a_report() {
        $report = gd_client_portal_runtime_integration_contracts();

        $this->assertIsArray($report);
        $this->assertArrayHasKey('checks', $report);
        $this->assertArrayHasKey('all_passed', $report);
    }

    public function test_wordpress_runtime_contract_passes() {
        $report = gd_client_portal_runtime_integration_contracts();

        $this->assertArrayHasKey('wordpress_runtime', $report['checks']);
        $this->assertTrue($report['checks']['wordpress_runtime']['ok']);
    }

    public function test_project_persistence_contract_is_reported() {
        $report = gd_client_portal_runtime_integration_contracts();

        $this->assertArrayHasKey('project_persistence', $report['checks']);
        $this->assertIsBool($report['checks']['project_persistence']['ok']);
    }
}
