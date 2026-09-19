<?php if (!defined('ABSPATH')) { exit; } ?>
<?php
$projects = function_exists('gd_client_portal_get_visible_projects') ? gd_client_portal_get_visible_projects(50) : array();
$deliverables = array();
if (function_exists('gdcp_delivery_service')) {
    foreach ($projects as $project) {
        $latest = gdcp_delivery_service()->latest(absint($project->id), true);
        if ($latest) { $deliverables[] = array('project'=>$project, 'delivery'=>$latest); }
    }
}
?>
<div class="gd-module-detail gd-deliverables-detail">
    <div class="gd-module-summary-row">
        <div><p class="gd-module-eyebrow"><?php esc_html_e('Milestones', 'gd-client-portal'); ?></p><h2><?php esc_html_e('Deliverables', 'gd-client-portal'); ?></h2></div>
        <span class="gd-pill gd-pill-success"><?php echo esc_html(sprintf(_n('%d ready', '%d ready', count($deliverables), 'gd-client-portal'), count($deliverables))); ?></span>
    </div>
    <?php if (empty($deliverables)) : ?>
        <div class="gd-empty-state"><strong><?php esc_html_e('No published deliverables yet', 'gd-client-portal'); ?></strong><p><?php esc_html_e('Completed project outputs will appear here when your service team publishes them.', 'gd-client-portal'); ?></p></div>
    <?php else : ?>
        <div class="gd-deliverable-stack">
        <?php foreach ($deliverables as $item) : $d=$item['delivery']; $project=$item['project']; ?>
            <article class="gd-deliverable-item">
                <div class="gd-deliverable-head"><strong><?php echo esc_html($project->title); ?></strong><span><?php echo esc_html__('Published', 'gd-client-portal'); ?><?php if (!empty($d->version)) echo ' · v'.esc_html($d->version); ?></span></div>
                <p><?php echo esc_html($d->title ?? $d->description ?? __('Latest project delivery', 'gd-client-portal')); ?></p>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
