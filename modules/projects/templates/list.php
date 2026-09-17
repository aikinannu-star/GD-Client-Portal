<div class="gd-module-projects gd-module-listing">
    <div class="gd-module-summary-row">
        <div>
            <p class="gd-module-eyebrow"><?php esc_html_e('Portfolio', 'gd-client-portal'); ?></p>
            <h3><?php esc_html_e('Your Projects', 'gd-client-portal'); ?></h3>
        </div>
        <span class="gd-pill"><?php echo esc_html__('3 active', 'gd-client-portal'); ?></span>
    </div>

    <?php if (empty($projects)) : ?>
        <div class="gd-empty-state">
            <p><?php esc_html_e('No projects found.', 'gd-client-portal'); ?></p>
        </div>
    <?php else : ?>
        <div class="gd-project-list">
            <?php foreach ($projects as $p) : ?>
                <article class="gd-project-item">
                    <div class="gd-project-topline">
                        <span class="gd-status-dot"></span>
                        <span class="gd-project-tag"><?php esc_html_e('In progress', 'gd-client-portal'); ?></span>
                    </div>
                    <h4><?php echo esc_html($p['title']); ?></h4>
                    <p><?php esc_html_e('Phase 2 delivery review in progress.', 'gd-client-portal'); ?></p>
                    <div class="gd-project-meta">
                        <span><?php esc_html_e('Due this month', 'gd-client-portal'); ?></span>
                        <a href="<?php echo esc_url($p['link']); ?>"><?php esc_html_e('Open project', 'gd-client-portal'); ?></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
