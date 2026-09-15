<?php

namespace xdecaro\Component\People\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;
use Throwable;

final class NotificationIntegrationService
{
    private const IMPORTANT_NOTIFICATION_FIELDS = [
        'first_name', 'last_name', 'preferred_name', 'user_id',
        'email', 'phone', 'whatsapp', 'preferred_contact', 'language',
        'birth_date', 'sex', 'birth_country_code', 'birth_place', 'birth_place_id', 'birth_region',
        'nationality_code', 'nationality_codes',
        'disability_status', 'disability_types', 'disability_other',
        'accessibility_needs', 'accessibility_other',
        'tax_identifier',
        'address_line', 'address_number', 'postal_code', 'city', 'region', 'country_code',
        'residence_place_id', 'additional_addresses', 'relations_data',
        'profile_document_uuid', 'person_status', 'access',
        'social_instagram', 'social_facebook', 'social_linkedin', 'social_tiktok',
        'social_telegram', 'social_x', 'social_youtube', 'website_url',
    ];

    public function __construct(private DatabaseInterface $db)
    {
    }

    public function snapshot(int $personId): ?array
    {
        if ($personId < 1) {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__xdecaropeople_people'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $personId, ParameterType::INTEGER);

        $row = $this->db->setQuery($query, 0, 1)->loadAssoc();
        return $row ?: null;
    }

    public function importantChangedFields(array $before, array $after): array
    {
        $changed = [];

        foreach (self::IMPORTANT_NOTIFICATION_FIELDS as $field) {
            if ((string) ($before[$field] ?? '') !== (string) ($after[$field] ?? '')) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    public function notifyPersonCreated(array $person): void
    {
        if (!$this->eventEnabled('created')) {
            return;
        }

        $this->loadLanguage();
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
        if (!$this->eventEnabled('updated') || $changedFields === []) {
            return;
        }

        $this->loadLanguage();
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
        if (!$this->eventEnabled('state') || $previousState === $newState) {
            return;
        }

        $this->loadLanguage();
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
        if (!$this->eventEnabled('possible_duplicate')) {
            return;
        }

        $this->loadLanguage();
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

    private function eventEnabled(string $eventType): bool
    {
        $configured = ComponentHelper::getParams('com_xdecaropeople')->get(
            'notification_event_types',
            ['created', 'possible_duplicate']
        );

        if (is_string($configured)) {
            $configured = preg_split('/\s*,\s*/', trim($configured), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        } elseif (!is_array($configured)) {
            $configured = (array) $configured;
        }

        $enabled = array_values(array_unique(array_map('strval', $configured)));

        return in_array($eventType, $enabled, true);
    }

    private function loadLanguage(): void
    {
        Factory::getApplication()->getLanguage()->load('com_xdecaropeople', JPATH_ADMINISTRATOR);
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
