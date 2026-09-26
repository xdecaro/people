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
$q = $db->getQuery(true)->select('id')->from('#__users')->where('username=' . $db->quote('admin'));
$adminId = (int) $db->setQuery($q, 0, 1)->loadResult();
$app->loadIdentity($container->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($adminId));

$component = $app->bootComponent('com_xdecaropeople');
$backup = $component->getBackupService();
$restore = $component->getRestoreService();

$valid = $backup->create($adminId, 'restore-preview-runtime');
$beforeCount = (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_people'))->loadResult();
$preview = $restore->preview($valid['path'], $adminId);
if (empty($preview['compatible']) || !empty($preview['blocking_errors'])) {
    fwrite(STDERR, "Valid backup did not preview as compatible.\n"); exit(1);
}
$afterCount = (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_people'))->loadResult();
if ($beforeCount !== $afterCount) { fwrite(STDERR, "Preview modified People rows.\n"); exit(1); }

$expectReject = static function (callable $fn, string $label): void {
    try { $fn(); } catch (\Throwable) { return; }
    fwrite(STDERR, "Restore preview must reject {$label}.\n"); exit(1);
};

$corrupt = sys_get_temp_dir() . '/people-corrupt-' . bin2hex(random_bytes(4)) . '.zip';
file_put_contents($corrupt, 'not-a-zip');
$expectReject(fn() => $restore->preview($corrupt, $adminId), 'corrupt ZIP');
@unlink($corrupt);

$traversal = sys_get_temp_dir() . '/people-traversal-' . bin2hex(random_bytes(4)) . '.zip';
copy($valid['path'], $traversal);
$zip = new \ZipArchive();
$zip->open($traversal);
$zip->addFromString('../evil.php', '<?php echo 1;');
$zip->close();
$expectReject(fn() => $restore->preview($traversal, $adminId), 'path traversal entry');
@unlink($traversal);

$badChecksum = sys_get_temp_dir() . '/people-checksum-' . bin2hex(random_bytes(4)) . '.zip';
copy($valid['path'], $badChecksum);
$zip = new \ZipArchive();
$zip->open($badChecksum);
$data = json_decode((string) $zip->getFromName('data.json'), true, 512, JSON_THROW_ON_ERROR);
$data['format_version'] = 999;
$zip->addFromString('data.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$zip->close();
$expectReject(fn() => $restore->preview($badChecksum, $adminId), 'checksum mismatch');
@unlink($badChecksum);

$actions = $db->setQuery($db->getQuery(true)->select('action')->from('#__xdecaropeople_maintenance_log')->where("action='restore_preview'"))->loadColumn();
if (!$actions) { fwrite(STDERR, "Restore preview was not logged.\n"); exit(1); }

echo "People 1.7.28 restore preview runtime OK\n";
