<?php
if (!defined('ABSPATH')) exit;
final class GDCP_Document_Service {
    public function get($id){ $repo=gdcp_repository('document'); return $repo ? $repo->find($id) : null; }
    public function project_documents($project_id,$limit=200){ $repo=gdcp_repository('document'); return $repo ? $repo->for_project($project_id,$limit) : array(); }
    public function can_view($id){ return gdcp_can('view','document',$id); }
}
