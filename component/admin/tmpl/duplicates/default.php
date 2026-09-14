<?php

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$typeLabels = [
    'email' => 'COM_XDECAROPEOPLE_DUPLICATE_TYPE_EMAIL',
    'tax_identifier' => 'COM_XDECAROPEOPLE_DUPLICATE_TYPE_TIN',
    'name_birth' => 'COM_XDECAROPEOPLE_DUPLICATE_TYPE_NAME_BIRTH',
    'name' => 'COM_XDECAROPEOPLE_DUPLICATE_TYPE_NAME',
    'phone' => 'COM_XDECAROPEOPLE_DUPLICATE_TYPE_PHONE',
    'whatsapp' => 'COM_XDECAROPEOPLE_DUPLICATE_TYPE_WHATSAPP',
];
?>
<div class="xdecaro-scope">
    <div class="alert alert-info"><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATES_HELP'); ?></div>

    <?php if (!$this->groups) : ?>
        <div class="alert alert-success"><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_NONE'); ?></div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_STRENGTH'); ?></th>
                        <th><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_TYPE'); ?></th>
                        <th><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_KEY'); ?></th>
                        <th><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_PERSONS'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->groups as $group) : ?>
                        <?php
                        $strength = (string) ($group['strength'] ?? 'possible');
                        $strengthLabel = $strength === 'strong'
                            ? Text::_('COM_XDECAROPEOPLE_DUPLICATE_STRENGTH_STRONG')
                            : Text::_('COM_XDECAROPEOPLE_DUPLICATE_STRENGTH_POSSIBLE');
                        $type = (string) ($group['type'] ?? '');
                        $typeLabel = isset($typeLabels[$type]) ? Text::_($typeLabels[$type]) : $type;
                        ?>
                        <tr>
                            <td>
                                <span class="badge <?php echo $strength === 'strong' ? 'bg-danger' : 'bg-warning text-dark'; ?>">
                                    <?php echo $this->escape($strengthLabel); ?>
                                </span>
                            </td>
                            <td><?php echo $this->escape($typeLabel); ?></td>
                            <td><?php echo $this->escape((string) ($group['value'] ?? $group['key'] ?? '')); ?></td>
                            <td>
                                <div class="xdecaro-duplicate-records">
                                    <?php foreach ((array) ($group['records'] ?? []) as $record) : ?>
                                        <?php
                                        $id = (int) ($record['id'] ?? 0);
                                        $name = trim((string) ($record['display_name'] ?? ''));
                                        if ($id < 1) {
                                            continue;
                                        }
                                        ?>
                                        <a
                                            class="btn btn-sm btn-outline-primary"
                                            href="<?php echo Route::_('index.php?option=com_xdecaropeople&task=person.edit&id=' . $id); ?>"
                                            title="<?php echo $this->escape(Text::_('COM_XDECAROPEOPLE_DUPLICATE_OPEN_PERSON')); ?>"
                                        >
                                            <?php echo $this->escape(($name !== '' ? $name : Text::_('COM_XDECAROPEOPLE_PERSON_EDIT')) . ' (#' . $id . ')'); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
