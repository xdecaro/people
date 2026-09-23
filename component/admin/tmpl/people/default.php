<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.multiselect');

$filterState = (string) $this->state->get('filter.state');
$currentLimit = (int) $this->state->get('list.limit', 20);
$limitChoices = [20, 50, 100, 200, 500];
?>
<form action="<?php echo Route::_('index.php?option=com_xdecaropeople&view=people'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="xdecaro-scope">
        <div class="xdecaro-people-filters mb-3">
            <div class="xdecaro-people-filter-search">
                <input
                    type="search"
                    name="filter_search"
                    class="form-control"
                    value="<?php echo $this->escape((string) $this->state->get('filter.search')); ?>"
                    placeholder="<?php echo Text::_('JSEARCH_FILTER'); ?>"
                >
            </div>
            <div class="xdecaro-people-filter-state">
                <select name="filter_state" class="form-select" onchange="this.form.submit()">
                    <option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?></option>
                    <option value="1" <?php echo $filterState === '1' ? 'selected' : ''; ?>><?php echo Text::_('JPUBLISHED'); ?></option>
                    <option value="0" <?php echo $filterState === '0' ? 'selected' : ''; ?>><?php echo Text::_('JUNPUBLISHED'); ?></option>
                    <option value="-2" <?php echo $filterState === '-2' ? 'selected' : ''; ?>><?php echo Text::_('COM_XDECAROPEOPLE_FILTER_TRASHED'); ?></option>
                </select>
            </div>
            <div class="xdecaro-people-filter-submit">
                <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th><input type="checkbox" name="checkall-toggle" onclick="Joomla.checkAll(this)"></th>
                        <th><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_DISPLAY_NAME'); ?></th>
                        <?php if ($this->canIdentityDetails) : ?>
                            <th><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_BIRTH_DATE'); ?></th>
                            <th><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_BIRTH_PLACE'); ?></th>
                        <?php endif; ?>
                        <th><?php echo Text::_('JGLOBAL_EMAIL'); ?></th>
                        <th><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_PHONE'); ?></th>
                        <th><?php echo Text::_('JSTATUS'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->items as $i => $item) : ?>
                        <?php
                        $state = (int) $item->state;
                        $stateLabel = match ($state) {
                            1 => Text::_('JPUBLISHED'),
                            -2 => Text::_('COM_XDECAROPEOPLE_FILTER_TRASHED'),
                            default => Text::_('JUNPUBLISHED'),
                        };
                        ?>
                        <tr>
                            <td><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id); ?></td>
                            <td>
                                <a href="<?php echo Route::_('index.php?option=com_xdecaropeople&task=person.edit&id=' . (int) $item->id); ?>">
                                    <?php echo $this->escape($item->display_name); ?>
                                </a>
                            </td>
                            <?php if ($this->canIdentityDetails) : ?>
                                <td><?php echo !empty($item->birth_date) ? $this->escape(HTMLHelper::_('date', $item->birth_date, Text::_('DATE_FORMAT_FILTER_DATE'))) : '—'; ?></td>
                                <td><?php echo $this->escape((string) ($item->birth_place ?? '')); ?></td>
                            <?php endif; ?>
                            <td><?php echo $this->escape((string) $item->email); ?></td>
                            <td><?php echo $this->escape((string) $item->phone); ?></td>
                            <td><?php echo $stateLabel; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="xdecaro-people-pagination-footer mt-3">
            <div class="xdecaro-people-page-size d-flex flex-wrap align-items-center gap-2">
                <label for="xdecaro-people-page-size" class="form-label mb-0">
                    <?php echo Text::_('COM_XDECAROPEOPLE_PAGINATION_SHOW'); ?>
                </label>
                <select
                    id="xdecaro-people-page-size"
                    name="list[limit]"
                    class="form-select form-select-sm"
                    onchange="this.form.submit()"
                >
                    <?php foreach ($limitChoices as $limitChoice) : ?>
                        <option value="<?php echo (int) $limitChoice; ?>" <?php echo $currentLimit === $limitChoice ? 'selected' : ''; ?>>
                            <?php echo (int) $limitChoice; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="text-body-secondary"><?php echo Text::_('COM_XDECAROPEOPLE_PAGINATION_PER_PAGE'); ?></span>
            </div>

            <div class="xdecaro-people-pagination-links">
                <?php echo $this->pagination->getListFooter(); ?>
            </div>
        </div>
    </div>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<dialog
    id="xdecaro-people-export-dialog"
    class="xdecaro-people-export-dialog border-0 rounded-3 shadow-lg p-0"
    data-no-selection="<?php echo $this->escape(Text::_('COM_XDECAROPEOPLE_EXPORT_NO_SELECTION')); ?>"
    data-no-columns="<?php echo $this->escape(Text::_('COM_XDECAROPEOPLE_EXPORT_NO_COLUMNS')); ?>"
>
    <div class="card border-0">
        <div class="card-header d-flex align-items-center justify-content-between gap-3">
            <strong><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_TITLE'); ?></strong>
            <button type="button" class="btn-close" aria-label="<?php echo Text::_('JCLOSE'); ?>" data-xdecaro-export-close></button>
        </div>
        <div class="card-body">
            <div class="mb-4">
                <label class="form-label fw-semibold" for="xdecaro-people-export-format-ui">
                    <?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_FORMAT'); ?>
                </label>
                <select id="xdecaro-people-export-format-ui" class="form-select">
                    <option value="xlsx"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_FORMAT_XLSX'); ?></option>
                    <option value="csv"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_FORMAT_CSV'); ?></option>
                    <option value="pdf"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_FORMAT_PDF'); ?></option>
                </select>
            </div>

            <fieldset class="mb-4">
                <legend class="fs-6 fw-semibold mb-3"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_SCOPE'); ?></legend>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="xdecaro_export_scope_ui" id="xdecaro-people-export-scope-filtered" value="filtered" checked>
                    <label class="form-check-label" for="xdecaro-people-export-scope-filtered">
                        <?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_SCOPE_FILTERED'); ?>
                    </label>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="xdecaro_export_scope_ui" id="xdecaro-people-export-scope-all" value="all">
                    <label class="form-check-label" for="xdecaro-people-export-scope-all">
                        <?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_SCOPE_ALL'); ?>
                    </label>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="radio" name="xdecaro_export_scope_ui" id="xdecaro-people-export-scope-selected" value="selected">
                    <label class="form-check-label" for="xdecaro-people-export-scope-selected">
                        <?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_SCOPE_SELECTED'); ?>
                        (<span id="xdecaro-people-export-selected-count">0</span>)
                    </label>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <small class="text-body-secondary">
                        <?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_SELECTION_PERSIST_HELP'); ?>
                    </small>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="xdecaro-people-export-clear-selection">
                        <?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_CLEAR_SELECTION'); ?>
                    </button>
                </div>
            </fieldset>

            <fieldset>
                <legend class="fs-6 fw-semibold mb-2">
                    <?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_COLUMNS'); ?>
                    (<span id="xdecaro-people-export-column-count">0</span>)
                </legend>
                <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
                    <div class="btn-group btn-group-sm" role="group" aria-label="<?php echo $this->escape(Text::_('COM_XDECAROPEOPLE_EXPORT_COLUMNS')); ?>">
                        <button type="button" class="btn btn-outline-secondary" id="xdecaro-people-export-columns-visible">
                            <?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_COLUMNS_VISIBLE'); ?>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="xdecaro-people-export-columns-all">
                            <?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_COLUMNS_ALL'); ?>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="xdecaro-people-export-columns-none">
                            <?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_COLUMNS_NONE'); ?>
                        </button>
                    </div>
                </div>

                <div class="row row-cols-1 row-cols-md-2 g-2 xdecaro-people-export-columns">
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="display_name" id="xdecaro-export-column-display-name" data-xdecaro-export-column data-visible-column checked>
                            <label class="form-check-label" for="xdecaro-export-column-display-name"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_DISPLAY_NAME'); ?></label>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="first_name" id="xdecaro-export-column-first-name" data-xdecaro-export-column>
                            <label class="form-check-label" for="xdecaro-export-column-first-name"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_FIRST_NAME'); ?></label>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="last_name" id="xdecaro-export-column-last-name" data-xdecaro-export-column>
                            <label class="form-check-label" for="xdecaro-export-column-last-name"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_LAST_NAME'); ?></label>
                        </div>
                    </div>

                    <?php if ($this->canSensitive) : ?>
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="tax_identifier" id="xdecaro-export-column-tax-identifier" data-xdecaro-export-column>
                                <label class="form-check-label" for="xdecaro-export-column-tax-identifier"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_COLUMN_TAX_IDENTIFIER'); ?></label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($this->canIdentityDetails) : ?>
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="birth_date" id="xdecaro-export-column-birth-date" data-xdecaro-export-column data-visible-column checked>
                                <label class="form-check-label" for="xdecaro-export-column-birth-date"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_BIRTH_DATE'); ?></label>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="birth_place" id="xdecaro-export-column-birth-place" data-xdecaro-export-column data-visible-column checked>
                                <label class="form-check-label" for="xdecaro-export-column-birth-place"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_BIRTH_PLACE'); ?></label>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="birth_region" id="xdecaro-export-column-birth-region" data-xdecaro-export-column>
                                <label class="form-check-label" for="xdecaro-export-column-birth-region"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_BIRTH_REGION'); ?></label>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="sex" id="xdecaro-export-column-sex" data-xdecaro-export-column>
                                <label class="form-check-label" for="xdecaro-export-column-sex"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_SEX'); ?></label>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="address_line" id="xdecaro-export-column-address" data-xdecaro-export-column>
                                <label class="form-check-label" for="xdecaro-export-column-address"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_ADDRESS'); ?></label>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="address_number" id="xdecaro-export-column-address-number" data-xdecaro-export-column>
                                <label class="form-check-label" for="xdecaro-export-column-address-number"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_ADDRESS_NUMBER'); ?></label>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="postal_code" id="xdecaro-export-column-postal-code" data-xdecaro-export-column>
                                <label class="form-check-label" for="xdecaro-export-column-postal-code"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_POSTAL_CODE'); ?></label>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="city" id="xdecaro-export-column-city" data-xdecaro-export-column>
                                <label class="form-check-label" for="xdecaro-export-column-city"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_CITY'); ?></label>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="region" id="xdecaro-export-column-region" data-xdecaro-export-column>
                                <label class="form-check-label" for="xdecaro-export-column-region"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_REGION'); ?></label>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="country_code" id="xdecaro-export-column-country" data-xdecaro-export-column>
                                <label class="form-check-label" for="xdecaro-export-column-country"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_COUNTRY'); ?></label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="email" id="xdecaro-export-column-email" data-xdecaro-export-column data-visible-column checked>
                            <label class="form-check-label" for="xdecaro-export-column-email"><?php echo Text::_('JGLOBAL_EMAIL'); ?></label>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="phone" id="xdecaro-export-column-phone" data-xdecaro-export-column data-visible-column checked>
                            <label class="form-check-label" for="xdecaro-export-column-phone"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_PHONE'); ?></label>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="state" id="xdecaro-export-column-state" data-xdecaro-export-column data-visible-column checked>
                            <label class="form-check-label" for="xdecaro-export-column-state"><?php echo Text::_('JSTATUS'); ?></label>
                        </div>
                    </div>
                </div>
            </fieldset>

            <p class="text-body-secondary small mt-3 mb-0">
                <?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_PRIVACY_NOTICE_178'); ?>
            </p>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-secondary" data-xdecaro-export-close>
                <?php echo Text::_('JCANCEL'); ?>
            </button>
            <button type="button" class="btn btn-primary" id="xdecaro-people-export-download">
                <?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_DOWNLOAD'); ?>
            </button>
        </div>
    </div>
</dialog>

<form
    action="<?php echo Route::_('index.php?option=com_xdecaropeople'); ?>"
    method="post"
    id="xdecaro-people-export-form"
    class="d-none"
>
    <input type="hidden" name="task" value="export.download">
    <input type="hidden" name="export_format" value="xlsx">
    <input type="hidden" name="export_scope" value="filtered">
    <input type="hidden" name="export_selected_ids" value="">
    <input type="hidden" name="filter_search" value="">
    <input type="hidden" name="filter_state" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
