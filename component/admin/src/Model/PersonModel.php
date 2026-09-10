<?php
namespace xdecaro\Component\People\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;

final class PersonModel extends AdminModel
{
    protected $text_prefix = 'COM_XDECAROPEOPLE';

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

        if (
            !$user->authorise('people.view_sensitive', 'com_xdecaropeople')
            && !$user->authorise('core.admin', 'com_xdecaropeople')
        ) {
            foreach ([
                'birth_date',
                'gender',
                'has_disability',
                'birth_place',
                'nationality_code',
                'tax_identifier',
                'address_line',
                'street_number',
                'postal_code',
                'city',
                'region',
                'country_code',
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

        return $data ?: $this->getItem();
    }

    protected function prepareTable($table): void
    {
        $now = Factory::getDate()->toSql();
        $userId = (int) Factory::getApplication()->getIdentity()->id;

        if (empty($table->uuid)) {
            $table->uuid = self::uuidV4();
        }

        if (trim((string) $table->display_name) === '') {
            $table->display_name = trim((string) $table->first_name . ' ' . (string) $table->last_name);
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
        $data['first_name'] = trim((string) ($data['first_name'] ?? ''));
        $data['last_name'] = trim((string) ($data['last_name'] ?? ''));
        $data['display_name'] = trim((string) ($data['display_name'] ?? ''));
        $data['user_id'] = !empty($data['user_id']) ? (int) $data['user_id'] : null;

        $birthDate = trim((string) ($data['birth_date'] ?? ''));
        $data['birth_date'] = $birthDate !== '' ? $birthDate : null;

        $gender = strtoupper(trim((string) ($data['gender'] ?? '')));
        $data['gender'] = in_array($gender, ['M', 'F'], true) ? $gender : null;

        $disability = $data['has_disability'] ?? null;
        $data['has_disability'] = ($disability === '' || $disability === null)
            ? null
            : ((int) $disability === 1 ? 1 : 0);

        if ($data['display_name'] === '') {
            $data['display_name'] = trim($data['first_name'] . ' ' . $data['last_name']);
        }

        if (!$this->validateUniqueUser($data)) {
            return false;
        }

        return parent::save($data);
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
            $this->setError('This Joomla User is already linked to another person.');

            return false;
        }

        return true;
    }

    private static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
