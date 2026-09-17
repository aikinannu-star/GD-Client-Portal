<?php
if (!defined('ABSPATH')) exit;
final class GDCP_Authorization_Service {
    public function can($action,$object,$id=0,$args=array()){ return gdcp_can($action,$object,$id,$args); }
    public function project($action,$id,$args=array()){ return $this->can($action,'project',$id,$args); }
    public function tenant($action,$id,$args=array()){ return $this->can($action,'tenant',$id,$args); }
}
