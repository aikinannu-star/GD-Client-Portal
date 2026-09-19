<?php if (!defined('ABSPATH')) { exit; } ?>
<?php
$projects = function_exists('gd_client_portal_get_visible_projects') ? gd_client_portal_get_visible_projects(20) : array();
$active = array_values(array_filter($projects, function($p){ return !in_array((string)($p->status ?? ''), array('completed','closed','cancelled'), true); }));
$current = $active[0] ?? ($projects[0] ?? null);
$stages = $current ? (function_exists('gd_client_portal_get_workflow') ? gd_client_portal_get_workflow(gd_client_portal_get_service_type($current->product_id ?? 0)) : array()) : array();
$current_stage = $current ? (string)($current->current_stage ?? '') : '';
$current_index = $current_stage !== '' ? array_search($current_stage, $stages, true) : false;
if ($current_index === false) $current_index = 0;
?>
<div class="gd-module-detail gd-workflow-detail">
    <div class="gd-module-summary-row gd-workflow-summary">
        <div><p class="gd-module-eyebrow"><?php esc_html_e('Project delivery', 'gd-client-portal'); ?></p><h2><?php echo esc_html($current ? $current->title : __('Workflow Overview', 'gd-client-portal')); ?></h2></div>
        <span class="gd-pill gd-pill-success"><?php echo esc_html($current ? max(0,min(100,absint($current->progress ?? 0))).'% complete' : __('No active project', 'gd-client-portal')); ?></span>
    </div>
    <?php if (!$current || empty($stages)) : ?>
        <div class="gd-empty-state"><strong><?php esc_html_e('No active workflow', 'gd-client-portal'); ?></strong><p><?php esc_html_e('A project workflow will appear here when a project is assigned to your account.', 'gd-client-portal'); ?></p></div>
    <?php else : ?>
    <div class="gd-workflow-grid">
        <?php foreach ($stages as $index => $stage) :
            $is_current = $index === $current_index;
            $is_active = $index < $current_index;
        ?>
        <div class="gd-workflow-stage <?php echo $is_current ? 'is-current' : ($is_active ? 'is-active' : ''); ?>">
            <span class="gd-workflow-number"><?php echo esc_html(str_pad((string)($index+1),2,'0',STR_PAD_LEFT)); ?></span>
            <strong><?php echo esc_html(ucwords(str_replace('_',' ',(string)$stage))); ?></strong>
            <small><?php echo esc_html($is_current ? __('Current', 'gd-client-portal') : ($is_active ? __('Completed', 'gd-client-portal') : __('Upcoming', 'gd-client-portal'))); ?></small>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
