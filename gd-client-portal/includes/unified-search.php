<?php
/** GD Client Portal v6.2 — Unified Client & Admin Search. */
if (!defined('ABSPATH')) exit;

function gd_client_portal_search_term(){ $q=isset($_REQUEST['q'])?sanitize_text_field(wp_unslash($_REQUEST['q'])):''; return trim(mb_substr($q,0,100)); }
function gd_client_portal_search_scope_where($column='tenant_id'){ $w=array('1=1');$p=array();if(!gd_client_portal_is_platform_admin()){$tenant=absint(gd_client_portal_get_current_tenant_id());if($tenant<=0)return array('0=1',array());$w[]=$column.'=%d';$p[]=$tenant;}return array(implode(' AND ',$w),$p);}
function gd_client_portal_search_run($q,$limit=8){ return gdcp_unified_search_service()->search($q,$limit); }
function gd_client_portal_unified_search_ajax(){
    gd_client_portal_ajax_guard('gd_client_portal_unified_search','nonce');
    $q=gd_client_portal_search_term(); if(strlen($q)<2)wp_send_json_success(array('results'=>array()));
    wp_send_json_success(array('results'=>gd_client_portal_search_run($q,8)));
}
add_action('wp_ajax_gd_client_portal_unified_search','gd_client_portal_unified_search_ajax');
function gd_client_portal_unified_search_assets(){
    wp_enqueue_style('gd-client-portal-unified-search',GD_CLIENT_PORTAL_URL.'assets/unified-search.css',array(),GD_CLIENT_PORTAL_VERSION);
    wp_enqueue_script('gd-client-portal-unified-search',GD_CLIENT_PORTAL_URL.'assets/unified-search.js',array('jquery'),GD_CLIENT_PORTAL_VERSION,true);
    wp_localize_script('gd-client-portal-unified-search','gdcpUnifiedSearch',array('ajaxurl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('gd_client_portal_unified_search')));
}
add_action('wp_enqueue_scripts','gd_client_portal_unified_search_assets',60);
add_action('admin_enqueue_scripts','gd_client_portal_unified_search_assets',60);
function gd_client_portal_unified_search_markup($admin=false){
    $id='gdcp-unified-search-'.wp_rand(1000,9999);
    ob_start();?><div class="gdcp-unified-search" id="<?php echo esc_attr($id); ?>">
      <div class="gdcp-search-box"><span aria-hidden="true">⌕</span><input type="search" class="gdcp-search-input" placeholder="Search projects, clients, invoices, contracts, files, tickets…" autocomplete="off"><kbd>Ctrl K</kbd></div>
      <div class="gdcp-search-results" role="listbox" aria-live="polite"></div>
    </div><?php return ob_get_clean();
}
function gd_client_portal_unified_search_shortcode(){if(!is_user_logged_in()||!gd_client_portal_verify_request())return '';return gd_client_portal_unified_search_markup(false);}
add_shortcode('gd_unified_search','gd_client_portal_unified_search_shortcode');
add_shortcode('gd_portal_search','gd_client_portal_unified_search_shortcode');
function gd_client_portal_unified_search_admin_page(){if(!gd_client_portal_user_can_access_admin())wp_die('Access denied.');echo '<div class="wrap"><h1>Unified Search</h1><p>Securely search projects, clients, financial records, contracts, documents, support tickets, service requests and tasks within your permitted tenant scope.</p>'.gd_client_portal_unified_search_markup(true).'</div>';}
add_action('admin_menu',function(){add_submenu_page('gd-client-portal','Unified Search','Unified Search','gd_client_portal_access_admin','gd-client-portal-unified-search','gd_client_portal_unified_search_admin_page');},26);
