<?php

/**
 * Runtime tests for contract, approval and delivery state boundaries.
 * Requires the disposable WordPress + MySQL PHPUnit environment.
 */
class GDCP_Contract_Approval_Delivery_Runtime_Test extends WP_UnitTestCase {
    private $tenant = 9401;
    private $user;
    private $project_id;

    public function setUp(): void {
        parent::setUp();
        global $wpdb;

        update_option('gd_client_portal_tenants', array(
            array('id'=>$this->tenant,'name'=>'Runtime Contract Tenant','slug'=>'runtime-contract','status'=>'active'),
        ));
        if (function_exists('gd_client_portal_contracts_activate_table')) gd_client_portal_contracts_activate_table();
        if (function_exists('gd_client_portal_contracts_activate_signature_table')) gd_client_portal_contracts_activate_signature_table();
        if (function_exists('gd_client_portal_activate_approval_table')) gd_client_portal_activate_approval_table();
        if (function_exists('gd_client_portal_activate_delivery_table')) gd_client_portal_activate_delivery_table();

        $this->user = self::factory()->user->create();
        gdcp_repository('tenant')->set_user_tenant($this->user, $this->tenant);
        wp_set_current_user($this->user);

        $wpdb->insert($wpdb->prefix.'gd_projects', array(
            'tenant_id'=>$this->tenant,'order_id'=>0,'user_id'=>$this->user,'product_id'=>0,
            'service_type'=>'general','title'=>'Runtime Contract Project','description'=>'Runtime state test',
            'status'=>'active','current_stage'=>'intake','progress'=>0,'created_at'=>current_time('mysql'),
        ), array('%d','%d','%d','%d','%s','%s','%s','%s','%d','%s'));
        $this->project_id = (int)$wpdb->insert_id;
    }

    public function tearDown(): void {
        wp_set_current_user(0);
        parent::tearDown();
    }

    public function test_contract_can_be_created_sent_and_decided_once() {
        $service = gdcp_contract_service();
        $id = $service->create_sent(array(
            'tenant_id'=>$this->tenant,'user_id'=>$this->user,'project_id'=>$this->project_id,
            'title'=>'Runtime Agreement','body'=>'Agreement body','status'=>'sent',
            'created_by'=>$this->user,
        ));
        $this->assertGreaterThan(0, $id);

        $result = $service->decide($id, 'accepted', 'Runtime Client', true);
        $this->assertFalse(is_wp_error($result));
        $row = $service->find($id);
        $this->assertSame('accepted', $row->status);
        $this->assertNotEmpty($row->accepted_at);

        $second = $service->decide($id, 'accepted', 'Runtime Client', true);
        $this->assertTrue(is_wp_error($second));
        $this->assertSame('not_pending', $second->get_error_code());
    }

    public function test_approval_is_bound_to_exact_project_version_and_tenant() {
        $repo = gdcp_repository('approval');
        $id = $repo->create_pending($this->project_id, $this->tenant, 7, $this->user);
        $this->assertGreaterThan(0, $id);

        global $wpdb;
        $wpdb->update($repo->table(), array('status'=>'approved'), array('id'=>$id), array('%s'), array('%d'));

        $this->assertNotNull($repo->find_approved_for_project_version($this->project_id, $this->tenant, 7));
        $this->assertNull($repo->find_approved_for_project_version($this->project_id, $this->tenant, 8));
        $this->assertNull($repo->find_approved_for_project_version($this->project_id, $this->tenant + 1, 7));
    }

    public function test_delivery_versions_are_monotonic_and_only_current_published_version_is_latest() {
        $service = gdcp_delivery_service();
        $project = gd_client_portal_get_project_by_id($this->project_id);
        $first = $service->publish($project, array('title'=>'v1','description'=>'first','file_url'=>'https://example.test/v1','status'=>'published','uploaded_by'=>$this->user,'created_at'=>current_time('mysql')));
        $second = $service->publish($project, array('title'=>'v2','description'=>'second','file_url'=>'https://example.test/v2','status'=>'published','uploaded_by'=>$this->user,'created_at'=>current_time('mysql')));
        $this->assertGreaterThan(0, $first);
        $this->assertGreaterThan($first, $second);
        $latest = $service->latest($this->project_id, true);
        $this->assertSame(2, (int)$latest->version);
        $this->assertSame('published', $latest->status);
        $versions = $service->versions($this->project_id, false);
        $statuses = array();
        foreach ($versions as $version) $statuses[(int)$version->version] = $version->status;
        $this->assertSame('superseded', $statuses[1]);
        $this->assertSame('published', $statuses[2]);
    }
}
