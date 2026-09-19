<?php if (!defined('ABSPATH')) { exit; } ?>
<?php
$projects = function_exists('gd_client_portal_get_visible_projects') ? gd_client_portal_get_visible_projects(50) : array();
$files = array();
foreach ($projects as $project) {
    if (!empty($project->file_url)) {
        $files[] = array('project'=>$project, 'url'=>gd_client_portal_private_project_file_url($project->id));
    }
}
?>
<div class="gd-module-detail gd-files-detail">
    <div class="gd-module-summary-row">
        <div><p class="gd-module-eyebrow"><?php esc_html_e('Asset library', 'gd-client-portal'); ?></p><h2><?php esc_html_e('Files', 'gd-client-portal'); ?></h2></div>
        <span class="gd-pill"><?php echo esc_html(sprintf(_n('%d file', '%d files', count($files), 'gd-client-portal'), count($files))); ?></span>
    </div>
    <?php if (empty($files)) : ?>
        <div class="gd-empty-state"><strong><?php esc_html_e('No project files yet', 'gd-client-portal'); ?></strong><p><?php esc_html_e('Files published to your projects will appear here.', 'gd-client-portal'); ?></p></div>
    <?php else : ?>
        <div class="gd-module-table-wrap"><table class="gd-module-table"><thead><tr><th><?php esc_html_e('Project', 'gd-client-portal'); ?></th><th><?php esc_html_e('Resource', 'gd-client-portal'); ?></th><th><?php esc_html_e('Access', 'gd-client-portal'); ?></th></tr></thead><tbody>
        <?php foreach ($files as $file) : ?><tr><td><?php echo esc_html($file['project']->title); ?></td><td><?php esc_html_e('Project file', 'gd-client-portal'); ?></td><td><a href="<?php echo esc_url($file['url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open file', 'gd-client-portal'); ?></a></td></tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div>
