<?php
/** Runtime notification/event idempotency tests. */
class GDCP_Notification_Idempotency_Runtime_Contracts_Test extends WP_UnitTestCase {
    private $user_ids = array();
    public function tearDown(): void {
        global $wpdb;
        $table = function_exists('gdcp_notifications_repository') ? gdcp_notifications_repository()->table() : '';
        if ($table) foreach ($this->user_ids as $uid) $wpdb->delete($table, array('user_id'=>$uid), array('%d'));
        foreach ($this->user_ids as $uid) wp_delete_user($uid);
        wp_set_current_user(0);
        parent::tearDown();
    }
    public function test_same_user_event_key_is_idempotent() {
        $uid = self::factory()->user->create(); $this->user_ids[] = $uid;
        $service = gdcp_notification_service();
        $a = $service->create($uid, 'Runtime event', 'first', 'runtime', 0, 0, 'runtime-event-1');
        $b = $service->create($uid, 'Runtime event', 'duplicate', 'runtime', 0, 0, 'runtime-event-1');
        $this->assertGreaterThan(0, $a);
        $this->assertSame((int)$a, (int)$b);
        $this->assertCount(1, $service->for_user($uid, 100));
    }
    public function test_event_keys_are_scoped_per_user() {
        $a = self::factory()->user->create(); $b = self::factory()->user->create(); $this->user_ids = array($a,$b);
        $service = gdcp_notification_service();
        $first = $service->create($a, 'Shared event', '', 'runtime', 0, 0, 'shared-event');
        $second = $service->create($b, 'Shared event', '', 'runtime', 0, 0, 'shared-event');
        $this->assertGreaterThan(0, $first); $this->assertGreaterThan(0, $second);
        $this->assertNotSame((int)$first, (int)$second);
    }
    public function test_wrong_tenant_notification_is_rejected() {
        $uid = self::factory()->user->create(); $this->user_ids[] = $uid;
        update_user_meta($uid, 'gd_client_portal_tenant_id', 1001);
        $this->assertFalse(gdcp_notification_service()->create($uid, 'Denied', '', 'security', 0, 1002, 'tenant-mismatch'));
    }
}
