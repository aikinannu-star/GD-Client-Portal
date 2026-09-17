<?php
if (!defined('ABSPATH')) exit;

/**
 * v7.6 application lifecycle boundary.
 *
 * This class does not replace WordPress hooks or legacy modules. It gives the
 * plugin one place to record bootstrap phases, module loading, and recoverable
 * failures so future domains can migrate without expanding the bootstrap
 * function into another monolith.
 */
final class GDCP_Application_Kernel {
    private static $phase = 'idle';
    private static $started = false;
    private static $loaded = array();
    private static $failures = array();
    private static $timings = array();
    private static $phase_started = 0.0;

    public static function start(){
        if(self::$started) return;
        self::$started=true;
        self::phase('bootstrap');
    }

    public static function phase($phase){
        $phase=sanitize_key($phase);
        if(!$phase) return false;
        self::$phase=$phase;
        self::$phase_started=microtime(true);
        return true;
    }

    public static function record_loaded($label,$file=''){
        $label=sanitize_key($label ?: basename((string)$file,'.php'));
        if(!$label) return;
        self::$loaded[$label]=array(
            'file'=>$file ? wp_normalize_path($file) : '',
            'phase'=>self::$phase,
            'time'=>current_time('mysql'),
        );
    }

    public static function record_failure($label,$message,$file='',$line=0){
        $row=array(
            'module'=>sanitize_text_field($label),
            'message'=>sanitize_text_field($message),
            'file'=>$file ? wp_normalize_path($file) : '',
            'line'=>absint($line),
            'phase'=>self::$phase,
            'time'=>current_time('mysql'),
        );
        self::$failures[]=$row;
        self::$failures=array_slice(self::$failures,-50);
        return $row;
    }

    public static function finish_phase(){
        if(self::$phase_started>0) self::$timings[self::$phase]=round((microtime(true)-self::$phase_started)*1000,2);
        return self::$timings[self::$phase]??0;
    }

    public static function finish(){ self::finish_phase(); self::$phase='ready'; }
    public static function phase_name(){ return self::$phase; }
    public static function loaded(){ return self::$loaded; }
    public static function failures(){ return self::$failures; }
    public static function timings(){ return self::$timings; }
    public static function status(){
        return array(
            'phase'=>self::$phase,
            'started'=>self::$started,
            'loaded'=>count(self::$loaded),
            'failures'=>count(self::$failures),
            'timings'=>self::$timings,
        );
    }
}
