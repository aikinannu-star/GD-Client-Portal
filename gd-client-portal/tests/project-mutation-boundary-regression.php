<?php
/** Static regression contracts for project mutation boundary enforcement. */
$root = dirname(__DIR__);
$service = file_get_contents($root.'/app/Services/ProjectService.php');
$automation = file_get_contents($root.'/includes/automation.php');
$intake = file_get_contents($root.'/includes/intake-builder.php');
foreach (array($service,$automation,$intake) as $source) {
    if ($source === false) throw new RuntimeException('Unable to read mutation-boundary source.');
}
if (strpos($service, 'advance_stage_for_automation') === false) throw new RuntimeException('Missing automation project service boundary.');
if (strpos($automation, '$wpdb->update(gd_client_portal_get_project_table_name()') !== false) throw new RuntimeException('Automation still directly updates the project table.');
if (strpos($automation, 'advance_stage_for_automation') === false) throw new RuntimeException('Automation does not route stage mutation through Project Service.');
if (strpos($intake, '$wpdb->update(gd_client_portal_get_project_table_name()') !== false) throw new RuntimeException('Intake still directly updates the project table.');
if (strpos($intake, 'gdcp_service(\'project\')') === false) throw new RuntimeException('Intake does not route project mutation through Project Service.');
echo "Project mutation boundary regression contracts passed.\n";

$onboarding = file_get_contents(__DIR__ . '/../includes/onboarding.php');
if (strpos($onboarding, '$wpdb->update(gd_client_portal_get_project_table_name()') !== false) throw new RuntimeException('Onboarding still directly mutates the project table.');
$delivery = file_get_contents(__DIR__ . '/../includes/delivery.php');
if (strpos($delivery, '$wpdb->update($project_table') !== false) throw new RuntimeException('Delivery still directly mutates the project table.');

$repo = file_get_contents($root.'/app/Repositories/ProjectRepository.php');
if ($repo === false || strpos($repo, 'function update_fields') === false) throw new RuntimeException('Project repository write boundary is missing.');
$scan_dirs = array($root.'/app/Services', $root.'/includes');
foreach ($scan_dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
        $path=$file->getPathname();
        if (basename($path)==='ProjectRepository.php') continue;
        $src=file_get_contents($path);
        if ($src===false) continue;
        if (preg_match('/\$wpdb->(?:update|insert|delete)\s*\([^;]*(?:gd_projects|gd_client_portal_get_project_table_name)/s',$src)) {
            throw new RuntimeException('Project table mutation escaped repository boundary: '.$path);
        }
    }
}
if (strpos($service, "gdcp_repository('project')") === false) throw new RuntimeException('Project service does not use repository persistence.');
