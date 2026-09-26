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
$inspector = $component->getDatabaseSchemaInspector();

$clean = $inspector->inspect();
if (($clean['status'] ?? '') !== 'OK' || empty($clean['ok'])) {
    fwrite(STDERR, "Fresh People schema must inspect as OK.\n");
    exit(1);
}

$people = $db->quoteName('#__xdecaropeople_people');
$customTable = $db->quoteName('#__xdecaropeople_custom_test');

try {
    $db->setQuery("ALTER TABLE {$people} DROP INDEX `idx_people_birth`")->execute();
    $missingIndex = $inspector->inspect();
    if (!in_array('idx_people_birth', (array) (($missingIndex['missing_indexes']['#__xdecaropeople_people'] ?? [])), true)) {
        fwrite(STDERR, "Inspector did not report missing idx_people_birth.\n");
        exit(1);
    }
    $db->setQuery("ALTER TABLE {$people} ADD KEY `idx_people_birth` (`birth_date`)")->execute();

    $db->setQuery("ALTER TABLE {$people} ADD `custom_probe` VARCHAR(16) DEFAULT NULL")->execute();
    $customColumn = $inspector->inspect();
    if (!in_array('custom_probe', (array) (($customColumn['unknown_columns']['#__xdecaropeople_people'] ?? [])), true)) {
        fwrite(STDERR, "Inspector did not report custom_probe as unknown.\n");
        exit(1);
    }

    $db->setQuery("CREATE TABLE {$customTable} (`id` INT NOT NULL PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")->execute();
    $unexpected = $inspector->inspect();
    if (!in_array('#__xdecaropeople_custom_test', (array) ($unexpected['unexpected_tables'] ?? []), true)) {
        fwrite(STDERR, "Inspector did not report unexpected People-prefixed table.\n");
        exit(1);
    }
} finally {
    try { $db->setQuery("DROP TABLE IF EXISTS {$customTable}")->execute(); } catch (\Throwable) {}
    try { $db->setQuery("ALTER TABLE {$people} DROP COLUMN `custom_probe`")->execute(); } catch (\Throwable) {}
    try { $db->setQuery("ALTER TABLE {$people} ADD KEY `idx_people_birth` (`birth_date`)")->execute(); } catch (\Throwable) {}
}

$final = $inspector->inspect();
if (($final['status'] ?? '') !== 'OK' || empty($final['ok'])) {
    fwrite(STDERR, "Schema did not return to OK after probe cleanup.\n");
    exit(1);
}

echo "People 1.7.29 database schema runtime PASS\n";
