<?php
if (!defined('ABSPATH')) exit;
final class GDCP_Support_Controller extends GDCP_Controller_Base {
    public function authorize($ticket_id,$action='view'){ return gdcp_can($action,'support',absint($ticket_id)); }
}
