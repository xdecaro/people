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
    'user_id' => 'COM_XDECAROPEOPLE_DUPLICATE_JOOMLA_USER',
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

$fieldValue = static function (array $record, string $field) use ($addressFor): string {
    if ($field === 'address') {
        return $addressFor($record);
    }

    if ($field === 'user_id') {
        $userId = (int) ($record['user_id'] ?? 0);
        return $userId > 0 ? (string) $userId : '';
    }

    return trim((string) ($record[$field] ?? ''));
};

$normalizeCompare = static function (string $value): string {
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    return mb_strtolower($value, 'UTF-8');
};

$statusBadge = static function (string $status): array {
    return match ($status) {
        'same' => ['COM_XDECAROPEOPLE_DUPLICATE_FIELD_SAME', 'bg-success-subtle text-success-emphasis'],
        'different' => ['COM_XDECAROPEOPLE_DUPLICATE_FIELD_DIFFERENT', 'bg-danger-subtle text-danger-emphasis'],
        'missing' => ['COM_XDECAROPEOPLE_DUPLICATE_FIELD_MISSING', 'bg-warning-subtle text-warning-emphasis'],
        default => ['', ''],
    };
};
?>
<div class="xdecaro-scope xdecaro-duplicates">
    <div class="alert alert-info mb-3">
        <strong><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATES_REVIEW_TITLE'); ?></strong>
        <div class="mt-1"><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATES_REVIEW_HELP'); ?></div>
    </div>

    <div class="xdecaro-duplicate-legend mb-3">
        <span class="badge bg-primary"><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_STRENGTH_STRONG'); ?></span>
        <span><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_STRONG_HELP'); ?></span>
        <span class="badge bg-warning text-dark"><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_STRENGTH_POSSIBLE'); ?></span>
        <span><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_POSSIBLE_HELP'); ?></span>
        <span class="badge bg-danger"><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_STRENGTH_CONFLICT'); ?></span>
        <span><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_CONFLICT_HELP'); ?></span>
    </div>

    <?php if (!$this->groups) : ?>
        <div class="alert alert-success"><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_NONE'); ?></div>
    <?php else : ?>
        <div class="xdecaro-duplicate-groups">
            <?php foreach ($this->groups as $group) : ?>
                <?php
                $strength = (string) ($group['strength'] ?? 'possible');
                $strengthLabel = match ($strength) {
                    'strong' => Text::_('COM_XDECAROPEOPLE_DUPLICATE_STRENGTH_STRONG'),
                    'conflict' => Text::_('COM_XDECAROPEOPLE_DUPLICATE_STRENGTH_CONFLICT'),
                    default => Text::_('COM_XDECAROPEOPLE_DUPLICATE_STRENGTH_POSSIBLE'),
                };
                $strengthClass = match ($strength) {
                    'strong' => 'bg-primary',
                    'conflict' => 'bg-danger',
                    default => 'bg-warning text-dark',
                };

                $type = (string) ($group['type'] ?? '');
                $typeLabel = isset($typeLabels[$type]) ? Text::_($typeLabels[$type]) : $type;
                $records = array_values((array) ($group['records'] ?? []));
                $recordIds = array_values(array_filter(array_map(
                    static fn(array $record): int => (int) ($record['id'] ?? 0),
                    $records
                )));

                $comparisonFields = [
                    'birth_date',
                    'sex',
                    'tax_identifier',
                    'user_id',
                    'email',
                    'phone',
                    'whatsapp',
                    'birth_place',
                    'address',
                ];

                if (!$this->canSensitive) {
                    $comparisonFields = ['user_id', 'email', 'phone', 'whatsapp'];
                }

                $fieldStatuses = [];
                $statusCounts = ['same' => 0, 'different' => 0, 'missing' => 0];
                $differentLabels = [];

                foreach ($comparisonFields as $field) {
                    $values = array_map(
                        static fn(array $record): string => $fieldValue($record, $field),
                        $records
                    );
                    $nonEmpty = array_values(array_filter(
                        $values,
                        static fn(string $value): bool => trim($value) !== ''
                    ));

                    if ($nonEmpty === []) {
                        $fieldStatuses[$field] = 'empty';
                        continue;
                    }

                    $unique = [];
                    foreach ($nonEmpty as $value) {
                        $unique[$normalizeCompare($value)] = true;
                    }

                    if (count($unique) > 1) {
                        $fieldStatuses[$field] = 'different';
                        $statusCounts['different']++;
                        $differentLabels[] = Text::_($fieldLabels[$field]);
                    } elseif (count($nonEmpty) < count($records)) {
                        $fieldStatuses[$field] = 'missing';
                        $statusCounts['missing']++;
                    } else {
                        $fieldStatuses[$field] = 'same';
                        $statusCounts['same']++;
                    }
                }
                ?>
                <details class="card xdecaro-duplicate-group" name="xdecaro-duplicate-review">
                    <summary class="card-header xdecaro-duplicate-accordion-summary">
                        <div class="xdecaro-duplicate-summary-main">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="badge <?php echo $strengthClass; ?>">
                                    <?php echo $this->escape($strengthLabel); ?>
                                </span>
                                <strong><?php echo $this->escape($typeLabel); ?></strong>
                                <span class="badge bg-secondary">
                                    <?php echo Text::sprintf('COM_XDECAROPEOPLE_DUPLICATE_RECORD_COUNT', count($records)); ?>
                                </span>
                            </div>

                            <div class="small text-body-secondary mt-1">
                                <?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_MATCH_REASON'); ?>:
                                <strong><?php echo $this->escape((string) ($group['value'] ?? $group['key'] ?? '')); ?></strong>
                            </div>

                            <?php if ($differentLabels !== []) : ?>
                                <div class="small mt-1 xdecaro-duplicate-difference-summary">
                                    <strong><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_DIFFERENT_FIELDS'); ?>:</strong>
                                    <?php echo $this->escape(implode(', ', $differentLabels)); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="xdecaro-duplicate-summary-counts" aria-label="<?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_COMPARISON_SUMMARY'); ?>">
                            <span class="badge bg-success-subtle text-success-emphasis">
                                <?php echo Text::sprintf('COM_XDECAROPEOPLE_DUPLICATE_COUNT_SAME', $statusCounts['same']); ?>
                            </span>
                            <span class="badge bg-danger-subtle text-danger-emphasis">
                                <?php echo Text::sprintf('COM_XDECAROPEOPLE_DUPLICATE_COUNT_DIFFERENT', $statusCounts['different']); ?>
                            </span>
                            <span class="badge bg-warning-subtle text-warning-emphasis">
                                <?php echo Text::sprintf('COM_XDECAROPEOPLE_DUPLICATE_COUNT_MISSING', $statusCounts['missing']); ?>
                            </span>
                            <span class="xdecaro-duplicate-chevron" aria-hidden="true"></span>
                        </div>
                    </summary>

                    <div class="card-body">
                        <?php if ($strength === 'conflict') : ?>
                            <div class="alert alert-danger">
                                <strong><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_CONFLICT_TITLE'); ?></strong>
                                <div><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_CONFLICT_BLOCKED'); ?></div>
                            </div>
                        <?php endif; ?>

                        <div class="xdecaro-duplicate-compare">
                            <?php foreach ($records as $record) : ?>
                                <?php
                                $id = (int) ($record['id'] ?? 0);
                                if ($id < 1) {
                                    continue;
                                }

                                $name = trim((string) ($record['display_name'] ?? ''));
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
                                        <?php foreach ($comparisonFields as $field) : ?>
                                            <?php
                                            $status = $fieldStatuses[$field] ?? 'empty';
                                            [$statusKey, $statusClass] = $statusBadge($status);
                                            $value = $fieldValue($record, $field);
                                            ?>
                                            <div class="xdecaro-duplicate-field xdecaro-duplicate-field--<?php echo $this->escape($status); ?>">
                                                <dt>
                                                    <span><?php echo Text::_($fieldLabels[$field]); ?></span>
                                                    <?php if ($statusKey !== '') : ?>
                                                        <span class="badge <?php echo $statusClass; ?>">
                                                            <?php echo Text::_($statusKey); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </dt>
                                                <dd>
                                                    <?php if ($field === 'user_id') : ?>
                                                        <?php if ((int) ($record['user_id'] ?? 0) > 0) : ?>
                                                            <span class="badge bg-info text-dark">
                                                                <?php echo Text::sprintf('COM_XDECAROPEOPLE_DUPLICATE_JOOMLA_USER_LINKED', (int) $record['user_id']); ?>
                                                            </span>
                                                        <?php else : ?>
                                                            <?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_JOOMLA_USER_NONE'); ?>
                                                        <?php endif; ?>
                                                    <?php else : ?>
                                                        <?php echo $this->escape($renderValue($value)); ?>
                                                    <?php endif; ?>
                                                </dd>
                                            </div>
                                        <?php endforeach; ?>

                                        <div class="xdecaro-duplicate-field xdecaro-duplicate-field--meta">
                                            <dt><?php echo Text::_($fieldLabels['source_component']); ?></dt>
                                            <dd><?php echo $this->escape($renderValue($record['source_component'] ?? '')); ?></dd>
                                        </div>
                                        <div class="xdecaro-duplicate-field xdecaro-duplicate-field--meta">
                                            <dt><?php echo Text::_($fieldLabels['created']); ?></dt>
                                            <dd><?php echo $this->escape($renderValue($record['created'] ?? '')); ?></dd>
                                        </div>
                                    </dl>

                                    <?php if (($group['merge_allowed'] ?? false) && $this->canMerge && count($records) >= 2) : ?>
                                        <form action="<?php echo Route::_('index.php?option=com_xdecaropeople&task=duplicate.merge'); ?>" method="post" class="mt-3">
                                            <input type="hidden" name="target_id" value="<?php echo $id; ?>">
                                            <input type="hidden" name="match_type" value="<?php echo $this->escape($type); ?>">
                                            <input type="hidden" name="match_key" value="<?php echo $this->escape((string) ($group['key'] ?? '')); ?>">
                                            <?php foreach ($recordIds as $recordId) : ?>
                                                <input type="hidden" name="record_ids[]" value="<?php echo (int) $recordId; ?>">
                                            <?php endforeach; ?>
                                            <?php echo HTMLHelper::_('form.token'); ?>
                                            <details class="xdecaro-duplicate-merge-confirm">
                                                <summary class="btn btn-sm btn-success w-100">
                                                    <?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_KEEP_AND_MERGE'); ?>
                                                </summary>
                                                <div class="alert alert-warning mt-2 mb-2">
                                                    <?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_MERGE_CONFIRM'); ?>
                                                </div>
                                                <button type="submit" class="btn btn-sm btn-danger w-100">
                                                    <?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_MERGE_CONFIRM_BUTTON'); ?>
                                                </button>
                                            </details>
                                            <div class="form-text">
                                                <?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_MERGE_HINT'); ?>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <div class="xdecaro-duplicate-actions mt-3">
                            <div class="small text-body-secondary">
                                <?php
                                echo $strength === 'conflict'
                                    ? Text::_('COM_XDECAROPEOPLE_DUPLICATE_CONFLICT_ACTION_HINT')
                                    : Text::_('COM_XDECAROPEOPLE_DUPLICATE_POSSIBLE_ACTION_HINT');
                                ?>
                            </div>

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
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
