<?php
/**
 * Access gate template for unauthenticated portal users.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="gd-access-gate">
    <div class="gd-access-gate__card">
        <h2><?php echo esc_html($title); ?></h2>
        <p><?php echo esc_html($message); ?></p>
        <!-- Auth UI removed from access gate; provide a link to the site's auth page -->
        <p>
            <a class="button button-primary" href="https://godemarsempire.com/my-account/"><?php echo esc_html__('Log in or Register', 'gd-client-portal'); ?></a>
        </p>
    </div>
</div>
