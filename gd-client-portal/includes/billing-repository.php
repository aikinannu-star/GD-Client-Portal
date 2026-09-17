<?php
if (!defined('ABSPATH')) exit;

final class GDCP_Billing_Repository {
    public function quotes_table(){ return gd_client_portal_billing_quotes_table(); }
    public function invoices_table(){ return gd_client_portal_billing_invoices_table(); }
    public function find_quote($id,$lock=false){ global $wpdb; $sql='SELECT * FROM '.$this->quotes_table().' WHERE id=%d'.($lock?' FOR UPDATE':'').' LIMIT 1'; return $wpdb->get_row($wpdb->prepare($sql,absint($id))); }
    public function find_invoice($id,$lock=false){ global $wpdb; $sql='SELECT * FROM '.$this->invoices_table().' WHERE id=%d'.($lock?' FOR UPDATE':'').' LIMIT 1'; return $wpdb->get_row($wpdb->prepare($sql,absint($id))); }
    public function find_invoice_by_quote($quote_id,$lock=false){ global $wpdb; $sql='SELECT * FROM '.$this->invoices_table().' WHERE quote_id=%d LIMIT 1'.($lock?' FOR UPDATE':''); return $wpdb->get_row($wpdb->prepare($sql,absint($quote_id))); }
    public function insert_quote(array $data){ global $wpdb; $formats=array('%s','%d','%d','%d','%s','%s','%s','%f','%s','%d','%s','%s'); if(false===$wpdb->insert($this->quotes_table(),$data,$formats)) return 0; return absint($wpdb->insert_id); }
    public function update_quote($id,array $data){ global $wpdb; return false!==$wpdb->update($this->quotes_table(),$data,array('id'=>absint($id)),array('%s','%s'),array('%d')); }
    public function list_outstanding_for_scope($uid,$tenant,$tenant_admin,$platform,$limit=50){
        $where="status IN ('unpaid','part_paid')"; $args=array();
        if(!$platform){$where.=' AND tenant_id=%d';$args[]=absint($tenant);if(!$tenant_admin){$where.=' AND user_id=%d';$args[]=absint($uid);}}
        $args[]=max(1,min(300,absint($limit)));
        return $this->db()->get_results($this->db()->prepare('SELECT id,invoice_number,status,total,amount_paid,currency,due_date,updated_at,project_id FROM '.$this->invoices_table().' WHERE '.$where.' ORDER BY due_date IS NULL ASC,due_date ASC,id DESC LIMIT %d',$args));
    }

    public function insert_invoice(array $data){ global $wpdb; $formats=array('%s','%d','%d','%d','%d','%s','%f','%f','%f','%f','%s','%s','%s','%s','%s'); if(false===$wpdb->insert($this->invoices_table(),$data,$formats)) return 0; return absint($wpdb->insert_id); }
    public function find_latest_quote_for_project($project_id,$lock=false){ global $wpdb; $sql='SELECT * FROM '.$this->quotes_table().' WHERE project_id=%d ORDER BY id DESC LIMIT 1'.($lock?' FOR UPDATE':''); return $wpdb->get_row($wpdb->prepare($sql,absint($project_id))); }
    public function find_latest_invoice_for_project($project_id,$lock=false){ global $wpdb; $sql='SELECT * FROM '.$this->invoices_table().' WHERE project_id=%d ORDER BY id DESC LIMIT 1'.($lock?' FOR UPDATE':''); return $wpdb->get_row($wpdb->prepare($sql,absint($project_id))); }
    public function find_invoice_by_order($order_id,$lock=false){ global $wpdb; $sql='SELECT * FROM '.$this->invoices_table().' WHERE order_id=%d LIMIT 1'.($lock?' FOR UPDATE':''); return $wpdb->get_row($wpdb->prepare($sql,absint($order_id))); }

    // Compatibility aliases for the application-layer repository API.
    public function invoice($id){ return $this->find_invoice($id); }
    public function invoices($limit=100){ global $wpdb; $limit=max(1,min(300,absint($limit))); return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.$this->invoices_table().' ORDER BY created_at DESC,id DESC LIMIT %d',$limit)); }
    public function insert(array $data){ return $this->insert_invoice($data); }
    public function update_invoice($id,array $data){ global $wpdb; $formats=array(); foreach($data as $key=>$value){ $formats[]=in_array($key,array('tenant_id','user_id','project_id','order_id','quote_id'),true)?'%d':(in_array($key,array('subtotal','tax','total','amount_paid'),true)?'%f':'%s'); } return false!==$wpdb->update($this->invoices_table(),$data,array('id'=>absint($id)),$formats,array('%d')); }
}
