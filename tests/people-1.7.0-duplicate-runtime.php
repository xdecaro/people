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

$component = $app->bootComponent('com_xdecaropeople');
$duplicates = $component->getDuplicateService();
$people = $component->getPersonProviderService();
$now = \Joomla\CMS\Factory::getDate()->toSql();

$target = (object) [
    'uuid' => '22222222-2222-4222-8222-222222222222',
    'display_name' => 'Mario Rossi',
    'first_name' => 'Mario',
    'last_name' => 'Rossi',
    'birth_date' => '1980-01-01',
    'tax_identifier' => 'RSSMRA80A01H501U',
    'email' => 'merge-target@example.invalid',
    'phone' => null,
    'person_status' => 'active',
    'state' => 1,
    'access' => 1,
    'created' => $now,
    'created_by' => $adminId,
];
$db->insertObject('#__xdecaropeople_people', $target, 'id');

$source = (object) [
    'uuid' => '33333333-3333-4333-8333-333333333333',
    'display_name' => 'Mario Rossi',
    'first_name' => 'Mario',
    'last_name' => 'Rossi',
    'birth_date' => '1980-01-01',
    'tax_identifier' => 'RSSMRA80A01H501U',
    'email' => 'merge-source@example.invalid',
    'phone' => '+3906999999',
    'person_status' => 'active',
    'state' => 1,
    'access' => 1,
    'created' => $now,
    'created_by' => $adminId,
];
$db->insertObject('#__xdecaropeople_people', $source, 'id');

$result = $duplicates->mergeGroup(
    (int) $target->id,
    'tax_identifier',
    'RSSMRA80A01H501U',
    [(int) $target->id, (int) $source->id],
    $adminId
);

if ((int) ($result['target_id'] ?? 0) !== (int) $target->id) {
    fwrite(STDERR, "Unexpected canonical target.\n");
    exit(1);
}

$query = $db->getQuery(true)
    ->select([$db->quoteName('state'), $db->quoteName('person_status')])
    ->from($db->quoteName('#__xdecaropeople_people'))
    ->where($db->quoteName('id') . ' = ' . (int) $source->id);
$sourceState = (array) $db->setQuery($query)->loadAssoc();

if ((int) ($sourceState['state'] ?? 1) !== 0 || ($sourceState['person_status'] ?? '') !== 'archived') {
    fwrite(STDERR, "Merged source was not archived.\n");
    exit(1);
}

$query = $db->getQuery(true)
    ->select($db->quoteName('phone'))
    ->from($db->quoteName('#__xdecaropeople_people'))
    ->where($db->quoteName('id') . ' = ' . (int) $target->id);

if ((string) $db->setQuery($query)->loadResult() !== '+3906999999') {
    fwrite(STDERR, "Missing source data was not copied to the canonical record.\n");
    exit(1);
}

$resolved = $people->getPerson('33333333-3333-4333-8333-333333333333', true);
if ((int) ($resolved['id'] ?? 0) !== (int) $target->id) {
    fwrite(STDERR, "Merged UUID did not resolve to the canonical person.\n");
    exit(1);
}

if (($resolved['merged_from_uuid'] ?? '') !== '33333333-3333-4333-8333-333333333333') {
    fwrite(STDERR, "Merged UUID provenance was not exposed.\n");
    exit(1);
}

$query = $db->getQuery(true)
    ->select('COUNT(*)')
    ->from($db->quoteName('#__xdecaropeople_merges'))
    ->where($db->quoteName('source_person_id') . ' = ' . (int) $source->id)
    ->where($db->quoteName('target_person_id') . ' = ' . (int) $target->id);

if ((int) $db->setQuery($query)->loadResult() !== 1) {
    fwrite(STDERR, "Merge alias was not persisted.\n");
    exit(1);
}

echo "People 1.7.0 duplicate merge runtime OK\n";
