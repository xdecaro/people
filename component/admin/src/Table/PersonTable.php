<?php
namespace xdecaro\Component\People\Administrator\Table;

defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

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

        foreach ([
            'email',
            'phone',
            'whatsapp',
            'tax_identifier',
            'birth_place',
            'address_line',
            'street_number',
            'postal_code',
            'city',
            'region',
            'notes',
            'instagram',
            'facebook',
            'linkedin',
            'tiktok',
            'telegram',
            'x_twitter',
            'youtube',
            'website',
        ] as $field) {
            if (property_exists($this, $field) && $this->{$field} !== null) {
                $value = trim((string) $this->{$field});
                $this->{$field} = $value !== '' ? $value : null;
            }
        }

        foreach (['nationality_code', 'country_code'] as $field) {
            if (property_exists($this, $field) && $this->{$field} !== null) {
                $value = strtoupper(trim((string) $this->{$field}));
                $this->{$field} = $value !== '' ? $value : null;
            }
        }

        if (property_exists($this, 'birth_date') && trim((string) $this->birth_date) === '') {
            $this->birth_date = null;
        }

        if (property_exists($this, 'gender')) {
            $gender = strtoupper(trim((string) $this->gender));
            $this->gender = in_array($gender, ['M', 'F'], true) ? $gender : null;
        }

        if (property_exists($this, 'has_disability')) {
            $value = $this->has_disability;
            $this->has_disability = ($value === '' || $value === null)
                ? null
                : ((int) $value === 1 ? 1 : 0);
        }

        return parent::check();
    }
}
