<?php
if (!defined('ABSPATH')) { exit; }
$root = dirname(__DIR__);
$delivery = file_get_contents($root.'/includes/delivery.php');
if (strpos($delivery, '$wpdb->insert(')!==false || strpos($delivery, '$wpdb->update(')!==false) throw new Exception('Delivery entry point still mutates delivery persistence directly.');
if (strpos($delivery, 'SELECT * FROM {$table} WHERE project_id')!==false) throw new Exception('Delivery entry point still performs direct delivery lookup.');
if (strpos($delivery, "SELECT * FROM '.gd_client_portal_get_delivery_table_name()")!==false) throw new Exception('Delivery download still performs direct delivery lookup.');
foreach(array('includes/delivery-repository.php','includes/delivery-service.php') as $f) if(!file_exists($root.'/'.$f)) throw new Exception('Missing canonical delivery boundary: '.$f);
echo "Delivery boundary passed.\n";
