<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.multiselect');

$filterState = (string) $this->state->get('filter.state');
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

        <?php echo $this->pagination->getListFooter(); ?>
    </div>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<dialog
    id="xdecaro-people-export-dialog"
    class="xdecaro-people-export-dialog border-0 rounded-3 shadow-lg p-0"
    data-no-selection="<?php echo $this->escape(Text::_('COM_XDECAROPEOPLE_EXPORT_NO_SELECTION')); ?>"
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

            <fieldset>
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
            </fieldset>

            <p class="text-body-secondary small mt-3 mb-0">
                <?php echo Text::_('COM_XDECAROPEOPLE_EXPORT_PRIVACY_NOTICE'); ?>
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
    <input type="hidden" name="filter_search" value="">
    <input type="hidden" name="filter_state" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
