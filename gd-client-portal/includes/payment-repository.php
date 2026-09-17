<?php
if (!defined('ABSPATH')) exit;

final class GDCP_Payment_Repository {
    public function payments_table(){ return gd_client_portal_payment_table(); }
    public function find_by_reference($reference,$lock=false){ global $wpdb; $sql='SELECT * FROM '.$this->payments_table().' WHERE reference=%s LIMIT 1'.($lock?' FOR UPDATE':''); return $wpdb->get_row($wpdb->prepare($sql,sanitize_text_field($reference))); }
    public function find($id){ global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->payments_table().' WHERE id=%d LIMIT 1',absint($id))); }
    public function insert(array $data){
        global $wpdb;
        $formats=array('%d','%d','%d','%d','%f','%s','%s','%s','%s','%s','%s','%s','%s');
        if(false===$wpdb->insert($this->payments_table(),$data,$formats)) return 0;
        return absint($wpdb->insert_id);
    }
    public function find_for_invoice($invoice_id){ global $wpdb; return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.$this->payments_table().' WHERE invoice_id=%d ORDER BY id DESC',absint($invoice_id))); }
}
