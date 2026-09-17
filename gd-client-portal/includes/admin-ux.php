<?php
/**
 * Shared administrator UX components.
 */

if (!defined('ABSPATH')) {
    exit;
}

function gdcp_admin_page_header($title, $description = '', $actions = array()) {
    echo '<div class="gdcp-admin-page">';
    echo '<div class="gdcp-admin-page__header">';
    echo '<div class="gdcp-admin-page__heading">';
    echo '<h1>' . esc_html($title) . '</h1>';
    if ($description !== '') {
        echo '<p class="description">' . esc_html($description) . '</p>';
    }
    echo '</div>';

    if (!empty($actions)) {
        echo '<div class="gdcp-admin-page__actions">';
        foreach ($actions as $action) {
            if (empty($action['url']) || empty($action['label'])) {
                continue;
            }
            $class = !empty($action['primary']) ? 'button button-primary' : 'button';
            echo '<a class="' . esc_attr($class) . '" href="' . esc_url($action['url']) . '">';
            echo esc_html($action['label']);
            echo '</a>';
        }
        echo '</div>';
    }

    echo '</div>';
}

function gdcp_admin_page_footer() {
    echo '</div>';
}

function gdcp_admin_section($title, $description = '') {
    echo '<section class="gdcp-admin-section">';
    echo '<h2>' . esc_html($title) . '</h2>';
    if ($description !== '') {
        echo '<p class="description">' . esc_html($description) . '</p>';
    }
}

function gdcp_admin_section_end() {
    echo '</section>';
}

function gdcp_admin_empty_state($title, $message, $action = null) {
    echo '<div class="gdcp-empty-state">';
    echo '<h2>' . esc_html($title) . '</h2>';
    echo '<p>' . esc_html($message) . '</p>';

    if (is_array($action) && !empty($action['url']) && !empty($action['label'])) {
        echo '<p><a class="button button-primary" href="' . esc_url($action['url']) . '">';
        echo esc_html($action['label']);
        echo '</a></p>';
    }

    echo '</div>';
}

function gdcp_admin_status_badge($label, $status = 'neutral') {
    $allowed = array('success', 'warning', 'danger', 'neutral');
    if (!in_array($status, $allowed, true)) {
        $status = 'neutral';
    }

    return '<span class="gdcp-status gdcp-status--' . esc_attr($status) . '">' . esc_html($label) . '</span>';
}

add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos((string) $hook, 'gd-client-portal') === false) {
        return;
    }

    $css = '
    .gdcp-admin-page { max-width: 1180px; }
    .gdcp-admin-page__header {
        display:flex; justify-content:space-between; gap:24px; align-items:flex-start;
        margin:24px 0 28px; padding-bottom:20px; border-bottom:1px solid #dcdcde;
    }
    .gdcp-admin-page__header h1 { margin:0 0 8px; }
    .gdcp-admin-page__actions { display:flex; gap:8px; flex-wrap:wrap; }
    .gdcp-admin-section {
        background:#fff; border:1px solid #dcdcde; border-radius:6px;
        padding:20px 24px; margin:18px 0;
    }
    .gdcp-admin-section h2 { margin-top:0; }
    .gdcp-empty-state {
        text-align:center; background:#fff; border:1px dashed #a7aaad;
        border-radius:6px; padding:48px 24px; margin:24px 0;
    }
    .gdcp-status {
        display:inline-block; padding:3px 9px; border-radius:999px;
        font-size:12px; font-weight:600; line-height:1.5;
    }
    .gdcp-status--success { background:#d7f3df; color:#0a5c2b; }
    .gdcp-status--warning { background:#fcf0c8; color:#6b4f00; }
    .gdcp-status--danger { background:#f8d7da; color:#8a1f2d; }
    .gdcp-status--neutral { background:#e9eaeb; color:#3c434a; }
    .gdcp-admin-page .widefat { border-radius:4px; overflow:hidden; }
    @media (max-width: 782px) {
        .gdcp-admin-page__header { display:block; }
        .gdcp-admin-page__actions { margin-top:16px; }
    }';

    wp_enqueue_style(
        'gdcp-admin-dashboard',
        GD_CLIENT_PORTAL_URL . 'assets/admin-dashboard.css',
        array(),
        GD_CLIENT_PORTAL_VERSION
    );

    wp_register_style('gdcp-admin-ux', false, array('gdcp-admin-dashboard'), GD_CLIENT_PORTAL_VERSION);
    wp_enqueue_style('gdcp-admin-ux');
    wp_add_inline_style('gdcp-admin-ux', $css);
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'gdcp-core-workflow-ux',
        GD_CLIENT_PORTAL_URL . 'assets/core-workflow-ux.css',
        array(),
        GD_CLIENT_PORTAL_VERSION
    );
});
