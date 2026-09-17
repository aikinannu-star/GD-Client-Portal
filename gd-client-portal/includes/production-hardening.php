<?php
/**
 * Production runtime hardening helpers.
 *
 * Keeps background scheduling bounded and exposes a small, read-only runtime
 * signal for operational diagnostics without changing business workflows.
 */
if (!defined('ABSPATH')) { exit; }

if (!function_exists('gd_client_portal_cron_schedule_once')) {
    function gd_client_portal_cron_schedule_once($hook, $timestamp, $recurrence = false) {
        $hook = sanitize_key($hook);
        if (!$hook || !function_exists('wp_next_scheduled')) return false;
        if (wp_next_scheduled($hook)) return false;
        if ($recurrence && function_exists('wp_schedule_event')) {
            return (bool) wp_schedule_event(absint($timestamp), sanitize_key($recurrence), $hook);
        }
        if (function_exists('wp_schedule_single_event')) {
            return (bool) wp_schedule_single_event(absint($timestamp), $hook);
        }
        return false;
    }
}

if (!function_exists('gd_client_portal_runtime_background_health')) {
    function gd_client_portal_runtime_background_health() {
        $hooks = array(
            'gd_client_portal_automation_daily',
            'gd_client_portal_automation_reliability_tick',
            'gd_client_portal_sla_daily',
            'gd_client_portal_payment_daily',
        );
        $scheduled = array();
        foreach ($hooks as $hook) {
            $scheduled[$hook] = function_exists('wp_next_scheduled') ? wp_next_scheduled($hook) : false;
        }
        return $scheduled;
    }
}
