<?php

class GDCP_Authenticated_Ajax_Runtime_Contracts_Test extends WP_UnitTestCase {

    /**
     * High-risk authenticated endpoints that must retain both request
     * authentication and nonce protection in the runtime build.
     */
    public function test_high_risk_ajax_callbacks_expose_security_contracts() {
        $contracts = array(
            'gd_client_portal_request_create' => array('includes/requests.php', 'gd_client_portal_verify_request', 'gd_client_portal_request'),
            'gd_client_portal_request_update' => array('includes/requests.php', 'gd_client_portal_verify_request', 'gd_client_portal_request_admin'),
            'gd_client_portal_feedback_create' => array('includes/feedback.php', 'gd_client_portal_verify_request', 'gd_client_portal_feedback'),
            'gd_client_portal_feedback_response' => array('includes/feedback.php', 'gd_client_portal_verify_request', 'gd_client_portal_feedback_admin'),
            'gd_client_portal_contract_create' => array('includes/contracts.php', 'gd_client_portal_verify_request', 'gd_client_portal_contracts_admin'),
            'gd_client_portal_contract_decision' => array('includes/contracts.php', 'gd_client_portal_verify_request', 'gd_client_portal_contracts_admin'),
            'gd_client_portal_document_upload' => array('includes/documents.php', 'gd_client_portal_verify_request', 'gd_client_portal_document'),
            'gd_client_portal_document_status' => array('includes/documents.php', 'gd_client_portal_verify_request', 'gd_client_portal_document'),
            'gd_client_portal_payment_manual' => array('includes/payments.php', 'gd_client_portal_verify_request', 'gd_client_portal_payment'),
            'gd_client_portal_finalize_delivery' => array('includes/delivery.php', 'gd_client_portal_verify_request', 'gd_client_portal_delivery'),
        );

        foreach ($contracts as $hook => $contract) {
            $source = dirname(__DIR__) . '/' . $contract[0];
            $contents = file_get_contents($source);
            $this->assertNotFalse($contents, "Source must be readable for {$hook}.");
            $this->assertStringContainsString("wp_ajax_{$hook}", $contents, "{$hook} must remain registered as authenticated AJAX.");
            $this->assertStringContainsString($contract[1], $contents, "{$hook} must enforce the authenticated request guard.");
            $this->assertStringContainsString($contract[2], $contents, "{$hook} must enforce its endpoint-specific nonce action.");
        }
    }

    public function test_ajax_nonce_round_trip_is_user_bound() {
        $user_a = self::factory()->user->create();
        $user_b = self::factory()->user->create();

        wp_set_current_user($user_a);
        $nonce_a = wp_create_nonce('gdcp_runtime_ajax');
        $this->assertNotFalse(wp_verify_nonce($nonce_a, 'gdcp_runtime_ajax'));

        wp_set_current_user($user_b);
        $this->assertFalse((bool) wp_verify_nonce($nonce_a, 'gdcp_runtime_ajax'));
    }

    public function test_authenticated_ajax_endpoints_are_not_registered_as_nopriv() {
        $source_files = array(
            'includes/requests.php',
            'includes/feedback.php',
            'includes/contracts.php',
            'includes/documents.php',
            'includes/payments.php',
            'includes/delivery.php',
        );

        $protected_hooks = array(
            'gd_client_portal_request_create', 'gd_client_portal_request_update',
            'gd_client_portal_feedback_create', 'gd_client_portal_feedback_response',
            'gd_client_portal_contract_create', 'gd_client_portal_contract_decision',
            'gd_client_portal_document_upload', 'gd_client_portal_document_status',
            'gd_client_portal_payment_manual', 'gd_client_portal_finalize_delivery',
        );

        foreach ($source_files as $relative) {
            $contents = file_get_contents(dirname(__DIR__) . '/' . $relative);
            $this->assertNotFalse($contents);
            foreach ($protected_hooks as $hook) {
                if (strpos($contents, "wp_ajax_{$hook}") !== false) {
                    $this->assertStringNotContainsString("wp_ajax_nopriv_{$hook}", $contents, "{$hook} must not expose its protected action to unauthenticated users.");
                }
            }
        }
    }
}
