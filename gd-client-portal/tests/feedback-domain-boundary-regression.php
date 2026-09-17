<?php
if(!defined('ABSPATH')) exit;
function gdcp_feedback_domain_boundary_regression(){
    $file=dirname(__DIR__).'/includes/feedback.php';
    $src=file_get_contents($file);
    return array(
        'service_loaded'=>function_exists('gdcp_feedback_service'),
        'no_direct_review_write'=>strpos($src,'$wpdb->insert(gd_client_portal_feedback_table(\'reviews\')')===false,
        'no_direct_response_write'=>strpos($src,'$wpdb->insert(gd_client_portal_feedback_table(\'responses\')')===false,
        'review_read_boundary'=>strpos($src,'gdcp_feedback_service()->get_review_for_access')!==false,
    );
}
