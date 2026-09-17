<?php
/** Runtime WooCommerce integration contracts. */
class GDCP_WooCommerce_Runtime_Contracts_Test extends WP_UnitTestCase {
    public function test_woocommerce_runtime_hooks_are_registered_when_available() {
        if (!class_exists('WooCommerce')) $this->markTestSkipped('WooCommerce is not installed in this disposable runtime.');
        $this->assertTrue(has_action('woocommerce_payment_complete', 'gd_client_portal_woo_automation_on_paid') !== false);
    }
    public function test_order_without_service_product_is_non_destructive() {
        if (!function_exists('wc_create_order')) $this->markTestSkipped('WooCommerce is not installed in this disposable runtime.');
        $order = wc_create_order();
        $this->assertNotEmpty($order->get_id());
        $before = $order->get_meta('_gd_client_portal_project_ids', true);
        $result = gd_client_portal_woo_automation_create_projects($order->get_id());
        $this->assertIsArray($result);
        $this->assertSame($before, $order->get_meta('_gd_client_portal_project_ids', true));
        $order->delete(true);
    }
    public function test_unpaid_order_is_not_entered_into_project_workflow() {
        if (!function_exists('wc_create_order')) $this->markTestSkipped('WooCommerce is not installed in this disposable runtime.');
        $order = wc_create_order();
        $order->set_status('pending'); $order->save();
        $result = gd_client_portal_woo_automation_create_projects($order->get_id());
        $this->assertSame(array(), $result);
        $this->assertNotSame('project_created', $order->get_meta('_gd_client_portal_automation_status', true));
        $order->delete(true);
    }
}
