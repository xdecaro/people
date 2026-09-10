<?php

namespace xdecaro\Component\People\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
use Throwable;
use xdecaro\Component\People\Administrator\Service\CountryMetadata;

final class PersonModel extends AdminModel
{
    protected $text_prefix = 'COM_XDECAROPEOPLE';

    private const DISABILITY_TYPES = [
        'hearing', 'visual', 'motor', 'intellectual_cognitive', 'psychosocial', 'multiple', 'other',
    ];

    private const ACCESSIBILITY_NEEDS = [
        'sign_language_interpreter', 'captions', 'wheelchair_access', 'companion', 'written_communication', 'other',
    ];

    private const CONTACT_METHODS = ['email', 'phone', 'whatsapp', 'other'];
    private const PERSON_STATUSES = ['active', 'archived', 'deceased'];
    private const RELATION_TYPES = ['parent', 'child', 'guardian', 'responsible', 'spouse', 'other'];
    private const ADDRESS_TYPES = ['domicile', 'correspondence', 'billing', 'other'];

    public function getTable($type = 'Person', $prefix = 'Administrator', $config = []): Table
    {
        return parent::getTable($type, $prefix, $config);
    }

    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_xdecaropeople.person', 'person', ['control' => 'jform', 'load_data' => $loadData]);
        if (!$form) {
            return false;
        }

        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('people.view_sensitive', 'com_xdecaropeople') && !$user->authorise('core.admin', 'com_xdecaropeople')) {
            foreach ([
                'birth_date',
                'sex',
                'disability_status',
                'disability_types',
                'disability_other',
                'accessibility_needs',
                'accessibility_other',
                'nationality_codes',
                'nationality_code',
                'birth_country_code',
                'birth_place',
                'birth_place_id',
                'birth_region',
                'tax_identifier',
                'address_line',
                'address_number',
                'postal_code',
                'city',
                'region',
                'country_code',
                'residence_place_id',
                'additional_addresses',
                'relations_data',
                'profile_document_uuid',
                'notes',
            ] as $field) {
                $form->removeField($field);
            }
        }

        return $form;
    }

    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_xdecaropeople.edit.person.data', []);
        if (!$data) {
            $data = $this->getItem();
        }

        if (is_object($data)) {
            foreach (['disability_types', 'accessibility_needs', 'nationality_codes', 'additional_addresses', 'relations_data'] as $field) {
                $data->{$field} = $this->decodeJsonList($data->{$field} ?? null);
            }

            if (empty($data->nationality_codes) && !empty($data->nationality_code)) {
                $data->nationality_codes = [(string) $data->nationality_code];
            }
        } elseif (is_array($data)) {
            foreach (['disability_types', 'accessibility_needs', 'nationality_codes', 'additional_addresses', 'relations_data'] as $field) {
                if (isset($data[$field]) && is_string($data[$field])) {
                    $data[$field] = $this->decodeJsonList($data[$field]);
                }
            }

            if (empty($data['nationality_codes']) && !empty($data['nationality_code'])) {
                $data['nationality_codes'] = [(string) $data['nationality_code']];
            }
        }

        return $data;
    }

    protected function prepareTable($table): void
    {
        $now = Factory::getDate()->toSql();
        $userId = (int) Factory::getApplication()->getIdentity()->id;

        if (empty($table->uuid)) {
            $table->uuid = self::uuidV4();
        }

        $table->display_name = trim((string) $table->first_name . ' ' . (string) $table->last_name);

        if (empty($table->source_component)) {
            $table->source_component = 'com_xdecaropeople';
        }

        if (empty($table->id)) {
            $table->created = $now;
            $table->created_by = $userId;
        } else {
            $table->modified = $now;
            $table->modified_by = $userId;
        }
    }

    public function save($data): bool
    {
        $id = (int) ($data['id'] ?? 0);
        $before = $id > 0 ? $this->loadAuditRow($id) : null;

        $data['first_name'] = trim((string) ($data['first_name'] ?? ''));
        $data['last_name'] = trim((string) ($data['last_name'] ?? ''));
        $data['preferred_name'] = $this->nullableString($data['preferred_name'] ?? null);
        $data['display_name'] = trim($data['first_name'] . ' ' . $data['last_name']);
        $data['user_id'] = !empty($data['user_id']) ? (int) $data['user_id'] : null;

        if (array_key_exists('birth_date', $data)) {
            $birthDate = trim((string) ($data['birth_date'] ?? ''));
            $data['birth_date'] = $birthDate !== '' ? $birthDate : null;
        }

        if (array_key_exists('sex', $data)) {
            $sex = strtoupper(trim((string) ($data['sex'] ?? '')));
            $data['sex'] = $sex !== '' ? $sex : null;
        }

        if (array_key_exists('disability_status', $data)) {
            $value = $data['disability_status'];
            $data['disability_status'] = $value === '' || $value === null ? null : (int) $value;
        }

        if (array_key_exists('disability_types', $data)) {
            $types = $this->normalizeAllowedList($data['disability_types'], self::DISABILITY_TYPES);
            if (($data['disability_status'] ?? null) !== 1) {
                $types = [];
            }
            $data['disability_types'] = $this->encodeJsonList($types);
            $data['disability_other'] = in_array('other', $types, true)
                ? $this->nullableString($data['disability_other'] ?? null)
                : null;
        }

        if (array_key_exists('accessibility_needs', $data)) {
            $needs = $this->normalizeAllowedList($data['accessibility_needs'], self::ACCESSIBILITY_NEEDS);
            $data['accessibility_needs'] = $this->encodeJsonList($needs);
            $data['accessibility_other'] = in_array('other', $needs, true)
                ? $this->nullableString($data['accessibility_other'] ?? null)
                : null;
        }

        if (array_key_exists('nationality_codes', $data)) {
            $codes = [];
            foreach ((array) $data['nationality_codes'] as $code) {
                $code = strtoupper(trim((string) $code));
                if ($code !== '' && CountryMetadata::isAlpha3($code)) {
                    $codes[$code] = $code;
                }
            }
            $codes = array_values($codes);
            $data['nationality_codes'] = $this->encodeJsonList($codes);
            $data['nationality_code'] = $codes[0] ?? null;
        }

        foreach (['birth_country_code', 'country_code'] as $field) {
            if (array_key_exists($field, $data)) {
                $code = strtoupper(trim((string) ($data[$field] ?? '')));
                $data[$field] = $code !== '' ? $code : null;
            }
        }

        foreach (['birth_place', 'birth_place_id', 'birth_region', 'residence_place_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->nullableString($data[$field]);
            }
        }

        if (array_key_exists('preferred_contact', $data)) {
            $method = strtolower(trim((string) ($data['preferred_contact'] ?? '')));
            $data['preferred_contact'] = in_array($method, self::CONTACT_METHODS, true) ? $method : null;
        }

        if (array_key_exists('person_status', $data)) {
            $status = strtolower(trim((string) ($data['person_status'] ?? 'active')));
            $data['person_status'] = in_array($status, self::PERSON_STATUSES, true) ? $status : 'active';
        }

        if (array_key_exists('profile_document_uuid', $data)) {
            $uuid = strtolower(trim((string) ($data['profile_document_uuid'] ?? '')));
            $data['profile_document_uuid'] = $uuid === '' ? null : $uuid;
        }

        if (empty($data['source_component'])) {
            $data['source_component'] = 'com_xdecaropeople';
        }

        foreach (['social_instagram', 'social_facebook', 'social_linkedin', 'social_tiktok', 'social_telegram', 'social_x', 'social_youtube', 'website_url'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->normalizeUrl($data[$field]);
            }
        }

        if (array_key_exists('additional_addresses', $data)) {
            $data['additional_addresses'] = $this->encodeJsonList($this->normalizeAddresses($data['additional_addresses']));
        }

        if (array_key_exists('relations_data', $data)) {
            $data['relations_data'] = $this->encodeJsonList($this->normalizeRelations($data['relations_data'], $data['uuid'] ?? null));
        }

        if (!$this->validateUniqueUser($data)) {
            return false;
        }

        $this->warnPossibleDuplicate($data, $id);

        if (!parent::save($data)) {
            return false;
        }

        $savedId = (int) $this->getState($this->getName() . '.id');
        if ($savedId < 1) {
            $savedId = $id;
        }

        if ($savedId > 0) {
            $this->writeHistory($savedId, $before, $id > 0 ? 'update' : 'create');
        }

        return true;
    }

    protected function canDelete($record): bool
    {
        return Factory::getApplication()->getIdentity()->authorise('core.delete', 'com_xdecaropeople');
    }

    protected function canEditState($record): bool
    {
        return Factory::getApplication()->getIdentity()->authorise('core.edit.state', 'com_xdecaropeople');
    }

    private function validateUniqueUser(array $data): bool
    {
        $userId = (int) ($data['user_id'] ?? 0);
        if ($userId < 1) {
            return true;
        }

        $id = (int) ($data['id'] ?? 0);
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__xdecaropeople_people'))
            ->where($db->quoteName('user_id') . ' = :uid')
            ->where($db->quoteName('id') . ' <> :id')
            ->bind(':uid', $userId, ParameterType::INTEGER)
            ->bind(':id', $id, ParameterType::INTEGER);

        if ((int) $db->setQuery($query)->loadResult() > 0) {
            $this->setError(Text::_('COM_XDECAROPEOPLE_ERROR_USER_ALREADY_LINKED'));
            return false;
        }

        return true;
    }

    private function warnPossibleDuplicate(array $data, int $excludeId): void
    {
        $db = $this->getDatabase();
        $conditions = [];
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__xdecaropeople_people'))
            ->where($db->quoteName('state') . ' >= 0')
            ->where($db->quoteName('id') . ' <> :excludeId')
            ->bind(':excludeId', $excludeId, ParameterType::INTEGER);

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($email !== '') {
            $conditions[] = 'LOWER(TRIM(' . $db->quoteName('email') . ')) = :dupEmail';
            $query->bind(':dupEmail', $email);
        }

        $tin = strtoupper(trim((string) ($data['tax_identifier'] ?? '')));
        if ($tin !== '') {
            $conditions[] = 'UPPER(TRIM(' . $db->quoteName('tax_identifier') . ')) = :dupTin';
            $query->bind(':dupTin', $tin);
        }

        $firstName = strtolower(trim((string) ($data['first_name'] ?? '')));
        $lastName = strtolower(trim((string) ($data['last_name'] ?? '')));
        $birthDate = trim((string) ($data['birth_date'] ?? ''));
        if ($firstName !== '' && $lastName !== '' && $birthDate !== '') {
            $conditions[] = '(LOWER(TRIM(' . $db->quoteName('first_name') . ')) = :dupFirst'
                . ' AND LOWER(TRIM(' . $db->quoteName('last_name') . ')) = :dupLast'
                . ' AND ' . $db->quoteName('birth_date') . ' = :dupBirth)';
            $query->bind(':dupFirst', $firstName)
                ->bind(':dupLast', $lastName)
                ->bind(':dupBirth', $birthDate);
        }

        if (!$conditions) {
            return;
        }

        $query->where('(' . implode(' OR ', $conditions) . ')');
        if ((int) $db->setQuery($query)->loadResult() > 0) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_XDECAROPEOPLE_WARNING_POSSIBLE_DUPLICATE'), 'warning');
        }
    }

    private function writeHistory(int $personId, ?array $before, string $action): void
    {
        try {
            $after = $this->loadAuditRow($personId);
            if (!$after) {
                return;
            }

            $changed = [];
            if ($before === null) {
                $changed = ['created'];
            } else {
                $ignored = ['modified', 'modified_by'];
                foreach ($after as $field => $value) {
                    if (in_array($field, $ignored, true)) {
                        continue;
                    }
                    if ((string) ($before[$field] ?? '') !== (string) $value) {
                        $changed[] = $field;
                    }
                }
            }

            if ($action === 'update' && !$changed) {
                return;
            }

            $db = $this->getDatabase();
            $record = (object) [
                'person_id' => $personId,
                'action' => $action,
                'changed_fields' => json_encode(array_values($changed), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'actor_user_id' => (int) Factory::getApplication()->getIdentity()->id,
                'created' => Factory::getDate()->toSql(),
            ];
            $db->insertObject('#__xdecaropeople_history', $record);
        } catch (Throwable $exception) {
            Log::add('People history write failed: ' . $exception->getMessage(), Log::WARNING, 'com_xdecaropeople');
        }
    }

    private function loadAuditRow(int $personId): ?array
    {
        if ($personId < 1) {
            return null;
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__xdecaropeople_people'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $personId, ParameterType::INTEGER);

        $row = $db->setQuery($query, 0, 1)->loadAssoc();
        return $row ?: null;
    }

    private function normalizeAddresses(mixed $rows): array
    {
        $result = [];
        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = strtolower(trim((string) ($row['type'] ?? 'other')));
            if (!in_array($type, self::ADDRESS_TYPES, true)) {
                $type = 'other';
            }

            $country = strtoupper(trim((string) ($row['country_code'] ?? '')));
            if ($country !== '' && !CountryMetadata::isAlpha2($country)) {
                $country = '';
            }

            $item = [
                'type' => $type,
                'address_line' => trim((string) ($row['address_line'] ?? '')),
                'address_number' => trim((string) ($row['address_number'] ?? '')),
                'postal_code' => trim((string) ($row['postal_code'] ?? '')),
                'city' => trim((string) ($row['city'] ?? '')),
                'region' => trim((string) ($row['region'] ?? '')),
                'country_code' => $country,
            ];

            if (implode('', array_values(array_diff_key($item, ['type' => true]))) === '') {
                continue;
            }

            $result[] = $item;
        }

        return $result;
    }

    private function normalizeRelations(mixed $rows, mixed $currentUuid): array
    {
        $result = [];
        $currentUuid = strtolower(trim((string) $currentUuid));

        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = strtolower(trim((string) ($row['type'] ?? 'other')));
            $uuid = strtolower(trim((string) ($row['person_uuid'] ?? '')));
            if (!in_array($type, self::RELATION_TYPES, true) || !self::isUuid($uuid) || ($currentUuid !== '' && $uuid === $currentUuid)) {
                continue;
            }

            $result[] = [
                'type' => $type,
                'person_uuid' => $uuid,
                'note' => trim((string) ($row['note'] ?? '')),
            ];
        }

        return $result;
    }

    private function normalizeAllowedList(mixed $value, array $allowed): array
    {
        $result = [];
        foreach ((array) $value as $item) {
            $item = strtolower(trim((string) $item));
            if ($item !== '' && in_array($item, $allowed, true)) {
                $result[$item] = $item;
            }
        }

        return array_values($result);
    }

    private function normalizeUrl(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function decodeJsonList(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function encodeJsonList(array $value): ?string
    {
        if (!$value) {
            return null;
        }

        return json_encode(array_values($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
    }

    private static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
