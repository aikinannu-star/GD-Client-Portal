<?php if (!defined('ABSPATH')) { exit; } ?>
<?php
    $items = gd_client_portal_filter_items_by_tenant(get_option('gd_client_portal_marketplace_items', array()), gd_client_portal_get_current_tenant_id());
    if (!is_array($items)) { $items = array(); }
    $visible = array_filter($items, function($i){ return !empty($i['enabled']); });
    $enabled_count = count($visible);
    $total_count = count($items);
?>
<div class="gd-module-detail gd-marketplace-detail">
    <?php
        // Provide a lightweight fallback configuration in case scripts are deferred/concatenated
        $paystack_config = gd_client_portal_marketplace_get_paystack_config();
        $mp_config = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_base' => esc_url_raw(rest_url('gd-client-portal/v1/marketplace')),
            'nonce' => wp_create_nonce('gd_client_portal_marketplace_add_to_cart'),
            'paystack_enabled' => !empty($paystack_config['enabled']) ? 1 : 0,
        );
    ?>
    <script>window.gdClientPortalMarketplace = window.gdClientPortalMarketplace || <?php echo wp_json_encode($mp_config); ?>;</script>
    <header class="gd-mp-header" role="banner">
        <div class="gd-mp-header-inner">
            <button class="gd-mp-menu-toggle" aria-label="Open menu" aria-expanded="false">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>

            <div class="gd-mp-title">
                <h2><?php echo esc_html__('Marketplace', 'gd-client-portal'); ?></h2>
            </div>

            <div class="gd-mp-search">
                <label class="gd-mp-search-label">
                    <svg class="gd-mp-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="11" cy="11" r="6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <input id="gd-mp-mini-search" type="search" placeholder="<?php echo esc_attr__('Search for products, brands...', 'gd-client-portal'); ?>" aria-label="Search marketplace" />
                </label>
            </div>

            <div class="gd-mp-actions">
                <button class="gd-mp-cart-btn" aria-label="View cart">
                    <svg class="gd-mp-cart-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M6 6h15l-1.5 9h-11z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="20" r="1" fill="currentColor"/><circle cx="18" cy="20" r="1" fill="currentColor"/></svg>
                    <span class="gd-mp-cart-count" aria-live="polite">0</span>
                </button>
                <button class="gd-mp-avatar" aria-haspopup="true" aria-expanded="false">
                    <span class="gd-mp-avatar-wrap"><?php echo get_avatar(get_current_user_id(), 40); ?></span>
                </button>
            </div>
        </div>
    </header>
    <div class="gd-module-summary-row">
        <div>
            <p class="gd-module-eyebrow"><?php echo esc_html__('Storefront', 'gd-client-portal'); ?></p>
            <h2><?php echo esc_html__('Marketplace', 'gd-client-portal'); ?></h2>
        </div>
        <span class="gd-pill"><?php echo sprintf(esc_html__('%s items', 'gd-client-portal'), number_format_i18n($enabled_count)); ?></span>
    </div>

    <div class="gd-marketplace-stack">
    <?php
    $cats = get_option('gd_client_portal_marketplace_categories', array());
    $payment_status = isset($_GET['gd_mp_status']) ? sanitize_text_field(wp_unslash($_GET['gd_mp_status'])) : '';
    $payment_reference = isset($_GET['reference']) ? sanitize_text_field(wp_unslash($_GET['reference'])) : '';
    if ($payment_status === 'success') {
        echo '<div class="gd-market-status gd-market-status-success" role="status" aria-live="polite">' . esc_html__('Payment confirmed. Your marketplace order is now active.', 'gd-client-portal') . '</div>';
    } elseif ($payment_status === 'failed') {
        echo '<div class="gd-market-status gd-market-status-error" role="alert">' . esc_html__('Payment could not be confirmed. Please try again or contact support.', 'gd-client-portal') . '</div>';
    }

    if (empty($visible)) :
        if ($total_count > 0) : ?>
            <p><?php echo esc_html__('There are items configured but none are enabled. Visit the admin to enable them.', 'gd-client-portal'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=gd-client-portal-marketplace')); ?>"><?php echo esc_html__('Manage marketplace items', 'gd-client-portal'); ?></a></p>
        <?php else: ?>
            <p><?php echo esc_html__('No marketplace items available.', 'gd-client-portal'); ?></p>
        <?php endif; ?>
        <?php else :
            // server-side filtering & pagination
            $page = isset($_GET['gd_mp_page']) ? max(1, intval($_GET['gd_mp_page'])) : 1;
            $perpage = isset($_GET['gd_mp_perpage']) ? max(1, intval($_GET['gd_mp_perpage'])) : 6;
            $filter_cat = isset($_GET['gd_mp_cat']) ? sanitize_text_field(wp_unslash($_GET['gd_mp_cat'])) : '';

            $filtered = array();
            foreach ($visible as $it) {
                if ($filter_cat) {
                    if (empty($it['category']) || strval($it['category']) !== strval($filter_cat)) { continue; }
                }
                $filtered[] = $it;
            }

            $total = count($filtered);
            $pages = max(1, ceil($total / $perpage));
            if ($page > $pages) { $page = $pages; }
            $start = ($page - 1) * $perpage;
            $page_items = array_slice($filtered, $start, $perpage);
            ?>

        <div class="gd-marketplace-controls" role="region" aria-label="Marketplace controls">
            <label for="gd-mp-filter"><?php echo esc_html__('Category:', 'gd-client-portal'); ?></label>
            <select id="gd-mp-filter" aria-controls="gd-mp-grid">
                <option value=""><?php echo esc_html__('All', 'gd-client-portal'); ?></option>
                <?php foreach ($cats as $cid => $cname) : ?>
                    <option value="<?php echo esc_attr($cid); ?>" <?php selected($filter_cat, $cid); ?>><?php echo esc_html($cname); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="gd-mp-perpage"><?php echo esc_html__('Per page:', 'gd-client-portal'); ?></label>
            <select id="gd-mp-perpage">
                <option value="6" <?php selected($perpage, 6); ?>>6</option>
                <option value="12" <?php selected($perpage, 12); ?>>12</option>
                <option value="24" <?php selected($perpage, 24); ?>>24</option>
            </select>
            <div style="flex:1"></div>
            <div aria-live="polite"><?php echo sprintf(esc_html__('%1$s items', 'gd-client-portal'), number_format_i18n($total)); ?></div>
        </div>

        <!-- Mobile category tabs -->
        <?php if (!empty($cats)) : ?>
            <div class="gd-mp-categories" role="tablist" aria-label="Marketplace categories">
                <?php foreach ($cats as $cid => $cname) : ?>
                    <button type="button" class="gd-mp-cat-tab" data-cat="<?php echo esc_attr($cid); ?>" aria-pressed="<?php echo selected($filter_cat, $cid, false); ?>"><?php echo esc_html($cname); ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="gd-market-grid" id="gd-mp-grid" data-current-page="<?php echo esc_attr($page); ?>" data-pages="<?php echo esc_attr($pages); ?>" data-perpage="<?php echo esc_attr($perpage); ?>">
            <?php foreach ($page_items as $it) : ?>
                <article class="gd-market-item" data-id="<?php echo esc_attr($it['id']); ?>" data-sku="<?php echo esc_attr($it['sku']); ?>" data-cat="<?php echo esc_attr($it['category'] ?? ''); ?>" role="article" aria-labelledby="mp-title-<?php echo esc_attr($it['id']); ?>">
                    <?php if (!empty($it['image_id'])) :
                        $alt = get_post_meta($it['image_id'], '_wp_attachment_image_alt', true);
                        $alt = $alt ? $alt : $it['title'];
                    ?>
                        <div class="gd-market-image"><?php echo wp_get_attachment_image($it['image_id'], 'medium', false, array('alt'=>esc_attr($alt))); ?></div>
                    <?php endif; ?>
                    <div class="gd-market-head">
                        <strong id="mp-title-<?php echo esc_attr($it['id']); ?>"><?php echo esc_html($it['title']); ?></strong>
                        <span class="gd-market-price"><?php echo esc_html('$' . number_format_i18n(floatval($it['price']), 2)); ?></span>
                    </div>
                    <p class="gd-market-desc"><?php echo esc_html($it['description']); ?></p>
                    <button class="gd-mp-add-ico" aria-label="Add <?php echo esc_attr($it['title']); ?> to cart" data-sku="<?php echo esc_attr($it['sku']); ?>" data-id="<?php echo esc_attr($it['id']); ?>"> 
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M6 6h15l-1.5 9h-11z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="20" r="1" fill="currentColor"/><circle cx="18" cy="20" r="1" fill="currentColor"/></svg>
                    </button>
                </article>
            <?php endforeach; ?>
        </div>

        <nav id="gd-mp-pagination" class="gd-mp-pagination" role="navigation" aria-label="Marketplace pages">
            <?php if ($pages > 1) :
                $base_args = array();
                if ($filter_cat) { $base_args['gd_mp_cat'] = $filter_cat; }
                if ($perpage) { $base_args['gd_mp_perpage'] = $perpage; }
                $base_url = remove_query_arg(array('gd_mp_page'), esc_url(add_query_arg($base_args)));
                // prev
                if ($page > 1) {
                    echo '<a class="button" href="' . esc_url(add_query_arg('gd_mp_page', $page-1, $base_url)) . '">&laquo; ' . esc_html__('Prev','gd-client-portal') . '</a> ';
                }
                for ($i=1;$i<=$pages;$i++) {
                    if ($i == $page) {
                        echo '<span class="button current" aria-current="page">' . intval($i) . '</span> ';
                    } else {
                        echo '<a class="button" href="' . esc_url(add_query_arg('gd_mp_page', $i, $base_url)) . '">' . intval($i) . '</a> ';
                    }
                }
                if ($page < $pages) {
                    echo ' <a class="button" href="' . esc_url(add_query_arg('gd_mp_page', $page+1, $base_url)) . '">' . esc_html__('Next','gd-client-portal') . ' &raquo;</a>';
                }
            endif; ?>
        </nav>

        <?php if ($pages > $page) : ?>
            <div style="text-align:center;margin-top:12px;">
                <button id="gd-mp-loadmore" class="button" data-next-page="<?php echo esc_attr($page + 1); ?>"><?php echo esc_html__('Load more', 'gd-client-portal'); ?></button>
            </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>

    <!-- Slide-out drawer: menu / orders -->
    <aside id="gd-mp-drawer" class="gd-mp-drawer" aria-hidden="true">
        <div class="gd-mp-drawer-inner">
            <button class="gd-mp-drawer-close" aria-label="Close menu">×</button>
            <nav class="gd-mp-drawer-nav" aria-label="Marketplace menu">
                <ul>
                    <li><a href="#gd-mp-grid" class="gd-mp-drawer-link"><?php echo esc_html__('Browse', 'gd-client-portal'); ?></a></li>
                    <?php if (is_user_logged_in()) : ?>
                        <li class="gd-mp-drawer-orders-heading"><?php echo esc_html__('My Orders', 'gd-client-portal'); ?></li>
                        <li class="gd-mp-drawer-orders">
                            <?php echo gd_client_portal_marketplace_render_order_history(); ?>
                        </li>
                    <?php endif; ?>
                    <li><a href="<?php echo esc_url(home_url('/my-account/')); ?>"><?php echo esc_html__('Account', 'gd-client-portal'); ?></a></li>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Mobile bottom navigation (app-like) -->
    <nav class="gd-mp-bottom-nav" role="navigation" aria-label="Marketplace navigation">
        <button class="gd-mp-bottom-btn gd-mp-bottom-home" aria-label="Browse">
            <span class="icon">🏠</span>
        </button>
        <button class="gd-mp-bottom-btn gd-mp-bottom-search" aria-label="Search">
            <span class="icon">🔍</span>
        </button>
        <button class="gd-mp-bottom-btn gd-mp-bottom-categories" aria-label="Categories">
            <span class="icon">🔖</span>
        </button>
        <button class="gd-mp-bottom-btn gd-mp-bottom-cart gd-mp-bottom-cart" aria-label="Cart">
            <span class="icon">🛒</span>
            <span class="gd-mp-cart-count" aria-live="polite">0</span>
        </button>
        <button class="gd-mp-bottom-btn gd-mp-bottom-account" aria-label="Account">
            <span class="icon"><?php echo get_avatar(get_current_user_id(), 20); ?></span>
        </button>
    </nav>
    </div>
</div>
