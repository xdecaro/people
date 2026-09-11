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
        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <input
                    type="search"
                    name="filter_search"
                    class="form-control"
                    value="<?php echo $this->escape((string) $this->state->get('filter.search')); ?>"
                    placeholder="<?php echo Text::_('JSEARCH_FILTER'); ?>"
                >
            </div>
            <div class="col-md-3">
                <select name="filter_state" class="form-select" onchange="this.form.submit()">
                    <option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?></option>
                    <option value="1" <?php echo $filterState === '1' ? 'selected' : ''; ?>><?php echo Text::_('JPUBLISHED'); ?></option>
                    <option value="0" <?php echo $filterState === '0' ? 'selected' : ''; ?>><?php echo Text::_('JUNPUBLISHED'); ?></option>
                    <option value="-2" <?php echo $filterState === '-2' ? 'selected' : ''; ?>><?php echo Text::_('COM_XDECAROPEOPLE_FILTER_TRASHED'); ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th><input type="checkbox" name="checkall-toggle" onclick="Joomla.checkAll(this)"></th>
                        <th><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_DISPLAY_NAME'); ?></th>
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
