<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.multiselect');

$filterState = (string) $this->state->get('filter.state');
$filterPersonStatus = (string) $this->state->get('filter.person_status');
$filtersOpen = $filterState !== '' || $filterPersonStatus !== '';
$currentLimit = (int) $this->state->get('list.limit', 20);
$limitChoices = [20, 50, 100, 200, 500, 1000, 999999];
$totalItems = (int) ($this->pagination->total ?? 0);
$peopleUrl = Route::_('index.php?option=com_xdecaropeople&view=people');
$allPeopleUrl = Route::_('index.php?option=com_xdecaropeople&view=people&filter_search=&filter_person_status=&filter_state=');
$publishedPeopleUrl = Route::_('index.php?option=com_xdecaropeople&view=people&filter_search=&filter_person_status=&filter_state=1');
$suspendedPeopleUrl = Route::_('index.php?option=com_xdecaropeople&view=people&filter_search=&filter_person_status=&filter_state=0');
$trashedPeopleUrl = Route::_('index.php?option=com_xdecaropeople&view=people&filter_search=&filter_person_status=&filter_state=-2');
$duplicatesUrl = Route::_('index.php?option=com_xdecaropeople&view=duplicates');
?>
<form action="<?php echo $peopleUrl; ?>" method="post" name="adminForm" id="adminForm">
    <div class="xdecaro-scope xdecaro-suite">
        <header class="xdecaro-suite__page-header">
            <div class="xdecaro-suite__eyebrow"><?php echo Text::_('COM_XDECAROPEOPLE'); ?></div>
            <h1 class="xdecaro-suite__title"><?php echo Text::_('COM_XDECAROPEOPLE_PEOPLE'); ?></h1>
            <p class="xdecaro-suite__description"><?php echo Text::_('COM_XDECAROPEOPLE_XML_DESCRIPTION'); ?></p>
        </header>

        <div class="xdecaro-suite__metrics">
            <a class="card xdecaro-suite__metric xdecaro-people-kpi-link is-primary<?php echo $filterState === '' ? ' is-active' : ''; ?> text-decoration-none" href="<?php echo $allPeopleUrl; ?>"<?php echo $filterState === '' ? ' aria-current="page"' : ''; ?>>
                <div class="card-body">
                    <div class="small text-body-secondary mb-1"><?php echo Text::_('COM_XDECAROPEOPLE_KPI_TOTAL'); ?></div>
                    <strong class="fs-3"><?php echo (int) ($this->statusSummary['total'] ?? 0); ?></strong>
                </div>
            </a>
            <a class="card xdecaro-suite__metric xdecaro-people-kpi-link is-success<?php echo $filterState === '1' ? ' is-active' : ''; ?> text-decoration-none" href="<?php echo $publishedPeopleUrl; ?>"<?php echo $filterState === '1' ? ' aria-current="page"' : ''; ?>>
                <div class="card-body">
                    <div class="small text-body-secondary mb-1"><?php echo Text::_('COM_XDECAROPEOPLE_KPI_PUBLISHED'); ?></div>
                    <strong class="fs-3"><?php echo (int) ($this->statusSummary['published'] ?? 0); ?></strong>
                </div>
            </a>
            <a class="card xdecaro-suite__metric xdecaro-people-kpi-link is-neutral<?php echo $filterState === '0' ? ' is-active' : ''; ?> text-decoration-none" href="<?php echo $suspendedPeopleUrl; ?>"<?php echo $filterState === '0' ? ' aria-current="page"' : ''; ?>>
                <div class="card-body">
                    <div class="small text-body-secondary mb-1"><?php echo Text::_('COM_XDECAROPEOPLE_KPI_UNPUBLISHED'); ?></div>
                    <strong class="fs-3"><?php echo (int) ($this->statusSummary['suspended'] ?? 0); ?></strong>
                </div>
            </a>
            <a class="card xdecaro-suite__metric xdecaro-people-kpi-link is-danger<?php echo $filterState === '-2' ? ' is-active' : ''; ?> text-decoration-none" href="<?php echo $trashedPeopleUrl; ?>"<?php echo $filterState === '-2' ? ' aria-current="page"' : ''; ?>>
                <div class="card-body">
                    <div class="small text-body-secondary mb-1"><?php echo Text::_('COM_XDECAROPEOPLE_KPI_TRASHED'); ?></div>
                    <strong class="fs-3"><?php echo (int) ($this->statusSummary['trashed'] ?? 0); ?></strong>
                </div>
            </a>
            <a class="card xdecaro-suite__metric xdecaro-people-kpi-link is-warning text-decoration-none" href="<?php echo $duplicatesUrl; ?>">
                <div class="card-body">
                    <div class="small text-body-secondary mb-1"><?php echo Text::_('COM_XDECAROPEOPLE_KPI_DUPLICATES'); ?></div>
                    <strong class="fs-3"><?php echo (int) $this->duplicateGroups; ?></strong>
                </div>
            </a>
        </div>

        <section class="card xdecaro-suite__section">
            <div class="card-body">
                <div class="xdecaro-filterbar xdecaro-filterbar--panel mb-3" data-xdecaro-filterbar>
                    <div class="xdecaro-filterbar__primary">
                        <div class="xdecaro-filterbar__search-shell">
                            <span class="icon-search" aria-hidden="true"></span>
                            <input type="search" name="filter_search" class="form-control" value="<?php echo $this->escape((string) $this->state->get('filter.search')); ?>" placeholder="<?php echo Text::_('COM_XDECAROPEOPLE_SEARCH_PLACEHOLDER'); ?>">
                        </div>
                        <button class="xdecaro-button xdecaro-button--primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                        <a class="xdecaro-button xdecaro-button--secondary" href="<?php echo $allPeopleUrl; ?>"><span class="icon-times" aria-hidden="true"></span><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></a>
                        <button class="xdecaro-button xdecaro-button--secondary xdecaro-filterbar__toggle" type="button" data-xdecaro-filterbar-toggle aria-expanded="<?php echo $filtersOpen ? 'true' : 'false'; ?>" aria-controls="xdecaro-people-advanced-filters"><span class="icon-filter" aria-hidden="true"></span><?php echo Text::_('COM_XDECAROPEOPLE_FILTERS'); ?></button>
                    </div>
                    <div id="xdecaro-people-advanced-filters" class="xdecaro-filterbar__advanced" data-xdecaro-filterbar-open<?php echo $filtersOpen ? '' : ' hidden'; ?>>
                        <div class="xdecaro-filterbar__advanced-fields">
                            <select name="filter_person_status" class="form-select" aria-label="<?php echo $this->escape(Text::_('COM_XDECAROPEOPLE_FIELD_PERSON_STATUS')); ?>">
                                <option value=""><?php echo Text::_('COM_XDECAROPEOPLE_FILTER_PERSON_STATUS_ALL'); ?></option>
                                <option value="active" <?php echo $filterPersonStatus === 'active' ? 'selected' : ''; ?>><?php echo Text::_('COM_XDECAROPEOPLE_PERSON_STATUS_ACTIVE'); ?></option>
                                <option value="archived" <?php echo $filterPersonStatus === 'archived' ? 'selected' : ''; ?>><?php echo Text::_('COM_XDECAROPEOPLE_PERSON_STATUS_ARCHIVED'); ?></option>
                                <option value="deceased" <?php echo $filterPersonStatus === 'deceased' ? 'selected' : ''; ?>><?php echo Text::_('COM_XDECAROPEOPLE_PERSON_STATUS_DECEASED'); ?></option>
                            </select>
                            <select name="filter_state" class="form-select" aria-label="<?php echo $this->escape(Text::_('JSTATUS')); ?>">
                                <option value=""><?php echo Text::_('COM_XDECAROPEOPLE_FILTER_STATE_ALL'); ?></option>
                                <option value="1" <?php echo $filterState === '1' ? 'selected' : ''; ?>><?php echo Text::_('JPUBLISHED'); ?></option>
                                <option value="0" <?php echo $filterState === '0' ? 'selected' : ''; ?>><?php echo Text::_('JUNPUBLISHED'); ?></option>
                                <option value="-2" <?php echo $filterState === '-2' ? 'selected' : ''; ?>><?php echo Text::_('COM_XDECAROPEOPLE_FILTER_TRASHED'); ?></option>
                            </select>
                        </div>
                        <button class="xdecaro-button xdecaro-button--secondary xdecaro-filterbar__close" type="button" data-xdecaro-filterbar-close><span class="icon-times" aria-hidden="true"></span><?php echo Text::_('COM_XDECAROPEOPLE_FILTERS_CLOSE'); ?></button>
                    </div>
                </div>

                <div class="xdecaro-suite__responsive-wrap table-responsive">
                    <table class="xdecaro-suite__responsive-table xdecaro-suite__responsive-table--striped table align-middle">
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
                                <th class="xdecaro-people-person-status-heading"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_PERSON_STATUS'); ?></th>
                                <th class="xdecaro-people-publication-heading"><?php echo Text::_('JSTATUS'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->items as $i => $item) : ?>
                                <?php
                                $personStatus = (string) ($item->person_status ?? 'active');
                                $personStatusLabel = match ($personStatus) {
                                    'archived' => Text::_('COM_XDECAROPEOPLE_PERSON_STATUS_ARCHIVED'),
                                    'deceased' => Text::_('COM_XDECAROPEOPLE_PERSON_STATUS_DECEASED'),
                                    default => Text::_('COM_XDECAROPEOPLE_PERSON_STATUS_ACTIVE'),
                                };
                                $personStatusTone = match ($personStatus) {
                                    'archived' => 'xdecaro-badge--warning',
                                    'deceased' => 'xdecaro-badge--neutral',
                                    default => 'xdecaro-badge--success',
                                };

                                $state = (int) $item->state;
                                $stateLabel = match ($state) {
                                    1 => Text::_('JPUBLISHED'),
                                    -2 => Text::_('COM_XDECAROPEOPLE_FILTER_TRASHED'),
                                    default => Text::_('JUNPUBLISHED'),
                                };
                                $publicationTone = match ($state) {
                                    1 => 'is-success',
                                    -2 => 'is-danger',
                                    default => 'is-warning',
                                };
                                $publicationIcon = match ($state) {
                                    1 => 'icon-check',
                                    -2 => 'icon-trash',
                                    default => 'icon-pause',
                                };
                                $stateUrl = match ($state) {
                                    1 => $publishedPeopleUrl,
                                    -2 => $trashedPeopleUrl,
                                    default => $suspendedPeopleUrl,
                                };
                                ?>
                                <tr>
                                    <td data-label="<?php echo $this->escape(Text::_('JSELECT')); ?>"><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id); ?></td>
                                    <td data-label="<?php echo $this->escape(Text::_('COM_XDECAROPEOPLE_FIELD_DISPLAY_NAME')); ?>"><a class="fw-semibold" href="<?php echo Route::_('index.php?option=com_xdecaropeople&task=person.edit&id=' . (int) $item->id); ?>"><?php echo $this->escape($item->display_name); ?></a></td>
                                    <?php if ($this->canIdentityDetails) : ?>
                                        <td data-label="<?php echo $this->escape(Text::_('COM_XDECAROPEOPLE_FIELD_BIRTH_DATE')); ?>"><?php echo !empty($item->birth_date) ? $this->escape(HTMLHelper::_('date', $item->birth_date, Text::_('DATE_FORMAT_FILTER_DATE'))) : '—'; ?></td>
                                        <td data-label="<?php echo $this->escape(Text::_('COM_XDECAROPEOPLE_FIELD_BIRTH_PLACE')); ?>"><?php echo $this->escape((string) ($item->birth_place ?? '')); ?></td>
                                    <?php endif; ?>
                                    <td data-label="<?php echo $this->escape(Text::_('JGLOBAL_EMAIL')); ?>"><?php echo $this->escape((string) $item->email); ?></td>
                                    <td data-label="<?php echo $this->escape(Text::_('COM_XDECAROPEOPLE_FIELD_PHONE')); ?>"><?php echo $this->escape((string) $item->phone); ?></td>
                                    <td class="xdecaro-people-person-status-cell" data-label="<?php echo $this->escape(Text::_('COM_XDECAROPEOPLE_FIELD_PERSON_STATUS')); ?>"><span class="xdecaro-badge <?php echo $personStatusTone; ?>"><?php echo $personStatusLabel; ?></span></td>
                                    <td class="xdecaro-people-publication-cell" data-label="<?php echo $this->escape(Text::_('JSTATUS')); ?>">
                                        <a class="xdecaro-people-publication-link <?php echo $publicationTone; ?>" href="<?php echo $stateUrl; ?>" title="<?php echo $this->escape(Text::_('JSEARCH_FILTER')); ?>: <?php echo $this->escape($stateLabel); ?>" aria-label="<?php echo $this->escape($stateLabel); ?>">
                                            <span class="<?php echo $publicationIcon; ?>" aria-hidden="true"></span>
                                            <span class="visually-hidden"><?php echo $stateLabel; ?></span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="xdecaro-people-pagination-footer mt-3">
                    <div class="xdecaro-people-page-size d-flex flex-wrap align-items-center gap-2">
                        <label for="xdecaro-people-page-size" class="form-label mb-0"><?php echo Text::_('COM_XDECAROPEOPLE_PAGINATION_SHOW'); ?></label>
                        <select id="xdecaro-people-page-size" name="list[limit]" class="form-select form-select-sm" onchange="this.form.requestSubmit()">
                            <?php foreach ($limitChoices as $limitChoice) : ?>
                                <option value="<?php echo (int) $limitChoice; ?>" <?php echo $currentLimit === $limitChoice ? 'selected' : ''; ?>><?php echo $limitChoice === 999999 ? Text::sprintf('COM_XDECAROPEOPLE_PAGINATION_ALL', $totalItems) : (int) $limitChoice; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-body-secondary"><?php echo Text::_('COM_XDECAROPEOPLE_PAGINATION_PER_PAGE'); ?></span>
                    </div>
                    <div class="xdecaro-people-pagination-links"><?php echo $this->pagination->getListFooter(); ?></div>
                </div>
            </div>
        </section>
    </div>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<dialog id="xdecaro-people-export-dialog" class="xdecaro-people-export-dialog border-0 rounded-3 shadow-lg p-0" data-no-selection="<?php echo $this->escape(Text::_('COM_XDECAROPEOPLE_EXPORT_NO_SELECTION')); ?>" data-no-columns="<?php echo $this->escape(Text::_('COM_XDECAROPEOPLE_EXPORT_NO_COLUMNS')); ?>">
    <div class="card border-0">
        <div class="card-header d-flex align-items-center justify-content-between gap-3">
            <strong><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_TITLE'); ?></strong>
            <button type="button" class="btn-close" aria-label="<?php echo Text::_('JCLOSE'); ?>" data-xdecaro-export-close></button>
        </div>
        <div class="card-body">
            <div class="mb-4">
                <label class="form-label fw-semibold" for="xdecaro-people-export-format-ui"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_FORMAT'); ?></label>
                <select id="xdecaro-people-export-format-ui" class="form-select">
                    <option value="xlsx"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_FORMAT_XLSX'); ?></option>
                    <option value="csv"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_FORMAT_CSV'); ?></option>
                    <option value="pdf"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_FORMAT_PDF'); ?></option>
                </select>
            </div>
            <fieldset class="mb-4">
                <legend class="fs-6 fw-semibold mb-3"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_SCOPE'); ?></legend>
                <div class="form-check mb-2"><input class="form-check-input" type="radio" name="xdecaro_export_scope_ui" id="xdecaro-people-export-scope-filtered" value="filtered" checked><label class="form-check-label" for="xdecaro-people-export-scope-filtered"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_SCOPE_FILTERED'); ?></label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="radio" name="xdecaro_export_scope_ui" id="xdecaro-people-export-scope-all" value="all"><label class="form-check-label" for="xdecaro-people-export-scope-all"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_SCOPE_ALL'); ?></label></div>
                <div class="form-check"><input class="form-check-input" type="radio" name="xdecaro_export_scope_ui" id="xdecaro-people-export-scope-selected" value="selected"><label class="form-check-label" for="xdecaro-people-export-scope-selected"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_SCOPE_SELECTED'); ?> (<span id="xdecaro-people-export-selected-count">0</span>)</label></div>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2"><small class="text-body-secondary"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_SELECTION_PERSIST_HELP'); ?></small><button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="xdecaro-people-export-clear-selection"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_CLEAR_SELECTION'); ?></button></div>
            </fieldset>
            <fieldset>
                <legend class="fs-6 fw-semibold mb-2"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_COLUMNS'); ?> (<span id="xdecaro-people-export-column-count">0</span>)</legend>
                <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3"><div class="btn-group btn-group-sm" role="group" aria-label="<?php echo $this->escape(Text::_('COM_XDECAROPEOPLE_EXPORT_COLUMNS')); ?>"><button type="button" class="btn btn-outline-secondary" id="xdecaro-people-export-columns-visible"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_COLUMNS_VISIBLE'); ?></button><button type="button" class="btn btn-outline-secondary" id="xdecaro-people-export-columns-all"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_COLUMNS_ALL'); ?></button><button type="button" class="btn btn-outline-secondary" id="xdecaro-people-export-columns-none"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_COLUMNS_NONE'); ?></button></div></div>
                <div class="row row-cols-1 row-cols-md-2 g-2 xdecaro-people-export-columns">
                    <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="display_name" id="xdecaro-export-column-display-name" data-xdecaro-export-column data-visible-column checked><label class="form-check-label" for="xdecaro-export-column-display-name"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_DISPLAY_NAME'); ?></label></div></div>
                    <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="first_name" id="xdecaro-export-column-first-name" data-xdecaro-export-column><label class="form-check-label" for="xdecaro-export-column-first-name"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_FIRST_NAME'); ?></label></div></div>
                    <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="last_name" id="xdecaro-export-column-last-name" data-xdecaro-export-column><label class="form-check-label" for="xdecaro-export-column-last-name"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_LAST_NAME'); ?></label></div></div>
                    <?php if ($this->canSensitive) : ?><div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="tax_identifier" id="xdecaro-export-column-tax-identifier" data-xdecaro-export-column><label class="form-check-label" for="xdecaro-export-column-tax-identifier"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_COLUMN_TAX_IDENTIFIER'); ?></label></div></div><?php endif; ?>
                    <?php if ($this->canIdentityDetails) : ?>
                        <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="birth_date" id="xdecaro-export-column-birth-date" data-xdecaro-export-column data-visible-column checked><label class="form-check-label" for="xdecaro-export-column-birth-date"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_BIRTH_DATE'); ?></label></div></div>
                        <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="birth_place" id="xdecaro-export-column-birth-place" data-xdecaro-export-column data-visible-column checked><label class="form-check-label" for="xdecaro-export-column-birth-place"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_BIRTH_PLACE'); ?></label></div></div>
                        <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="birth_region" id="xdecaro-export-column-birth-region" data-xdecaro-export-column><label class="form-check-label" for="xdecaro-export-column-birth-region"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_BIRTH_REGION'); ?></label></div></div>
                        <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="sex" id="xdecaro-export-column-sex" data-xdecaro-export-column><label class="form-check-label" for="xdecaro-export-column-sex"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_SEX'); ?></label></div></div>
                        <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="address_line" id="xdecaro-export-column-address" data-xdecaro-export-column><label class="form-check-label" for="xdecaro-export-column-address"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_ADDRESS'); ?></label></div></div>
                        <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="address_number" id="xdecaro-export-column-address-number" data-xdecaro-export-column><label class="form-check-label" for="xdecaro-export-column-address-number"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_ADDRESS_NUMBER'); ?></label></div></div>
                        <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="postal_code" id="xdecaro-export-column-postal-code" data-xdecaro-export-column><label class="form-check-label" for="xdecaro-export-column-postal-code"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_POSTAL_CODE'); ?></label></div></div>
                        <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="city" id="xdecaro-export-column-city" data-xdecaro-export-column><label class="form-check-label" for="xdecaro-export-column-city"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_CITY'); ?></label></div></div>
                        <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="region" id="xdecaro-export-column-region" data-xdecaro-export-column><label class="form-check-label" for="xdecaro-export-column-region"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_REGION'); ?></label></div></div>
                        <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="country_code" id="xdecaro-export-column-country" data-xdecaro-export-column><label class="form-check-label" for="xdecaro-export-column-country"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_COUNTRY'); ?></label></div></div>
                    <?php endif; ?>
                    <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="email" id="xdecaro-export-column-email" data-xdecaro-export-column data-visible-column checked><label class="form-check-label" for="xdecaro-export-column-email"><?php echo Text::_('JGLOBAL_EMAIL'); ?></label></div></div>
                    <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="phone" id="xdecaro-export-column-phone" data-xdecaro-export-column data-visible-column checked><label class="form-check-label" for="xdecaro-export-column-phone"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_PHONE'); ?></label></div></div>
                    <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="state" id="xdecaro-export-column-state" data-xdecaro-export-column data-visible-column checked><label class="form-check-label" for="xdecaro-export-column-state"><?php echo Text::_('JSTATUS'); ?></label></div></div>
                </div>
            </fieldset>
            <p class="text-body-secondary small mt-3 mb-0"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_PRIVACY_NOTICE_178'); ?></p>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2"><button type="button" class="btn btn-secondary" data-xdecaro-export-close><?php echo Text::_('JCANCEL'); ?></button><button type="button" class="btn btn-primary" id="xdecaro-people-export-download"><?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_DOWNLOAD'); ?></button></div>
    </div>
</dialog>

<form action="<?php echo Route::_('index.php?option=com_xdecaropeople'); ?>" method="post" id="xdecaro-people-export-form" class="d-none">
    <input type="hidden" name="task" value="export.download">
    <input type="hidden" name="export_format" value="xlsx">
    <input type="hidden" name="export_scope" value="filtered">
    <input type="hidden" name="export_selected_ids" value="">
    <input type="hidden" name="filter_search" value="">
    <input type="hidden" name="filter_person_status" value="">
    <input type="hidden" name="filter_state" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>