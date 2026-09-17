<?php

class GDCP_Tenant_Isolation_Contracts_Test extends WP_UnitTestCase {
    private $tenants_backup;

    public function setUp(): void {
        parent::setUp();
        $this->tenants_backup = get_option('gd_client_portal_tenants', null);
        update_option('gd_client_portal_tenants', array(
            array('id'=>9101,'name'=>'Runtime Tenant A','slug'=>'runtime-a','status'=>'active'),
            array('id'=>9102,'name'=>'Runtime Tenant B','slug'=>'runtime-b','status'=>'active'),
        ));
    }

    public function tearDown(): void {
        if ($this->tenants_backup === null) delete_option('gd_client_portal_tenants');
        else update_option('gd_client_portal_tenants', $this->tenants_backup);
        wp_set_current_user(0);
        parent::tearDown();
    }

    public function test_tenant_authorization_contract_is_present() {
        $this->assertTrue(function_exists('gdcp_tenant_can_access'));
    }

    public function test_distinct_test_users_can_be_created() {
        $user_a = self::factory()->user->create();
        $user_b = self::factory()->user->create();
        $this->assertNotSame($user_a, $user_b);
    }

    public function test_cross_user_identity_is_distinct() {
        $user_a = self::factory()->user->create();
        $user_b = self::factory()->user->create();
        wp_set_current_user($user_a);
        $this->assertSame($user_a, get_current_user_id());
        wp_set_current_user($user_b);
        $this->assertSame($user_b, get_current_user_id());
        $this->assertNotSame($user_a, $user_b);
    }

    public function test_user_tenant_assignment_isolated_and_inactive_tenant_fails_closed() {
        $user_a = self::factory()->user->create();
        $user_b = self::factory()->user->create();
        $repo = gdcp_repository('tenant');

        $this->assertTrue($repo->set_user_tenant($user_a, 9101));
        $this->assertTrue($repo->set_user_tenant($user_b, 9102));

        wp_set_current_user($user_a);
        $this->assertSame(9101, $repo->user_tenant($user_a));
        $this->assertTrue(gdcp_tenant_can_access(9101));
        $this->assertFalse(gdcp_tenant_can_access(9102));

        $tenants = get_option('gd_client_portal_tenants');
        $tenants[1]['status'] = 'inactive';
        update_option('gd_client_portal_tenants', $tenants);
        $this->assertSame(0, $repo->user_tenant($user_b));
    }
}
