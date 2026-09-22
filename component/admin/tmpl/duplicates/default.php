<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
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

$fieldLabels = [
    'birth_date' => 'COM_XDECAROPEOPLE_FIELD_BIRTH_DATE',
    'sex' => 'COM_XDECAROPEOPLE_FIELD_SEX',
    'tax_identifier' => 'COM_XDECAROPEOPLE_FIELD_TAX_IDENTIFIER',
    'email' => 'JGLOBAL_EMAIL',
    'phone' => 'COM_XDECAROPEOPLE_FIELD_PHONE',
    'whatsapp' => 'COM_XDECAROPEOPLE_FIELD_WHATSAPP',
    'birth_place' => 'COM_XDECAROPEOPLE_FIELD_BIRTH_PLACE',
    'address' => 'COM_XDECAROPEOPLE_FIELD_ADDRESS',
    'source_component' => 'COM_XDECAROPEOPLE_FIELD_SOURCE_COMPONENT',
    'created' => 'JGLOBAL_CREATED',
];

$renderValue = static function (mixed $value): string {
    $value = trim((string) ($value ?? ''));
    return $value !== '' ? $value : '—';
};

$addressFor = static function (array $record): string {
    $line = trim(
        trim((string) ($record['address_line'] ?? ''))
        . ' '
        . trim((string) ($record['address_number'] ?? ''))
    );

    $place = implode(' ', array_filter([
        trim((string) ($record['postal_code'] ?? '')),
        trim((string) ($record['city'] ?? '')),
        trim((string) ($record['region'] ?? '')),
    ], static fn(string $value): bool => $value !== ''));

    return trim(implode(' · ', array_filter([$line, $place], static fn(string $value): bool => $value !== '')));
};
?>
<div class="xdecaro-scope xdecaro-duplicates">
    <div class="alert alert-info mb-3">
        <strong><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATES_REVIEW_TITLE'); ?></strong>
        <div class="mt-1"><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATES_REVIEW_HELP'); ?></div>
    </div>

    <div class="xdecaro-duplicate-legend mb-3">
        <span class="badge bg-danger"><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_STRENGTH_STRONG'); ?></span>
        <span><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_STRONG_HELP'); ?></span>
        <span class="badge bg-warning text-dark"><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_STRENGTH_POSSIBLE'); ?></span>
        <span><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_POSSIBLE_HELP'); ?></span>
    </div>

    <?php if (!$this->groups) : ?>
        <div class="alert alert-success"><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_NONE'); ?></div>
    <?php else : ?>
        <div class="xdecaro-duplicate-groups">
            <?php foreach ($this->groups as $group) : ?>
                <?php
                $strength = (string) ($group['strength'] ?? 'possible');
                $strengthLabel = $strength === 'strong'
                    ? Text::_('COM_XDECAROPEOPLE_DUPLICATE_STRENGTH_STRONG')
                    : Text::_('COM_XDECAROPEOPLE_DUPLICATE_STRENGTH_POSSIBLE');
                $type = (string) ($group['type'] ?? '');
                $typeLabel = isset($typeLabels[$type]) ? Text::_($typeLabels[$type]) : $type;
                $records = array_values((array) ($group['records'] ?? []));
                $recordIds = array_values(array_filter(array_map(
                    static fn(array $record): int => (int) ($record['id'] ?? 0),
                    $records
                )));
                ?>
                <section class="card xdecaro-duplicate-group">
                    <div class="card-header xdecaro-duplicate-group-header">
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="badge <?php echo $strength === 'strong' ? 'bg-danger' : 'bg-warning text-dark'; ?>">
                                    <?php echo $this->escape($strengthLabel); ?>
                                </span>
                                <strong><?php echo $this->escape($typeLabel); ?></strong>
                            </div>
                            <div class="small text-body-secondary mt-1">
                                <?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_MATCH_REASON'); ?>:
                                <strong><?php echo $this->escape((string) ($group['value'] ?? $group['key'] ?? '')); ?></strong>
                            </div>
                        </div>

                        <span class="badge bg-secondary">
                            <?php echo Text::sprintf('COM_XDECAROPEOPLE_DUPLICATE_RECORD_COUNT', count($records)); ?>
                        </span>
                    </div>

                    <div class="card-body">
                        <div class="xdecaro-duplicate-compare">
                            <?php foreach ($records as $record) : ?>
                                <?php
                                $id = (int) ($record['id'] ?? 0);
                                if ($id < 1) {
                                    continue;
                                }

                                $name = trim((string) ($record['display_name'] ?? ''));
                                $address = $addressFor($record);
                                ?>
                                <article class="xdecaro-duplicate-person">
                                    <div class="xdecaro-duplicate-person-heading">
                                        <div>
                                            <h3 class="h6 mb-1">
                                                <?php echo $this->escape($name !== '' ? $name : Text::_('COM_XDECAROPEOPLE_PERSON_EDIT')); ?>
                                            </h3>
                                            <span class="small text-body-secondary">#<?php echo $id; ?></span>
                                        </div>
                                        <a
                                            class="btn btn-sm btn-outline-primary"
                                            href="<?php echo Route::_('index.php?option=com_xdecaropeople&task=person.edit&id=' . $id); ?>"
                                        >
                                            <?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_EDIT_PERSON'); ?>
                                        </a>
                                    </div>

                                    <dl class="xdecaro-duplicate-fields">
                                        <?php if ($this->canSensitive) : ?>
                                            <div>
                                                <dt><?php echo Text::_($fieldLabels['birth_date']); ?></dt>
                                                <dd><?php echo $this->escape($renderValue($record['birth_date'] ?? '')); ?></dd>
                                            </div>
                                            <div>
                                                <dt><?php echo Text::_($fieldLabels['sex']); ?></dt>
                                                <dd><?php echo $this->escape($renderValue($record['sex'] ?? '')); ?></dd>
                                            </div>
                                            <div>
                                                <dt><?php echo Text::_($fieldLabels['tax_identifier']); ?></dt>
                                                <dd><?php echo $this->escape($renderValue($record['tax_identifier'] ?? '')); ?></dd>
                                            </div>
                                        <?php endif; ?>

                                        <div>
                                            <dt><?php echo Text::_($fieldLabels['email']); ?></dt>
                                            <dd><?php echo $this->escape($renderValue($record['email'] ?? '')); ?></dd>
                                        </div>
                                        <div>
                                            <dt><?php echo Text::_($fieldLabels['phone']); ?></dt>
                                            <dd><?php echo $this->escape($renderValue($record['phone'] ?? '')); ?></dd>
                                        </div>
                                        <div>
                                            <dt><?php echo Text::_($fieldLabels['whatsapp']); ?></dt>
                                            <dd><?php echo $this->escape($renderValue($record['whatsapp'] ?? '')); ?></dd>
                                        </div>

                                        <?php if ($this->canSensitive) : ?>
                                            <div>
                                                <dt><?php echo Text::_($fieldLabels['birth_place']); ?></dt>
                                                <dd><?php echo $this->escape($renderValue($record['birth_place'] ?? '')); ?></dd>
                                            </div>
                                            <div>
                                                <dt><?php echo Text::_($fieldLabels['address']); ?></dt>
                                                <dd><?php echo $this->escape($renderValue($address)); ?></dd>
                                            </div>
                                        <?php endif; ?>

                                        <div>
                                            <dt><?php echo Text::_($fieldLabels['source_component']); ?></dt>
                                            <dd><?php echo $this->escape($renderValue($record['source_component'] ?? '')); ?></dd>
                                        </div>
                                        <div>
                                            <dt><?php echo Text::_($fieldLabels['created']); ?></dt>
                                            <dd><?php echo $this->escape($renderValue($record['created'] ?? '')); ?></dd>
                                        </div>
                                    </dl>

                                    <?php if ($strength === 'strong' && $this->canMerge && count($records) >= 2) : ?>
                                        <form action="<?php echo Route::_('index.php?option=com_xdecaropeople&task=duplicate.merge'); ?>" method="post" class="mt-3">
                                            <input type="hidden" name="target_id" value="<?php echo $id; ?>">
                                            <input type="hidden" name="match_type" value="<?php echo $this->escape($type); ?>">
                                            <input type="hidden" name="match_key" value="<?php echo $this->escape((string) ($group['key'] ?? '')); ?>">
                                            <?php foreach ($recordIds as $recordId) : ?>
                                                <input type="hidden" name="record_ids[]" value="<?php echo (int) $recordId; ?>">
                                            <?php endforeach; ?>
                                            <?php echo HTMLHelper::_('form.token'); ?>
                                            <button type="submit" class="btn btn-sm btn-success w-100">
                                                <?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_KEEP_AND_MERGE'); ?>
                                            </button>
                                            <div class="form-text">
                                                <?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_MERGE_HINT'); ?>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <div class="xdecaro-duplicate-actions mt-3">
                            <?php if ($strength !== 'strong') : ?>
                                <div class="small text-body-secondary">
                                    <?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_POSSIBLE_ACTION_HINT'); ?>
                                </div>
                            <?php endif; ?>

                            <form action="<?php echo Route::_('index.php?option=com_xdecaropeople&task=duplicate.dismiss'); ?>" method="post">
                                <input type="hidden" name="match_type" value="<?php echo $this->escape($type); ?>">
                                <input type="hidden" name="match_key" value="<?php echo $this->escape((string) ($group['key'] ?? '')); ?>">
                                <?php foreach ($recordIds as $recordId) : ?>
                                    <input type="hidden" name="record_ids[]" value="<?php echo (int) $recordId; ?>">
                                <?php endforeach; ?>
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    <?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_NOT_DUPLICATE'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
