<?php
if (!defined('ABSPATH')) exit;

abstract class GDCP_Service_Base {
    protected function db(){ global $wpdb; return $wpdb; }
    protected function begin(){ return $this->db()->query('START TRANSACTION') !== false; }
    protected function commit(){ return $this->db()->query('COMMIT') !== false; }
    protected function rollback(){ $this->db()->query('ROLLBACK'); }
    protected function transaction($callback){
        if(!is_callable($callback)) return false;
        $started=$this->begin();
        try { $result=call_user_func($callback); if($result===false){ if($started)$this->rollback(); return false; } if($started && !$this->commit()) return false; return $result; }
        catch(Throwable $e){ if($started)$this->rollback(); return $this->fail($e->getMessage()); }
    }
    protected function actor(){ return get_current_user_id(); }
    protected function fail($message){ if(function_exists('error_log')) error_log('[GD Client Portal] Service error: '.sanitize_text_field($message)); return false; }
}
