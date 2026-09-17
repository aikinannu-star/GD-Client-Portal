<?php
if (!defined('ABSPATH')) exit;

function gdcp_render_project_completion_summary($atts=array()) {
    if (!is_user_logged_in()) {
        return '<div class="gd-completion-summary"><h2>Project Completion</h2><p>Please log in to review your project completion summary.</p></div>';
    }
    $atts=shortcode_atts(array('project_id'=>0),$atts,'gd_project_completion');
    $project_id=absint($atts['project_id'] ?: (isset($_GET['project_id']) ? $_GET['project_id'] : 0));
    if (!$project_id || !function_exists('gd_client_portal_get_project_by_id')) {
        return '<div class="gd-completion-summary"><h2>Project Completion</h2><p>Select a project to review its final delivery and next steps.</p></div>';
    }
    $project=gd_client_portal_get_project_by_id($project_id);
    if (!$project || !function_exists('gd_client_portal_can_access_project') || !gd_client_portal_can_access_project($project)) {
        return '<div class="gd-completion-summary"><h2>Project Completion</h2><p>Project access denied.</p></div>';
    }
    $checks=array(
        'Project workspace'=>true,
        'Client access'=>true,
        'Final workflow review'=>true,
        'Billing follow-up'=>function_exists('gd_client_portal_billing_get_invoices'),
        'Support handover'=>function_exists('gd_client_portal_support_tickets'),
    );
    ob_start(); ?>
    <div class="gd-completion-summary">
        <h2><?php echo esc_html__('Project Completion','gd-client-portal'); ?></h2>
        <p><?php echo esc_html__('Review the final project state and available post-delivery support options.','gd-client-portal'); ?></p>
        <h3><?php echo esc_html($project->title); ?></h3>
        <ul class="gd-completion-checklist">
        <?php foreach($checks as $label=>$ok): ?>
            <li><?php echo $ok ? '✓' : '•'; ?> <?php echo esc_html($label); ?></li>
        <?php endforeach; ?>
        </ul>
        <div class="gd-completion-next"><strong><?php echo esc_html__('What happens next?','gd-client-portal'); ?></strong>
        <p><?php echo esc_html__('Review outstanding billing and use Support whenever further assistance is needed.','gd-client-portal'); ?></p></div>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('gd_project_completion','gdcp_render_project_completion_summary');
