<?php
if (!defined('ABSPATH')) exit;

/**
 * Central event bus with backward-compatible WordPress hook bridging.
 * New code should call gdcp_event(); legacy gd_client_portal_* actions are
 * automatically mirrored into the bus without creating recursive dispatch.
 */
final class GDCP_Event_Bus {
    private static $events = array();
    private static $bridges_registered = false;
    private static $dispatching = false;

    public static function dispatch($event, $payload=array(), $emit_legacy=true) {
        $event = sanitize_key($event);
        if (!$event) return false;
        if (!is_array($payload)) $payload = array('value'=>$payload);
        $record = array(
            'event'=>$event,
            'time'=>microtime(true),
            'payload'=>$payload,
        );
        self::$events[] = $record;
        if (count(self::$events) > 100) self::$events = array_slice(self::$events, -100);

        do_action('gdcp.event.' . $event, $payload);
        do_action('gd_client_portal_event', $event, $payload);

        // Emit the historical hook when new code uses the event bus. This keeps
        // existing automation/module listeners working during migration.
        if ($emit_legacy && !self::$dispatching) {
            self::$dispatching = true;
            $args = isset($payload['_legacy_args']) && is_array($payload['_legacy_args']) ? $payload['_legacy_args'] : array($payload);
            do_action_ref_array('gd_client_portal_' . $event, $args);
            self::$dispatching = false;
        }
        return true;
    }

    public static function bridge_legacy($event, $args) {
        if (self::$dispatching) return;
        $event = sanitize_key($event);
        $args = is_array($args) ? array_values($args) : array($args);
        self::dispatch($event, array('legacy'=>true, '_legacy_args'=>$args, 'args'=>$args), false);
    }

    public static function register_legacy_bridges($events=array()) {
        if (self::$bridges_registered) return;
        self::$bridges_registered = true;
        foreach ((array)$events as $event) {
            $event = sanitize_key($event);
            if (!$event) continue;
            add_action('gd_client_portal_'.$event, function() use ($event) {
                $args = func_get_args();
                GDCP_Event_Bus::bridge_legacy($event, $args);
            }, 999, 99);
        }
    }

    public static function recent(){ return self::$events; }
    public static function stats(){
        $stats=array();
        foreach(self::$events as $row){ $e=$row['event']; $stats[$e]=isset($stats[$e])?$stats[$e]+1:1; }
        arsort($stats); return $stats;
    }
}
if (!function_exists('gdcp_event')) {
    function gdcp_event($event,$payload=array()){ return GDCP_Event_Bus::dispatch($event,$payload,true); }
}
if (!function_exists('gdcp_register_legacy_event_bridges')) {
    function gdcp_register_legacy_event_bridges($events=array()){ return GDCP_Event_Bus::register_legacy_bridges($events); }
}
