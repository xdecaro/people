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
$q = $db->getQuery(true)
    ->select($db->quoteName('id'))
    ->from($db->quoteName('#__users'))
    ->where($db->quoteName('username') . ' = ' . $db->quote('admin'));
$adminId = (int) $db->setQuery($q, 0, 1)->loadResult();
$app->loadIdentity($container->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($adminId));

$duplicates = $app->bootComponent('com_xdecaropeople')->getDuplicateService();
$now = \Joomla\CMS\Factory::getDate()->toSql();

$complete = (object) [
    'uuid' => 'a1111111-1111-4111-8111-111111111111',
    'display_name' => 'Gianluca Rezza CI',
    'first_name' => 'Gianluca',
    'last_name' => 'Rezza CI',
    'birth_date' => '1998-11-14',
    'sex' => 'M',
    'tax_identifier' => 'RZZGLC98S14H501J',
    'birth_place' => 'ROMA',
    'person_status' => 'active',
    'state' => 1,
    'access' => 1,
    'created' => $now,
    'created_by' => $adminId,
];
$db->insertObject('#__xdecaropeople_people', $complete, 'id');

$sparse = (object) [
    'uuid' => 'b2222222-2222-4222-8222-222222222222',
    'display_name' => 'Gianluca Rezza CI',
    'first_name' => 'Gianluca',
    'last_name' => 'Rezza CI',
    'person_status' => 'active',
    'state' => 1,
    'access' => 1,
    'created' => $now,
    'created_by' => $adminId,
];
$db->insertObject('#__xdecaropeople_people', $sparse, 'id');

$key = 'gianluca|rezza ci';
$allowed = false;
foreach ($duplicates->find(500) as $group) {
    if (($group['type'] ?? '') === 'name' && ($group['key'] ?? '') === $key) {
        $allowed = ($group['merge_allowed'] ?? false) === true
            && ($group['manual_merge'] ?? false) === true;
        break;
    }
}

if (!$allowed) {
    fwrite(STDERR, "Safe same-name manual merge was not exposed.\n");
    exit(1);
}

$result = $duplicates->mergeGroup(
    (int) $complete->id,
    'name',
    $key,
    [(int) $complete->id, (int) $sparse->id],
    $adminId
);

if ((int) ($result['target_id'] ?? 0) !== (int) $complete->id) {
    fwrite(STDERR, "Wrong manual merge target.\n");
    exit(1);
}

$conflictA = (object) [
    'uuid' => 'c3333333-3333-4333-8333-333333333333',
    'display_name' => 'Paolo Verdi CI',
    'first_name' => 'Paolo',
    'last_name' => 'Verdi CI',
    'birth_date' => '1970-01-01',
    'sex' => 'M',
    'tax_identifier' => 'VRDPLA70A01H501A',
    'person_status' => 'active',
    'state' => 1,
    'access' => 1,
    'created' => $now,
    'created_by' => $adminId,
];
$db->insertObject('#__xdecaropeople_people', $conflictA, 'id');

$conflictB = (object) [
    'uuid' => 'd4444444-4444-4444-8444-444444444444',
    'display_name' => 'Paolo Verdi CI',
    'first_name' => 'Paolo',
    'last_name' => 'Verdi CI',
    'birth_date' => '1980-02-02',
    'sex' => 'M',
    'tax_identifier' => 'VRDPLA80B02H501B',
    'person_status' => 'active',
    'state' => 1,
    'access' => 1,
    'created' => $now,
    'created_by' => $adminId,
];
$db->insertObject('#__xdecaropeople_people', $conflictB, 'id');

$blocked = false;
try {
    $duplicates->mergeGroup(
        (int) $conflictA->id,
        'name',
        'paolo|verdi ci',
        [(int) $conflictA->id, (int) $conflictB->id],
        $adminId
    );
} catch (\RuntimeException $exception) {
    $blocked = $exception->getCode() === 400;
}

if (!$blocked) {
    fwrite(STDERR, "Conflicting identity data did not block manual name merge.\n");
    exit(1);
}

echo "People 1.7.19 safe manual possible merge runtime OK\n";
