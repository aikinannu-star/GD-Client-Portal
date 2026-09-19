<?php if (!defined('ABSPATH')) { exit; } ?>
<?php
$projects = isset($projects) && is_array($projects) ? $projects : (function_exists('gd_client_portal_get_visible_projects') ? gd_client_portal_get_visible_projects(50) : array());
$active_projects = array_filter($projects, function($p){ return !in_array((string)($p->status ?? ''), array('completed','closed','cancelled'), true); });
?>
<div class="gd-module-projects gd-module-listing">
    <div class="gd-module-summary-row">
        <div>
            <p class="gd-module-eyebrow"><?php esc_html_e('Portfolio', 'gd-client-portal'); ?></p>
            <h3><?php esc_html_e('Your Projects', 'gd-client-portal'); ?></h3>
        </div>
        <span class="gd-pill"><?php echo esc_html(sprintf(_n('%d project', '%d projects', count($projects), 'gd-client-portal'), count($projects))); ?></span>
    </div>

    <?php if (empty($projects)) : ?>
        <div class="gd-empty-state">
            <strong><?php esc_html_e('No projects yet', 'gd-client-portal'); ?></strong>
            <p><?php esc_html_e('Projects will appear here as soon as a service order is provisioned for your account.', 'gd-client-portal'); ?></p>
        </div>
    <?php else : ?>
        <div class="gd-project-list">
            <?php foreach ($projects as $p) :
                $status = ucwords(str_replace('_', ' ', (string)($p->status ?? 'active')));
                $stage = ucwords(str_replace('_', ' ', (string)($p->current_stage ?? 'Project setup')));
                $progress = max(0, min(100, absint($p->progress ?? 0)));
            ?>
                <article class="gd-project-item">
                    <div class="gd-project-topline">
                        <span class="gd-status-dot"></span>
                        <span class="gd-project-tag"><?php echo esc_html($status); ?></span>
                    </div>
                    <h4><?php echo esc_html($p->title); ?></h4>
                    <p><?php echo esc_html($stage); ?> · <?php echo esc_html($progress); ?>% <?php esc_html_e('complete', 'gd-client-portal'); ?></p>
                    <div class="gd-project-meta">
                        <span><?php echo esc_html(sprintf(_n('%d active project', '%d active projects', count($active_projects), 'gd-client-portal'), count($active_projects))); ?></span>
                        <?php if (!empty($p->link)) : ?><a href="<?php echo esc_url($p->link); ?>"><?php esc_html_e('Open project', 'gd-client-portal'); ?></a><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
