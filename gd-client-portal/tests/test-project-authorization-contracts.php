<?php

class GDCP_Project_Authorization_Contracts_Test extends WP_UnitTestCase {
    private $tenants_backup;

    public function setUp(): void {
        parent::setUp();
        $this->tenants_backup = get_option('gd_client_portal_tenants', null);
        update_option('gd_client_portal_tenants', array(
            array('id'=>9201,'name'=>'Project Tenant A','slug'=>'project-a','status'=>'active'),
            array('id'=>9202,'name'=>'Project Tenant B','slug'=>'project-b','status'=>'active'),
        ));
    }

    public function tearDown(): void {
        if ($this->tenants_backup === null) delete_option('gd_client_portal_tenants');
        else update_option('gd_client_portal_tenants', $this->tenants_backup);
        wp_set_current_user(0);
        parent::tearDown();
    }

    public function test_project_persistence_contract_is_available() {
        global $wpdb;
        $table = $wpdb->prefix . 'gd_projects';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        $this->assertSame($table, $exists, 'Projects table must exist in the WordPress test database.');
    }

    public function test_project_authorization_runtime_is_exposed() {
        $this->assertTrue(function_exists('gdcp_can'));
    }

    public function test_regular_user_cannot_view_project_from_another_tenant() {
        global $wpdb;
        $user_a = self::factory()->user->create();
        $user_b = self::factory()->user->create();
        gdcp_repository('tenant')->set_user_tenant($user_a, 9201);
        gdcp_repository('tenant')->set_user_tenant($user_b, 9202);

        $wpdb->insert($wpdb->prefix.'gd_projects', array(
            'tenant_id'=>9202,'order_id'=>0,'user_id'=>$user_b,'product_id'=>0,
            'service_type'=>'general','title'=>'Tenant B Project','description'=>'Isolation test',
            'status'=>'active','current_stage'=>'intake','progress'=>0,'created_at'=>current_time('mysql'),
        ), array('%d','%d','%d','%d','%s','%s','%s','%s','%d','%s'));
        $project_id = (int)$wpdb->insert_id;

        wp_set_current_user($user_a);
        $this->assertFalse(gdcp_can('view','project',$project_id));

        wp_set_current_user($user_b);
        $this->assertTrue(gdcp_can('view','project',$project_id));
    }
}
