<?php
if (!defined('ABSPATH')) exit;
final class GDCP_Notification_Repository extends GDCP_Base_Repository {
    public function table() { return function_exists('gd_client_portal_notification_table') ? gd_client_portal_notification_table() : $this->db()->prefix.'gd_client_portal_notifications'; }
    public function for_user($user_id = 0, $limit = 50, $unread = false) {
        $user_id = $user_id ? absint($user_id) : get_current_user_id(); if (!$user_id) return array();
        $sql='SELECT * FROM '.$this->table().' WHERE user_id=%d'; $args=array($user_id);
        if ($unread) $sql.=' AND is_read=0'; $sql.=' ORDER BY created_at DESC,id DESC LIMIT %d'; $args[]=max(1,min(200,absint($limit)));
        return $this->db()->get_results($this->db()->prepare($sql,$args));
    }
    public function find($id) { return $this->db()->get_row($this->db()->prepare('SELECT * FROM '.$this->table().' WHERE id=%d AND user_id=%d',absint($id),get_current_user_id())); }
}
