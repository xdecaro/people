<?php

namespace xdecaro\Component\People\Administrator\Table;

defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use xdecaro\Component\People\Administrator\Service\CountryMetadata;

final class PersonTable extends Table
{
    private const PERSON_STATUSES = ['active', 'archived', 'deceased'];
    private const CONTACT_METHODS = ['email', 'phone', 'whatsapp', 'other'];

    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__xdecaropeople_people', 'id', $db);
    }

    public function check(): bool
    {
        $this->first_name = trim((string) $this->first_name);
        $this->last_name = trim((string) $this->last_name);
        $this->display_name = trim((string) $this->display_name);

        if ($this->first_name === '' || $this->last_name === '') {
            $this->setError('First name and last name are required.');
            return false;
        }

        if ($this->display_name === '') {
            $this->display_name = trim($this->first_name . ' ' . $this->last_name);
        }

        if (property_exists($this, 'birth_date') && trim((string) ($this->birth_date ?? '')) === '') {
            $this->birth_date = null;
        }

        if (property_exists($this, 'sex')) {
            $sex = strtoupper(trim((string) ($this->sex ?? '')));
            if ($sex !== '' && !in_array($sex, ['M', 'F'], true)) {
                $this->setError('Invalid sex value.');
                return false;
            }
            $this->sex = $sex !== '' ? $sex : null;
        }

        if (property_exists($this, 'disability_status')) {
            if ($this->disability_status === '' || $this->disability_status === null) {
                $this->disability_status = null;
            } else {
                $value = (int) $this->disability_status;
                if (!in_array($value, [0, 1], true)) {
                    $this->setError('Invalid disability value.');
                    return false;
                }
                $this->disability_status = $value;
            }
        }

        foreach ([
            'preferred_name',
            'email',
            'phone',
            'whatsapp',
            'preferred_contact',
            'tax_identifier',
            'birth_place',
            'birth_place_id',
            'birth_region',
            'disability_other',
            'accessibility_other',
            'address_line',
            'address_number',
            'postal_code',
            'city',
            'region',
            'residence_place_id',
            'social_instagram',
            'social_facebook',
            'social_linkedin',
            'social_tiktok',
            'social_telegram',
            'social_x',
            'social_youtube',
            'website_url',
            'profile_document_uuid',
            'source_component',
            'notes',
        ] as $field) {
            if (property_exists($this, $field) && $this->{$field} !== null) {
                $value = trim((string) $this->{$field});
                $this->{$field} = $value !== '' ? $value : null;
            }
        }

        foreach (['disability_types', 'accessibility_needs', 'nationality_codes', 'additional_addresses', 'relations_data'] as $field) {
            if (!property_exists($this, $field) || $this->{$field} === null || $this->{$field} === '') {
                continue;
            }

            $decoded = json_decode((string) $this->{$field}, true);
            if (!is_array($decoded)) {
                $this->setError('Invalid structured People field: ' . $field . '.');
                return false;
            }
        }

        if (property_exists($this, 'nationality_code') && $this->nationality_code !== null) {
            $code = strtoupper(trim((string) $this->nationality_code));
            if ($code !== '' && !CountryMetadata::isAlpha3($code)) {
                $this->setError('Invalid nationality code.');
                return false;
            }
            $this->nationality_code = $code !== '' ? $code : null;
        }

        foreach (['birth_country_code', 'country_code'] as $field) {
            if (!property_exists($this, $field) || $this->{$field} === null) {
                continue;
            }

            $code = strtoupper(trim((string) $this->{$field}));
            if ($code !== '' && !CountryMetadata::isAlpha2($code)) {
                $this->setError('Invalid country code.');
                return false;
            }
            $this->{$field} = $code !== '' ? $code : null;
        }

        if (property_exists($this, 'preferred_contact') && $this->preferred_contact !== null) {
            $method = strtolower((string) $this->preferred_contact);
            if (!in_array($method, self::CONTACT_METHODS, true)) {
                $this->setError('Invalid preferred contact method.');
                return false;
            }
            $this->preferred_contact = $method;
        }

        if (property_exists($this, 'person_status')) {
            $status = strtolower(trim((string) ($this->person_status ?? 'active')));
            if (!in_array($status, self::PERSON_STATUSES, true)) {
                $this->setError('Invalid person status.');
                return false;
            }
            $this->person_status = $status;
        }

        if (property_exists($this, 'profile_document_uuid') && $this->profile_document_uuid !== null) {
            $uuid = strtolower((string) $this->profile_document_uuid);
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
                $this->setError('Invalid profile document UUID.');
                return false;
            }
            $this->profile_document_uuid = $uuid;
        }

        return parent::check();
    }
}
