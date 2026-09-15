<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$servicePath = $root . '/component/admin/src/Service/NotificationIntegrationService.php';
$provider = file_get_contents($root . '/component/admin/services/provider.php') ?: '';
$component = file_get_contents($root . '/component/admin/src/Extension/PeopleComponent.php') ?: '';
$personController = file_get_contents($root . '/component/admin/src/Controller/PersonController.php') ?: '';
$peopleController = file_get_contents($root . '/component/admin/src/Controller/PeopleController.php') ?: '';
$config = file_get_contents($root . '/component/admin/config.xml') ?: '';
$it = file_get_contents($root . '/component/admin/language/it-IT/com_xdecaropeople.ini') ?: '';
$en = file_get_contents($root . '/component/admin/language/en-GB/com_xdecaropeople.ini') ?: '';

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$expect(is_file($servicePath), 'NotificationIntegrationService is missing.');
$service = is_file($servicePath) ? (file_get_contents($servicePath) ?: '') : '';

foreach ([
    'final class NotificationIntegrationService',
    'IMPORTANT_NOTIFICATION_FIELDS',
    'function snapshot(',
    'function importantChangedFields(',
    "ComponentHelper::getParams('com_xdecaropeople')",
    'notification_recipient_user_id',
    "bootComponent('com_xdecaronotifications')",
    'getNotificationService()->create(',
    "getDeliveryService()->queueForNotification(\$notificationId, ['in_app'])",
    "'source_component' => 'com_xdecaropeople'",
    "'source_entity' => 'person'",
    "'recipient_type' => 'user'",
    "'category' => 'people'",
    'function notifyPersonCreated(',
    'function notifyPersonUpdated(',
    'function notifyPersonStateChanged(',
    'function notifyPossibleDuplicate(',
    'Log::add(',
] as $marker) {
    $expect(str_contains($service, $marker), "Notification integration missing marker: {$marker}");
}

$expect(str_contains($provider, 'NotificationIntegrationService::class'), 'DI provider must register NotificationIntegrationService.');
$expect(str_contains($provider, 'new NotificationIntegrationService('), 'DI provider must construct NotificationIntegrationService.');
$expect(str_contains($provider, 'setNotificationIntegrationService'), 'DI provider must inject NotificationIntegrationService into PeopleComponent.');
$expect(str_contains($component, 'getNotificationIntegrationService'), 'PeopleComponent must expose NotificationIntegrationService.');

$expect((bool) preg_match('/name="notification_recipient_user_id"[\s\S]*?type="user"/i', $config), 'People options must expose a Joomla user recipient field.');

foreach ([
    'COM_XDECAROPEOPLE_CONFIG_NOTIFICATIONS',
    'COM_XDECAROPEOPLE_CONFIG_NOTIFICATION_RECIPIENT',
    'COM_XDECAROPEOPLE_CONFIG_NOTIFICATION_RECIPIENT_DESC',
    'COM_XDECAROPEOPLE_NOTIFICATION_CREATED_TITLE',
    'COM_XDECAROPEOPLE_NOTIFICATION_CREATED_MESSAGE',
    'COM_XDECAROPEOPLE_NOTIFICATION_UPDATED_TITLE',
    'COM_XDECAROPEOPLE_NOTIFICATION_UPDATED_MESSAGE',
    'COM_XDECAROPEOPLE_NOTIFICATION_STATE_TITLE',
    'COM_XDECAROPEOPLE_NOTIFICATION_PUBLISHED_MESSAGE',
    'COM_XDECAROPEOPLE_NOTIFICATION_UNPUBLISHED_MESSAGE',
    'COM_XDECAROPEOPLE_NOTIFICATION_TRASHED_MESSAGE',
    'COM_XDECAROPEOPLE_NOTIFICATION_RESTORED_MESSAGE',
    'COM_XDECAROPEOPLE_NOTIFICATION_DUPLICATE_TITLE',
    'COM_XDECAROPEOPLE_NOTIFICATION_DUPLICATE_MESSAGE',
] as $key) {
    $expect(str_contains($it, $key . '='), "Missing Italian notification translation: {$key}");
    $expect(str_contains($en, $key . '='), "Missing English notification translation: {$key}");
}

foreach ([
    'public function save($key = null, $urlVar = null)',
    'notifyPersonCreated(',
    'importantChangedFields(',
    'notifyPersonUpdated(',
    'notifyPersonStateChanged(',
    'getDuplicateService()->hasMatch(',
    'notifyPossibleDuplicate(',
] as $marker) {
    $expect(str_contains($personController, $marker), "PersonController notification contract missing marker: {$marker}");
}

foreach ([
    'public function publish()',
    'parent::publish();',
    'notifyPersonStateChanged(',
] as $marker) {
    $expect(str_contains($peopleController, $marker), "PeopleController state notification contract missing marker: {$marker}");
}

$expect(!str_contains($service, "'tax_identifier' =>"), 'Notification payload must not expose tax identifier values.');
$expect(!str_contains($service, "'disability_status' =>"), 'Notification payload must not expose disability values.');
$expect(!str_contains($service, "'accessibility_needs' =>"), 'Notification payload must not expose accessibility values.');
$expect(!str_contains($service, "'address_line' =>"), 'Notification payload must not expose address values.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "People notifications contract OK\n";
