<?php

declare(strict_types=1);

define('_JEXEC', 1);

require_once __DIR__ . '/../component/admin/src/Service/DatabaseSchemaInspector.php';

use xdecaro\Component\People\Administrator\Service\DatabaseSchemaInspector;

$reflection = new ReflectionClass(DatabaseSchemaInspector::class);
$inspector = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod('columnCompatible');
$method->setAccessible(true);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$compatible = static function (string $fragment, array $actual) use ($method, $inspector): bool {
    return (bool) $method->invoke($inspector, $fragment, $actual);
};

$assert(
    $compatible('`id` INT UNSIGNED NOT NULL AUTO_INCREMENT', [
        'Type' => 'int(10) unsigned',
        'Null' => 'NO',
        'Default' => null,
        'Extra' => 'auto_increment',
    ]),
    'MariaDB int(10) unsigned must be compatible with canonical INT UNSIGNED.'
);

$assert(
    $compatible('`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', [
        'Type' => 'bigint(20) unsigned',
        'Null' => 'NO',
        'Default' => null,
        'Extra' => 'auto_increment',
    ]),
    'MariaDB bigint(20) unsigned must be compatible with canonical BIGINT UNSIGNED.'
);

$assert(
    $compatible('`state` TINYINT NOT NULL DEFAULT 1', [
        'Type' => 'tinyint(4)',
        'Null' => 'NO',
        'Default' => '1',
        'Extra' => '',
    ]),
    'MariaDB tinyint(4) must be compatible with canonical TINYINT.'
);

$assert(
    !$compatible('`user_id` INT UNSIGNED DEFAULT NULL', [
        'Type' => 'int(10)',
        'Null' => 'YES',
        'Default' => null,
        'Extra' => '',
    ]),
    'UNSIGNED must still be enforced.'
);

$assert(
    !$compatible('`id` INT UNSIGNED NOT NULL', [
        'Type' => 'bigint(20) unsigned',
        'Null' => 'NO',
        'Default' => null,
        'Extra' => '',
    ]),
    'Different integer families must remain incompatible.'
);

$assert(
    !$compatible('`display_name` VARCHAR(255) NOT NULL', [
        'Type' => 'varchar(190)',
        'Null' => 'NO',
        'Default' => null,
        'Extra' => '',
    ]),
    'Non-integer widths must remain significant.'
);

echo "People 1.7.31 MariaDB integer-width contract: OK\n";
