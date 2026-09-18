<?php
if (!defined('ABSPATH')) exit;

$canonical = defined('GD_CLIENT_PORTAL_PATH') ? GD_CLIENT_PORTAL_PATH . 'app/Services/DocumentService.php' : __DIR__ . '/../app/Services/DocumentService.php';

if (!class_exists('GDCP_Document_Service', false) && is_file($canonical)) {
	require_once $canonical;
}

if (!function_exists('gdcp_document_service')) {
	function gdcp_document_service(){
		static $s;
		if (!$s && class_exists('GDCP_Document_Service')) {
			$s = new GDCP_Document_Service();
		}
		return $s;
	}
}
