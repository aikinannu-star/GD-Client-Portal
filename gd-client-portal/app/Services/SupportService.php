<?php
if (!defined('ABSPATH')) exit;
final class GDCP_Support_Service {
    public function ticket($id){ $repo=gdcp_repository('support'); return $repo ? $repo->ticket($id) : null; }
    public function tickets($limit=100){ $repo=gdcp_repository('support'); return $repo ? $repo->tickets($limit) : array(); }
    public function can_view($id){ return gdcp_can('view','support',$id); }
}
