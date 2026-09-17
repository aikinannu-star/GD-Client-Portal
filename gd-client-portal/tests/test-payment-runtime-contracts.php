<?php

class GDCP_Payment_Runtime_Contracts_Test extends WP_UnitTestCase {
    private $invoice_ids = array();
    private $user_ids = array();

    public function setUp(): void {
        parent::setUp();
        $this->ensure_tables();
    }

    public function tearDown(): void {
        global $wpdb;
        $invoice_table = gd_client_portal_billing_invoices_table();
        $payment_table = gd_client_portal_payment_table();
        foreach ($this->invoice_ids as $invoice_id) {
            $wpdb->delete($payment_table, array('invoice_id' => $invoice_id), array('%d'));
            $wpdb->delete($invoice_table, array('id' => $invoice_id), array('%d'));
        }
        foreach ($this->user_ids as $user_id) {
            wp_delete_user($user_id);
        }
        wp_set_current_user(0);
        parent::tearDown();
    }

    private function ensure_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $invoices = gd_client_portal_billing_invoices_table();
        $payments = gd_client_portal_payment_table();
        dbDelta("CREATE TABLE $invoices (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,invoice_number varchar(50) NOT NULL,tenant_id bigint(20) unsigned NOT NULL DEFAULT 0,user_id bigint(20) unsigned NOT NULL DEFAULT 0,project_id bigint(20) unsigned NOT NULL DEFAULT 0,order_id bigint(20) unsigned NOT NULL DEFAULT 0,quote_id bigint(20) unsigned NOT NULL DEFAULT 0,currency varchar(10) NOT NULL DEFAULT 'GHS',subtotal decimal(20,2) NOT NULL DEFAULT 0.00,tax decimal(20,2) NOT NULL DEFAULT 0.00,total decimal(20,2) NOT NULL DEFAULT 0.00,amount_paid decimal(20,2) NOT NULL DEFAULT 0.00,due_date date NULL,status varchar(30) NOT NULL DEFAULT 'unpaid',payment_reference varchar(100) NULL,description text NULL,created_at datetime DEFAULT CURRENT_TIMESTAMP,updated_at datetime DEFAULT CURRENT_TIMESTAMP,paid_at datetime NULL,PRIMARY KEY(id),UNIQUE KEY invoice_number(invoice_number),KEY tenant_id(tenant_id),KEY user_id(user_id),KEY project_id(project_id),KEY order_id(order_id),KEY status(status)) $charset;");
        dbDelta("CREATE TABLE $payments (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,invoice_id bigint(20) unsigned NOT NULL DEFAULT 0,tenant_id bigint(20) unsigned NOT NULL DEFAULT 0,user_id bigint(20) unsigned NOT NULL DEFAULT 0,project_id bigint(20) unsigned NOT NULL DEFAULT 0,amount decimal(20,2) NOT NULL DEFAULT 0.00,currency varchar(10) NOT NULL DEFAULT 'GHS',gateway varchar(40) NOT NULL DEFAULT 'manual',reference varchar(120) NOT NULL,channel varchar(60) NULL,status varchar(30) NOT NULL DEFAULT 'success',gateway_status varchar(60) NULL,metadata longtext NULL,created_at datetime DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY reference(reference),KEY invoice_id(invoice_id),KEY tenant_id(tenant_id),KEY user_id(user_id),KEY project_id(project_id),KEY status(status)) $charset;");
    }

    private function invoice($total = 100.00, $currency = 'GHS') {
        global $wpdb;
        $user_id = self::factory()->user->create();
        $this->user_ids[] = $user_id;
        $table = gd_client_portal_billing_invoices_table();
        $wpdb->insert($table, array(
            'invoice_number' => 'RUNTIME-' . wp_generate_password(10, false, false),
            'tenant_id' => 9101,
            'user_id' => $user_id,
            'project_id' => 7001,
            'currency' => $currency,
            'subtotal' => $total,
            'tax' => 0,
            'total' => $total,
            'amount_paid' => 0,
            'status' => 'unpaid',
            'description' => 'Runtime payment test',
        ));
        $id = absint($wpdb->insert_id);
        $this->invoice_ids[] = $id;
        return $id;
    }

    public function test_exact_reference_replay_is_idempotent() {
        $invoice_id = $this->invoice();
        $first = gdcp_payment_service()->record($invoice_id, 40, 'RUNTIME-REF-1');
        $second = gdcp_payment_service()->record($invoice_id, 40, 'RUNTIME-REF-1');

        $this->assertIsArray($first);
        $this->assertTrue($first['created']);
        $this->assertIsArray($second);
        $this->assertFalse($second['created']);
        $this->assertSame($first['id'], $second['id']);
        $this->assertEquals(40.0, (float) $second['invoice']->amount_paid);
    }

    public function test_reference_cannot_be_replayed_for_another_invoice() {
        $invoice_a = $this->invoice();
        $invoice_b = $this->invoice();
        $first = gdcp_payment_service()->record($invoice_a, 25, 'RUNTIME-REF-CONFLICT');
        $second = gdcp_payment_service()->record($invoice_b, 25, 'RUNTIME-REF-CONFLICT');

        $this->assertIsArray($first);
        $this->assertWPError($second);
        $this->assertSame('payment_reference_conflict', $second->get_error_code());
    }

    public function test_reference_replay_with_different_amount_is_rejected() {
        $invoice_id = $this->invoice();
        $first = gdcp_payment_service()->record($invoice_id, 25, 'RUNTIME-REF-AMOUNT');
        $second = gdcp_payment_service()->record($invoice_id, 30, 'RUNTIME-REF-AMOUNT');

        $this->assertIsArray($first);
        $this->assertWPError($second);
        $this->assertSame('payment_amount_conflict', $second->get_error_code());
    }

    public function test_payment_amount_is_capped_at_outstanding_balance() {
        $invoice_id = $this->invoice(100);
        $result = gdcp_payment_service()->record($invoice_id, 150, 'RUNTIME-REF-BALANCE');

        $this->assertIsArray($result);
        $this->assertTrue($result['created']);
        $this->assertEquals(100.0, (float) $result['payment']->amount);
        $this->assertEquals(100.0, (float) $result['invoice']->amount_paid);
        $this->assertSame('paid', $result['invoice']->status);
    }

    public function test_payment_currency_is_inherited_from_invoice() {
        $invoice_id = $this->invoice(80, 'USD');
        $result = gdcp_payment_service()->record($invoice_id, 20, 'RUNTIME-REF-CURRENCY');

        $this->assertIsArray($result);
        $this->assertSame('USD', strtoupper($result['payment']->currency));
        $this->assertSame('USD', strtoupper($result['invoice']->currency));
    }
}
