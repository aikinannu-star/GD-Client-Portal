<?php

class GDCP_Integration_Suite_Report_Test extends WP_UnitTestCase {

    public function test_runtime_contract_report_has_expected_shape() {
        $report = gd_client_portal_runtime_integration_contracts();

        $this->assertIsArray($report);
        $this->assertArrayHasKey('generated_at', $report);
        $this->assertArrayHasKey('checks', $report);
        $this->assertArrayHasKey('all_passed', $report);

        foreach ($report['checks'] as $name => $check) {
            $this->assertIsString($name);
            $this->assertArrayHasKey('ok', $check);
            $this->assertArrayHasKey('detail', $check);
            $this->assertIsBool($check['ok']);
        }
    }

    public function test_core_wordpress_contracts_pass_in_real_runtime() {
        $report = gd_client_portal_runtime_integration_contracts();

        $this->assertTrue($report['checks']['wordpress_runtime']['ok']);
        $this->assertTrue($report['checks']['ajax_security']['ok']);
        $this->assertTrue($report['checks']['uploads_runtime']['ok']);
        $this->assertTrue($report['checks']['cron_runtime']['ok']);
    }
}
