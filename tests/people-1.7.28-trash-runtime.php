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
$container
    ->alias('session', 'session.cli')
    ->alias('JSession', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');

$app = $container->get('JApplicationAdministrator');
\Joomla\CMS\Factory::$application = $app;
$app->createExtensionNamespaceMap();
$db = $container->get(\Joomla\Database\DatabaseInterface::class);

$query = $db->getQuery(true)->select($db->quoteName('id'))->from($db->quoteName('#__users'))->where($db->quoteName('username') . ' = ' . $db->quote('admin'));
$adminId = (int) $db->setQuery($query, 0, 1)->loadResult();
$app->loadIdentity($container->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($adminId));

$component = $app->bootComponent('com_xdecaropeople');
$trash = $component->getPersonTrashService();
$provider = $component->getPersonProviderService();
$now = \Joomla\CMS\Factory::getDate()->toSql();
$uuid = '77777777-7777-4777-8777-777777777777';

$db->setQuery($db->getQuery(true)->delete($db->quoteName('#__xdecaropeople_maintenance_log'))->where($db->quoteName('subject_uuid') . ' = ' . $db->quote($uuid)))->execute();
$db->setQuery($db->getQuery(true)->delete($db->quoteName('#__xdecaropeople_people'))->where($db->quoteName('uuid') . ' = ' . $db->quote($uuid)))->execute();

$person = (object) [
    'uuid' => $uuid,
    'display_name' => 'Trash Runtime Person',
    'first_name' => 'Trash',
    'last_name' => 'Runtime Person',
    'person_status' => 'active',
    'state' => 0,
    'access' => 1,
    'created' => $now,
    'created_by' => $adminId,
];
$db->insertObject('#__xdecaropeople_people', $person, 'id');
$id = (int) $person->id;

if ($trash->trash([$id], $adminId) !== 1) {
    fwrite(STDERR, "Trash operation did not affect one person.\n");
    exit(1);
}
$row = $db->setQuery($db->getQuery(true)->select(['id','uuid','state'])->from('#__xdecaropeople_people')->where('id=' . $id))->loadAssoc();
if ((int) $row['id'] !== $id || $row['uuid'] !== $uuid || (int) $row['state'] !== -2) {
    fwrite(STDERR, "Trash must preserve ID/UUID and set state=-2.\n");
    exit(1);
}
if ($provider->getPerson($uuid, false) !== null) {
    fwrite(STDERR, "Trashed person leaked through normal provider.\n");
    exit(1);
}

if ($trash->restore([$id], $adminId) !== 1) {
    fwrite(STDERR, "Restore operation did not affect one person.\n");
    exit(1);
}
$state = (int) $db->setQuery($db->getQuery(true)->select('state')->from('#__xdecaropeople_people')->where('id=' . $id))->loadResult();
if ($state !== 0) {
    fwrite(STDERR, "Restore must return the previous state.\n");
    exit(1);
}

$blocked = false;
try {
    $trash->purge([$id], $adminId);
} catch (\RuntimeException $e) {
    $blocked = true;
}
if (!$blocked) {
    fwrite(STDERR, "Purge must reject a non-trashed person.\n");
    exit(1);
}

$trash->trash([$id], $adminId);
if ($trash->purge([$id], $adminId) !== 1) {
    fwrite(STDERR, "Purge did not remove one trashed person.\n");
    exit(1);
}
$count = (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_people')->where('id=' . $id))->loadResult();
if ($count !== 0) {
    fwrite(STDERR, "Purged person still exists.\n");
    exit(1);
}

$actions = $db->setQuery($db->getQuery(true)->select('action')->from('#__xdecaropeople_maintenance_log')->where('subject_uuid=' . $db->quote($uuid))->order('id ASC'))->loadColumn();
foreach (['trash_person', 'restore_trash_person', 'purge_person'] as $requiredAction) {
    if (!in_array($requiredAction, $actions, true)) {
        fwrite(STDERR, "Missing maintenance action: {$requiredAction}\n");
        exit(1);
    }
}

$metadata = (string) $db->setQuery($db->getQuery(true)->select('metadata')->from('#__xdecaropeople_maintenance_log')->where('subject_uuid=' . $db->quote($uuid))->order('id DESC'), 0, 1)->loadResult();
if (str_contains($metadata, 'Trash Runtime Person')) {
    fwrite(STDERR, "Maintenance metadata must not contain full display-name data.\n");
    exit(1);
}

echo "People 1.7.28 trash runtime OK\n";
