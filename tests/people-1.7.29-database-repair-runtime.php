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
$maintenance = $component->getDatabaseMaintenanceService();
$inspector = $component->getDatabaseSchemaInspector();
$people = $db->quoteName('#__xdecaropeople_people');
$fakePeople = $db->quoteName('#__xdecaropeople_custom_test');
$external = $db->quoteName('#__outside_people_probe');

try {
    $db->setQuery("ALTER TABLE {$people} DROP INDEX `idx_people_email`")->execute();
    $db->setQuery("ALTER TABLE {$people} ADD `custom_probe` VARCHAR(16) DEFAULT NULL")->execute();
    $db->setQuery("ALTER TABLE {$people} ADD KEY `idx_custom_probe` (`custom_probe`)")->execute();
    $db->setQuery("CREATE TABLE {$fakePeople} (`id` INT NOT NULL PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")->execute();
    $db->setQuery("CREATE TABLE {$external} (`id` INT NOT NULL PRIMARY KEY, `email` VARCHAR(254) DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")->execute();

    $before = $inspector->inspect();
    if (!in_array('idx_people_email', (array) (($before['missing_indexes']['#__xdecaropeople_people'] ?? [])), true)) {
        fwrite(STDERR, "Repair setup did not create expected missing index drift.\n");
        exit(1);
    }

    $result = $maintenance->repair($adminId);
    if (($result['after']['status'] ?? '') !== 'Da aggiornare') {
        fwrite(STDERR, "Repair should leave unknown custom objects reported as drift.\n");
        exit(1);
    }
    $after = $inspector->inspect();
    if (in_array('idx_people_email', (array) (($after['missing_indexes']['#__xdecaropeople_people'] ?? [])), true)) {
        fwrite(STDERR, "Repair did not restore canonical idx_people_email.\n");
        exit(1);
    }
    if (!in_array('custom_probe', (array) (($after['unknown_columns']['#__xdecaropeople_people'] ?? [])), true)) {
        fwrite(STDERR, "Repair removed or hid custom_probe unexpectedly.\n");
        exit(1);
    }
    if (!in_array('idx_custom_probe', (array) (($after['unknown_indexes']['#__xdecaropeople_people'] ?? [])), true)) {
        fwrite(STDERR, "Repair removed or hid custom index unexpectedly.\n");
        exit(1);
    }
    if (!in_array('#__xdecaropeople_custom_test', (array) ($after['unexpected_tables'] ?? []), true)) {
        fwrite(STDERR, "Repair removed unexpected People-prefixed table unexpectedly.\n");
        exit(1);
    }

    $externalColumns = (array) $db->setQuery("SHOW COLUMNS FROM {$external}")->loadAssocList();
    $externalNames = array_map(static fn(array $row): string => (string) ($row['Field'] ?? ''), $externalColumns);
    if (!in_array('email', $externalNames, true)) {
        fwrite(STDERR, "Repair touched non-People table.\n");
        exit(1);
    }
} finally {
    try { $db->setQuery("DROP TABLE IF EXISTS {$fakePeople}")->execute(); } catch (\Throwable) {}
    try { $db->setQuery("DROP TABLE IF EXISTS {$external}")->execute(); } catch (\Throwable) {}
    try { $db->setQuery("ALTER TABLE {$people} DROP INDEX `idx_custom_probe`")->execute(); } catch (\Throwable) {}
    try { $db->setQuery("ALTER TABLE {$people} DROP COLUMN `custom_probe`")->execute(); } catch (\Throwable) {}
    try { $db->setQuery("ALTER TABLE {$people} ADD KEY `idx_people_email` (`email`)")->execute(); } catch (\Throwable) {}
}

$final = $inspector->inspect();
if (($final['status'] ?? '') !== 'OK') {
    fwrite(STDERR, "Schema did not return to OK after repair probe cleanup.\n");
    exit(1);
}

echo "People 1.7.29 database repair runtime PASS\n";
