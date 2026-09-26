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

$q = $db->getQuery(true)->select('id')->from('#__users')->where($db->quoteName('username') . '=' . $db->quote('admin'));
$adminId = (int) $db->setQuery($q, 0, 1)->loadResult();
$app->loadIdentity($container->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($adminId));

$component = $app->bootComponent('com_xdecaropeople');
$trash = $component->getPersonTrashService();
$now = \Joomla\CMS\Factory::getDate()->toSql();

$person = (object) [
    'uuid' => '77777777-7777-4777-8777-777777777777',
    'display_name' => 'Trash Runtime Person',
    'first_name' => 'Trash',
    'last_name' => 'Runtime',
    'person_status' => 'active',
    'state' => 1,
    'access' => 1,
    'created' => $now,
    'created_by' => $adminId,
];
$db->insertObject('#__xdecaropeople_people', $person, 'id');
$originalId = (int) $person->id;

if ($trash->trash([$originalId], $adminId) !== 1) {
    fwrite(STDERR, "Trash did not affect exactly one row.\n");
    exit(1);
}

$q = $db->getQuery(true)->select(['id','uuid','state'])->from('#__xdecaropeople_people')->where('id=' . $originalId);
$row = $db->setQuery($q)->loadAssoc();
if ((int) ($row['id'] ?? 0) !== $originalId || ($row['uuid'] ?? '') !== $person->uuid || (int) ($row['state'] ?? 0) !== -2) {
    fwrite(STDERR, "Trash must preserve ID/UUID and set state=-2 only.\n");
    exit(1);
}

$q = $db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_maintenance_log')->where($db->quoteName('action') . '=' . $db->quote('trash_person'))->where($db->quoteName('subject_uuid') . '=' . $db->quote($person->uuid));
if ((int) $db->setQuery($q)->loadResult() !== 1) {
    fwrite(STDERR, "Trash action was not logged.\n");
    exit(1);
}

if ($trash->restore([$originalId], $adminId) !== 1) {
    fwrite(STDERR, "Restore did not affect exactly one row.\n");
    exit(1);
}
$q = $db->getQuery(true)->select('state')->from('#__xdecaropeople_people')->where('id=' . $originalId);
if ((int) $db->setQuery($q)->loadResult() !== 1) {
    fwrite(STDERR, "Restore did not recover the previous state.\n");
    exit(1);
}

$thrown = false;
try {
    $trash->purge([$originalId], $adminId);
} catch (\RuntimeException) {
    $thrown = true;
}
if (!$thrown) {
    fwrite(STDERR, "Purge must refuse non-trashed people.\n");
    exit(1);
}

$trash->trash([$originalId], $adminId);
if ($trash->purge([$originalId], $adminId) !== 1) {
    fwrite(STDERR, "Purge did not remove trashed row.\n");
    exit(1);
}
$q = $db->getQuery(true)->select('COUNT(*)')->from('#__xdecaropeople_people')->where('id=' . $originalId);
if ((int) $db->setQuery($q)->loadResult() !== 0) {
    fwrite(STDERR, "Purged row still exists.\n");
    exit(1);
}

$q = $db->getQuery(true)->select('metadata')->from('#__xdecaropeople_maintenance_log')->where($db->quoteName('subject_uuid') . '=' . $db->quote($person->uuid))->order('id DESC');
$logs = $db->setQuery($q)->loadColumn();
foreach ($logs as $metadata) {
    $decoded = json_decode((string) $metadata, true) ?: [];
    foreach (['email','phone','tax_identifier','address_line','notes'] as $sensitiveKey) {
        if (array_key_exists($sensitiveKey, $decoded)) {
            fwrite(STDERR, "Maintenance log leaked sensitive field: {$sensitiveKey}\n");
            exit(1);
        }
    }
}

echo "People 1.7.28 trash runtime OK\n";
