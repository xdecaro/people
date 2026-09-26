<?php

declare(strict_types=1);

$root = getcwd();
if (!is_file($root . '/includes/defines.php') || !is_file($root . '/includes/framework.php')) {
    fwrite(STDERR, "Run this probe from the Joomla root.\n");
    exit(1);
}

$_SERVER['HTTP_HOST'] = 'localhost';
define('_JEXEC', 1);
define('JPATH_BASE', $root);
require JPATH_BASE . '/includes/defines.php';
require JPATH_BASE . '/includes/framework.php';
define('JPATH_COMPONENT', JPATH_ADMINISTRATOR . '/components/com_xdecaropeople');
define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_COMPONENT);

$container = \Joomla\CMS\Factory::getContainer();
$container->alias('session', 'session.cli')->alias('JSession', 'session.cli')->alias(\Joomla\CMS\Session\Session::class, 'session.cli')->alias(\Joomla\Session\Session::class, 'session.cli')->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
$app = $container->get('JApplicationAdministrator');
\Joomla\CMS\Factory::$application = $app;
$app->createExtensionNamespaceMap();
$db = $container->get(\Joomla\Database\DatabaseInterface::class);
$adminId = (int) $db->setQuery($db->getQuery(true)->select('id')->from('#__users')->where('username=' . $db->quote('admin')), 0, 1)->loadResult();
$app->loadIdentity($container->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($adminId));

$component = $app->bootComponent('com_xdecaropeople');
$model = $component->getMVCFactory()->createModel('Information', 'Administrator', ['ignore_request' => true]);
if (!$model) { fwrite(STDERR, "Information model unavailable.\n"); exit(1); }

$diagnostics = $model->getDiagnostics();
$database = $model->getDatabaseSummary();
$components = $model->getConnectedComponents();
$backups = $model->getBackups();
$trash = $model->getRecentTrashed();
$activity = $model->getMaintenanceActivity();

foreach (['component_version','core_version','core_ok','table_ok','php_version','joomla_version','integrity'] as $key) {
    if (!array_key_exists($key, $diagnostics)) { fwrite(STDERR, "Diagnostics missing {$key}.\n"); exit(1); }
}
foreach (['total','active','unpublished','trashed','missing'] as $key) {
    if (!array_key_exists($key, $database)) { fwrite(STDERR, "Database summary missing {$key}.\n"); exit(1); }
}
foreach (['birth_date','sex','tax_identifier','birth_place','address_line','email','phone','nationality_code'] as $key) {
    if (!array_key_exists($key, $database['missing'])) { fwrite(STDERR, "Missing-data summary missing {$key}.\n"); exit(1); }
}
if (!is_array($components) || count($components) < 7) { fwrite(STDERR, "Connected components summary incomplete.\n"); exit(1); }
if (!is_array($backups) || !is_array($trash) || !is_array($activity)) { fwrite(STDERR, "Information maintenance lists invalid.\n"); exit(1); }

$integrity = $component->getIntegrityService()->check($adminId, true);
foreach (['uuid_missing','uuid_duplicates','orphan_history','broken_merges','backup_storage'] as $key) {
    if (!array_key_exists($key, $integrity)) { fwrite(STDERR, "Integrity result missing {$key}.\n"); exit(1); }
}

echo "People 1.7.28 information runtime OK\n";
