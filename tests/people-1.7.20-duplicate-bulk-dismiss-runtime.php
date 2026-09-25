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
$query = $db->getQuery(true)
    ->select($db->quoteName('id'))
    ->from($db->quoteName('#__users'))
    ->where($db->quoteName('username') . ' = ' . $db->quote('admin'));
$adminId = (int) $db->setQuery($query, 0, 1)->loadResult();
$app->loadIdentity($container->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($adminId));

$duplicates = $app->bootComponent('com_xdecaropeople')->getDuplicateService();
$now = \Joomla\CMS\Factory::getDate()->toSql();

$rows = [
    ['e1111111-1111-4111-8111-111111111111', 'Bulk Alpha', 'Bulk', 'Alpha'],
    ['e2222222-2222-4222-8222-222222222222', 'Bulk Alpha', 'Bulk', 'Alpha'],
    ['e3333333-3333-4333-8333-333333333333', 'Bulk Beta', 'Bulk', 'Beta'],
    ['e4444444-4444-4444-8444-444444444444', 'Bulk Beta', 'Bulk', 'Beta'],
];

foreach ($rows as [$uuid, $display, $first, $last]) {
    $row = (object) [
        'uuid' => $uuid,
        'display_name' => $display,
        'first_name' => $first,
        'last_name' => $last,
        'person_status' => 'active',
        'state' => 1,
        'access' => 1,
        'created' => $now,
        'created_by' => $adminId,
    ];
    $db->insertObject('#__xdecaropeople_people', $row, 'id');
}

$wantedKeys = ['bulk|alpha' => true, 'bulk|beta' => true];
$signatures = [];

foreach ($duplicates->find(500) as $group) {
    $key = (string) ($group['key'] ?? '');
    if (($group['type'] ?? '') === 'name' && isset($wantedKeys[$key])) {
        $signatures[] = (string) ($group['signature'] ?? '');
    }
}

if (count($signatures) !== 2) {
    fwrite(STDERR, "Expected two duplicate groups for bulk dismissal.\n");
    exit(1);
}

$count = $duplicates->ignoreGroupsBySignatures($signatures, $adminId);
if ($count !== 2) {
    fwrite(STDERR, "Bulk dismissal returned an unexpected count.\n");
    exit(1);
}

$remaining = [];
foreach ($duplicates->find(500) as $group) {
    $signature = (string) ($group['signature'] ?? '');
    if (in_array($signature, $signatures, true)) {
        $remaining[] = $signature;
    }
}

if ($remaining !== []) {
    fwrite(STDERR, "Bulk-dismissed groups are still visible.\n");
    exit(1);
}

echo "People 1.7.20 bulk duplicate dismissal runtime OK\n";
