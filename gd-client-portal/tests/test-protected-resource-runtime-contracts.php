<?php

class GDCP_Protected_Resource_Runtime_Contracts_Test extends WP_UnitTestCase {
    private $users = array();

    public function tearDown(): void {
        wp_set_current_user(0);
        foreach ($this->users as $user_id) {
            if (function_exists('wp_delete_user')) {
                wp_delete_user($user_id);
            }
        }
        parent::tearDown();
    }

    public function test_private_project_file_url_contains_action_project_and_valid_nonce() {
        $user_id = self::factory()->user->create();
        $this->users[] = $user_id;
        wp_set_current_user($user_id);

        $project_id = 987654;
        $url = gd_client_portal_private_project_file_url($project_id);

        $this->assertNotSame('', $url);
        $this->assertStringContainsString('gd_client_portal_project_file_download', $url);
        $this->assertStringContainsString('project_id=' . $project_id, $url);

        $parts = wp_parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        $this->assertArrayHasKey('_wpnonce', $query);
        $this->assertNotFalse(wp_verify_nonce($query['_wpnonce'], 'gd_client_portal_project_file_download_' . $project_id));
    }

    public function test_private_message_file_url_contains_action_message_and_valid_nonce() {
        $user_id = self::factory()->user->create();
        $this->users[] = $user_id;
        wp_set_current_user($user_id);

        $message_id = 987655;
        $url = gd_client_portal_private_message_file_url($message_id);

        $this->assertNotSame('', $url);
        $this->assertStringContainsString('gd_client_portal_message_file_download', $url);
        $this->assertStringContainsString('message_id=' . $message_id, $url);

        $parts = wp_parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        $this->assertArrayHasKey('_wpnonce', $query);
        $this->assertNotFalse(wp_verify_nonce($query['_wpnonce'], 'gd_client_portal_message_file_download_' . $message_id));
    }

    public function test_upload_url_to_path_rejects_missing_and_traversal_paths() {
        $this->assertFalse(gd_client_portal_upload_url_to_path('https://example.invalid/not-a-portal-file'));

        $uploads = wp_get_upload_dir();
        $this->assertFalse(
            gd_client_portal_upload_url_to_path(untrailingslashit($uploads['baseurl']) . '/../outside.txt')
        );
    }

    public function test_upload_url_to_path_accepts_a_real_file_inside_uploads() {
        $uploads = wp_upload_dir();
        wp_mkdir_p($uploads['basedir'] . '/gdcp-runtime');
        $filename = $uploads['basedir'] . '/gdcp-runtime/runtime-protected.txt';
        file_put_contents($filename, 'runtime protected resource');

        try {
            $url = trailingslashit($uploads['baseurl']) . 'gdcp-runtime/runtime-protected.txt';
            $resolved = gd_client_portal_upload_url_to_path($url);
            $this->assertNotFalse($resolved);
            $this->assertSame(realpath($filename), $resolved);
            $this->assertTrue(gd_client_portal_verify_upload_path($resolved));
        } finally {
            if (file_exists($filename)) {
                unlink($filename);
            }
        }
    }

    public function test_authorization_denies_unknown_project_to_regular_user() {
        $user_id = self::factory()->user->create();
        $this->users[] = $user_id;
        wp_set_current_user($user_id);

        $this->assertFalse(gdcp_can('view', 'project', 987656));
        $this->assertFalse(gdcp_can('download', 'project', 987656));
    }
}
