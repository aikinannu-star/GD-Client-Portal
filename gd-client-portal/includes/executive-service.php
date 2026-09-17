<?php
if(!defined('ABSPATH')) exit;
final class GDCP_Executive_Analytics_Service {
    private $repo;
    public function __construct(){ $this->repo=class_exists('GDCP_Repository_Registry')?gdcp_repository('executive'):new GDCP_Executive_Analytics_Repository(); }
    public function financial_metrics($scope){return $this->repo->financial_metrics($scope);}
    public function support_metrics($scope){return $this->repo->support_metrics($scope);}
    public function feedback_metrics($scope){return $this->repo->feedback_metrics($scope);}
    public function accepted_contracts($scope){return $this->repo->count_by_status('contracts','accepted',$scope);}
    public function documents($scope){return $this->repo->count_by_status('documents',null,$scope);}
    public function open_tasks($scope){return $this->repo->open_tasks($scope);}
    public function monthly_revenue($scope,$start){return $this->repo->monthly_revenue($scope,$start);}
    public function latest_currency(){return $this->repo->latest_currency();}
}
function gdcp_executive_service(){static $s=null;if(!$s)$s=new GDCP_Executive_Analytics_Service();return $s;}
