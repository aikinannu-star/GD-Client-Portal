<?php

class GDCP_Protected_File_Contracts_Test extends WP_UnitTestCase {

    public function test_upload_runtime_is_available() {
        $this->assertTrue(function_exists('wp_upload_dir'));

        $uploads = wp_upload_dir();
        $this->assertIsArray($uploads);
        $this->assertArrayHasKey('basedir', $uploads);
    }

    public function test_protected_file_security_layer_is_represented() {
        $candidates = array(
            'gd_client_portal_stream_protected_file',
            'gd_client_portal_protected_file_download',
            'gd_client_portal_authorize_file_access',
        );

        $available = false;
        foreach ($candidates as $candidate) {
            if (function_exists($candidate)) {
                $available = true;
                break;
            }
        }

        $this->assertTrue(
            $available || function_exists('gd_client_portal_runtime_integration_contracts'),
            'Protected-file security must be represented by a runtime contract.'
        );
    }
}
