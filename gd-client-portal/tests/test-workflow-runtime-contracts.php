<?php

/**
 * Runtime workflow tests for the project lifecycle boundary.
 * Requires the disposable WordPress + MySQL PHPUnit environment.
 */
class GDCP_Workflow_Runtime_Contracts_Test extends WP_UnitTestCase {
    private $tenant = 9501;
    private $user = 0;
    private $other_user = 0;
    private $project_id = 0;

    public function setUp(): void {
        parent::setUp();
        global $wpdb;

        update_option('gd_client_portal_tenants', array(
            array('id'=>$this->tenant,'name'=>'Runtime Workflow Tenant','slug'=>'runtime-workflow','status'=>'active'),
            array('id'=>$this->tenant + 1,'name'=>'Other Workflow Tenant','slug'=>'runtime-workflow-other','status'=>'active'),
        ));

        $this->user = self::factory()->user->create();
        $this->other_user = self::factory()->user->create();
        gdcp_repository('tenant')->set_user_tenant($this->user, $this->tenant);
        gdcp_repository('tenant')->set_user_tenant($this->other_user, $this->tenant + 1);
        wp_set_current_user($this->user);

        $wpdb->insert($wpdb->prefix.'gd_projects', array(
            'tenant_id'=>$this->tenant,'order_id'=>0,'user_id'=>$this->user,'product_id'=>0,
            'service_type'=>'software_engineering','title'=>'Runtime Workflow Project','description'=>'Lifecycle test',
            'status'=>'active','current_stage'=>'intake','progress'=>0,'created_at'=>current_time('mysql'),
        ), array('%d','%d','%d','%d','%s','%s','%s','%s','%d','%s'));
        $this->project_id = (int)$wpdb->insert_id;
    }

    public function tearDown(): void {
        wp_set_current_user(0);
        if ($this->user) wp_delete_user($this->user);
        if ($this->other_user) wp_delete_user($this->other_user);
        parent::tearDown();
    }

    public function test_complete_workflow_sequence_updates_stage_and_progress() {
        $service = gdcp_service('project');
        $workflow = gd_client_portal_get_workflow('software_engineering');
        $this->assertSame(array('intake','requirement_analysis','system_design','development','testing','deployment','completed'), $workflow);

        foreach ($workflow as $stage) {
            $result = $service->update_stage($this->project_id, $stage);
            $this->assertSame(1, $result, 'Workflow stage should transition: '.$stage);
            $project = $service->get($this->project_id);
            $this->assertSame($stage, $project->current_stage);
            $this->assertSame(gd_client_portal_auto_progress('software_engineering', $stage), (int)$project->progress);
        }

        $final = $service->get($this->project_id);
        $this->assertSame('completed', $final->current_stage);
        $this->assertSame(100, (int)$final->progress);
    }

    public function test_invalid_stage_is_rejected_without_mutating_project() {
        $service = gdcp_service('project');
        $before = $service->get($this->project_id);
        $result = $service->update_stage($this->project_id, 'not-a-real-stage');
        $after = $service->get($this->project_id);

        $this->assertFalse($result);
        $this->assertSame($before->current_stage, $after->current_stage);
        $this->assertSame((int)$before->progress, (int)$after->progress);
    }

    public function test_stage_progress_is_clamped_to_zero_to_hundred() {
        $service = gdcp_service('project');
        $this->assertSame(1, $service->update_stage($this->project_id, 'development', 999));
        $project = $service->get($this->project_id);
        $this->assertSame(100, (int)$project->progress);

        $this->assertSame(1, $service->update_stage($this->project_id, 'testing', -20));
        $project = $service->get($this->project_id);
        $this->assertSame(0, (int)$project->progress);
    }

    public function test_same_stage_retry_does_not_emit_a_second_stage_change_event() {
        $service = gdcp_service('project');
        $events = 0;
        $callback = function() use (&$events) { $events++; };
        add_action('gd_client_portal_project_stage_changed', $callback, 1, 3);

        $this->assertSame(1, $service->update_stage($this->project_id, 'development'));
        $this->assertSame(1, $events);
        $this->assertSame(1, $service->update_stage($this->project_id, 'development'));
        $this->assertSame(1, $events);

        remove_action('gd_client_portal_project_stage_changed', $callback, 1);
    }

    public function test_cross_tenant_user_cannot_transition_project_stage() {
        wp_set_current_user($this->other_user);
        $service = gdcp_service('project');
        $this->assertFalse($service->update_stage($this->project_id, 'development'));

        wp_set_current_user($this->user);
        $project = $service->get($this->project_id);
        $this->assertSame('intake', $project->current_stage);
        $this->assertSame(0, (int)$project->progress);
    }
}
