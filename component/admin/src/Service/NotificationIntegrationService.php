<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use RuntimeException;
use Throwable;

final class NotificationIntegrationService
{
    public function notifyPersonCreated(array $person): void
    {
        $this->emit(
            $person,
            'created',
            Text::_('COM_XDECAROPEOPLE_NOTIFICATION_CREATED_TITLE'),
            Text::sprintf('COM_XDECAROPEOPLE_NOTIFICATION_CREATED_MESSAGE', $this->displayName($person)),
            'normal',
            $this->personActionUrl($person)
        );
    }

    public function notifyPersonUpdated(array $person, array $changedFields): void
    {
        if ($changedFields === []) {
            return;
        }

        $this->emit(
            $person,
            'updated:' . hash('sha256', implode('|', array_values($changedFields))),
            Text::_('COM_XDECAROPEOPLE_NOTIFICATION_UPDATED_TITLE'),
            Text::sprintf('COM_XDECAROPEOPLE_NOTIFICATION_UPDATED_MESSAGE', $this->displayName($person)),
            'normal',
            $this->personActionUrl($person)
        );
    }

    public function notifyPersonStateChanged(array $person, int $previousState, int $newState): void
    {
        if ($previousState === $newState) {
            return;
        }

        $messageKey = match (true) {
            $newState === -2 => 'COM_XDECAROPEOPLE_NOTIFICATION_TRASHED_MESSAGE',
            $previousState === -2 && $newState === 1 => 'COM_XDECAROPEOPLE_NOTIFICATION_RESTORED_MESSAGE',
            $newState === 1 => 'COM_XDECAROPEOPLE_NOTIFICATION_PUBLISHED_MESSAGE',
            $newState === 0 => 'COM_XDECAROPEOPLE_NOTIFICATION_UNPUBLISHED_MESSAGE',
            default => null,
        };

        if ($messageKey === null) {
            return;
        }

        $this->emit(
            $person,
            'state:' . $previousState . ':' . $newState . ':' . sprintf('%.6F', microtime(true)),
            Text::_('COM_XDECAROPEOPLE_NOTIFICATION_STATE_TITLE'),
            Text::sprintf($messageKey, $this->displayName($person)),
            'normal',
            $this->personActionUrl($person)
        );
    }

    public function notifyPossibleDuplicate(array $person): void
    {
        $this->emit(
            $person,
            'possible-duplicate',
            Text::_('COM_XDECAROPEOPLE_NOTIFICATION_DUPLICATE_TITLE'),
            Text::sprintf('COM_XDECAROPEOPLE_NOTIFICATION_DUPLICATE_MESSAGE', $this->displayName($person)),
            'high',
            'index.php?option=com_xdecaropeople&view=duplicates'
        );
    }

    private function emit(
        array $person,
        string $event,
        string $title,
        string $message,
        string $priority,
        string $actionUrl
    ): void {
        $recipientUserId = $this->recipientUserId();
        $personId = (int) ($person['id'] ?? 0);

        if ($recipientUserId < 1 || $personId < 1) {
            return;
        }

        try {
            $component = Factory::getApplication()->bootComponent('com_xdecaronotifications');
            if (!is_object($component)
                || !method_exists($component, 'getNotificationService')
                || !method_exists($component, 'getDeliveryService')) {
                throw new RuntimeException('Notifications component public services are unavailable.');
            }

            $fingerprint = hash(
                'sha256',
                json_encode([$event, $personId, $person], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: $event
            );

            $notificationId = $component->getNotificationService()->create([
                'external_key' => 'people:person:' . $personId . ':' . $fingerprint,
                'source_component' => 'com_xdecaropeople',
                'source_entity' => 'person',
                'source_id' => (string) $personId,
                'recipient_type' => 'user',
                'recipient_id' => (string) $recipientUserId,
                'category' => 'people',
                'priority' => $priority,
                'title' => $title,
                'message' => $message,
                'action_url' => $actionUrl,
                'created_by' => (int) Factory::getApplication()->getIdentity()->id,
            ]);

            $component->getDeliveryService()->queueForNotification($notificationId, ['in_app']);
        } catch (Throwable $exception) {
            Log::add(
                'People notification integration failed: ' . $exception->getMessage(),
                Log::WARNING,
                'com_xdecaropeople'
            );
        }
    }

    private function recipientUserId(): int
    {
        return max(
            0,
            (int) ComponentHelper::getParams('com_xdecaropeople')->get('notification_recipient_user_id', 0)
        );
    }

    private function personActionUrl(array $person): string
    {
        return 'index.php?option=com_xdecaropeople&task=person.edit&id=' . (int) ($person['id'] ?? 0);
    }

    private function displayName(array $person): string
    {
        $name = trim((string) ($person['display_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $name = trim((string) ($person['first_name'] ?? '') . ' ' . (string) ($person['last_name'] ?? ''));
        return $name !== '' ? $name : Text::_('COM_XDECAROPEOPLE_PERSON_EDIT');
    }
}
