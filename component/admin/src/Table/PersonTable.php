<?php

namespace xdecaro\Component\People\Administrator\Table;

defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use xdecaro\Component\People\Administrator\Service\CountryMetadata;

final class PersonTable extends Table
{
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
            'email',
            'phone',
            'whatsapp',
            'tax_identifier',
            'birth_place',
            'address_line',
            'address_number',
            'postal_code',
            'city',
            'region',
            'social_instagram',
            'social_facebook',
            'social_linkedin',
            'social_tiktok',
            'social_telegram',
            'social_x',
            'social_youtube',
            'website_url',
            'notes',
        ] as $field) {
            if (property_exists($this, $field) && $this->{$field} !== null) {
                $value = trim((string) $this->{$field});
                $this->{$field} = $value !== '' ? $value : null;
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

        if (property_exists($this, 'country_code') && $this->country_code !== null) {
            $code = strtoupper(trim((string) $this->country_code));
            if ($code !== '' && !CountryMetadata::isAlpha2($code)) {
                $this->setError('Invalid country code.');
                return false;
            }
            $this->country_code = $code !== '' ? $code : null;
        }

        return parent::check();
    }
}
