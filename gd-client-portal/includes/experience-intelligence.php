<?php
/**
 * GD Client Portal v5.8 — Client Experience Intelligence & Personalization.
 * Read-only intelligence layer using existing portal data; no new tables required.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_experience_score_project($project) {
    if (!$project) return array('score'=>0,'level'=>'unknown','signals'=>array(),'recommendations'=>array());
    $score = 100; $signals = array(); $recommendations = array();
    $status = (string)($project->status ?? '');
    $stage = (string)($project->current_stage ?? '');
    $pid = absint($project->id);

    $life = function_exists('gd_client_portal_lifecycle_intelligence') ? gd_client_portal_lifecycle_intelligence($pid) : array();
    if (!empty($life['gap'])) {
        $score -= 18; $signals[] = 'A lifecycle milestone needs attention.';
        $recommendations[] = 'Resolve the next lifecycle milestone.';
    }
    if (function_exists('gd_client_portal_predictive_score_project')) {
        $risk = gd_client_portal_predictive_score_project($project);
        if (($risk['level'] ?? '') === 'high') { $score -= 22; $signals[] = 'Project has high operational risk.'; $recommendations[] = 'Review project risk and contact the client proactively.'; }
        elseif (($risk['level'] ?? '') === 'medium') { $score -= 10; $signals[] = 'Project has moderate operational risk.'; }
    }

    global $wpdb;
    if (function_exists('gd_client_portal_support_table')) {
        $t = gd_client_portal_support_table('tickets');
        $open = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE project_id=%d AND status IN ('open','in_progress','waiting_client')", $pid));
        if ($open > 0) { $score -= min(20, $open * 7); $signals[] = $open . ' open support ticket(s).'; $recommendations[] = 'Review open support tickets before the client has to follow up.'; }
    }
    if (function_exists('gd_client_portal_feedback_table')) {
        $t = gd_client_portal_feedback_table();
        $review = $wpdb->get_row($wpdb->prepare("SELECT rating,communication_rating,timeliness_rating,recommend FROM {$t} WHERE project_id=%d ORDER BY id DESC LIMIT 1", $pid));
        if ($review) {
            $rating = (float)$review->rating;
            if ($rating < 3) { $score -= 25; $signals[] = 'Latest client feedback is below 3/5.'; $recommendations[] = 'Review the latest feedback and follow up with the client.'; }
            elseif ($rating < 4) { $score -= 8; $signals[] = 'Latest client feedback is below 4/5.'; }
        }
    }
    if (function_exists('gd_client_portal_billing_quotes_table') && function_exists('gd_client_portal_billing_invoices_table')) {
        $it = gd_client_portal_billing_invoices_table();
        $overdue = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$it} WHERE project_id=%d AND status='overdue'", $pid));
        if ($overdue) { $score -= 18; $signals[] = 'Project has an overdue invoice.'; $recommendations[] = 'Send a payment reminder and review the invoice.'; }
    }
    if ($stage === 'awaiting_requirements') { $score -= 8; $signals[] = 'Client requirements are still pending.'; $recommendations[] = 'Prompt the client to complete outstanding requirements.'; }
    if (in_array($status, array('completed','cancelled'), true)) { $recommendations[] = 'Review the client journey and collect feedback where appropriate.'; }

    $score = max(0, min(100, $score));
    $level = $score >= 75 ? 'healthy' : ($score >= 50 ? 'watch' : 'needs_attention');
    return array('score'=>$score,'level'=>$level,'signals'=>array_values(array_unique($signals)),'recommendations'=>array_values(array_unique($recommendations)));
}

function gd_client_portal_experience_projects($limit=100) {
    if (!function_exists('gd_client_portal_get_visible_projects')) return array();
    $out = array();
    foreach ((array)gd_client_portal_get_visible_projects() as $p) {
        $r = gd_client_portal_experience_score_project($p); $r['project'] = $p; $out[] = $r;
    }
    usort($out, function($a,$b){ return $a['score'] <=> $b['score']; });
    return array_slice($out, 0, absint($limit));
}

function gd_client_portal_experience_recommendations($project_id) {
    $p = function_exists('gd_client_portal_get_project_by_id') ? gd_client_portal_cached_project($project_id) : false;
    if (!$p || !function_exists('gd_client_portal_verify_project_access') || !gd_client_portal_verify_project_access($p)) return array();
    $r = gd_client_portal_experience_score_project($p);
    return array('project'=>$p,'score'=>$r['score'],'level'=>$r['level'],'signals'=>$r['signals'],'recommendations'=>$r['recommendations']);
}

function gd_client_portal_experience_admin_page() {
    if (!function_exists('gd_client_portal_user_can_access_admin') || !gd_client_portal_user_can_access_admin()) wp_die(esc_html__('Access denied.','gd-client-portal'));
    $items = gd_client_portal_experience_projects(100); $healthy=$watch=$needs=0;
    foreach ($items as $i) { if ($i['level']==='healthy') $healthy++; elseif ($i['level']==='watch') $watch++; else $needs++; }
    echo '<div class="wrap"><h1>Client Experience Intelligence</h1><p>Proactive client-health signals based on lifecycle progress, project risk, support activity, feedback and billing state. Scores are operational guidance, not certainty.</p>';
    echo '<div class="gdcp-experience-cards"><div><b>'.intval($healthy).'</b><span>Healthy</span></div><div><b>'.intval($watch).'</b><span>Watch</span></div><div><b>'.intval($needs).'</b><span>Needs attention</span></div></div>';
    echo '<table class="widefat striped"><thead><tr><th>Project</th><th>Experience</th><th>Signals</th><th>Recommended next step</th></tr></thead><tbody>';
    foreach ($items as $i) { $p=$i['project']; $rec=!empty($i['recommendations'])?$i['recommendations'][0]:'No immediate action.'; echo '<tr><td><strong>#'.intval($p->id).'</strong> '.esc_html($p->title??'Project').'</td><td><strong>'.intval($i['score']).'/100</strong><br>'.esc_html(ucwords(str_replace('_',' ',$i['level']))).'</td><td>'.esc_html(implode(' ',$i['signals'])?:'No negative experience signals.').'</td><td>'.esc_html($rec).'</td></tr>'; }
    if (!$items) echo '<tr><td colspan="4">No visible projects available.</td></tr>';
    echo '</tbody></table></div>';
}
add_action('admin_menu', function(){ add_submenu_page('gd-client-portal','Client Experience Intelligence','Client Experience','gd_client_portal_access_admin','gd-client-portal-experience','gd_client_portal_experience_admin_page'); }, 20);
add_shortcode('gd_client_experience_intelligence','gd_client_portal_experience_admin_page');

function gd_client_portal_experience_assets() {
    if (is_admin() && isset($_GET['page']) && $_GET['page']==='gd-client-portal-experience') wp_enqueue_style('gd-client-portal-experience', GD_CLIENT_PORTAL_URL.'assets/experience-intelligence.css', array(), GD_CLIENT_PORTAL_VERSION);
}
add_action('admin_enqueue_scripts','gd_client_portal_experience_assets');
