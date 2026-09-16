<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');

$personHeading = trim(
    (string) ($this->item->first_name ?? '')
    . ' '
    . (string) ($this->item->last_name ?? '')
);

if ($personHeading === '') {
    $personHeading = Text::_('COM_XDECAROPEOPLE_PERSON_NEW');
}

$escape = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<form
    action="<?php echo Route::_('index.php?option=com_xdecaropeople&view=person&layout=edit&id=' . (int) ($this->item->id ?? 0)); ?>"
    method="post"
    name="adminForm"
    id="adminForm"
    class="form-validate"
>
    <div class="xdecaro-scope xdecaro-people-person-edit">
        <div class="xdecaro-person-heading">
            <h2><?php echo $escape($personHeading); ?></h2>
        </div>

        <?php
        echo HTMLHelper::_('uitab.startTabSet', 'personTabs', ['active' => 'identity']);

        echo HTMLHelper::_('uitab.addTab', 'personTabs', 'identity', Text::_('COM_XDECAROPEOPLE_FIELDSET_IDENTITY'));
        echo $this->form->renderFieldset('identity');
        echo HTMLHelper::_('uitab.endTab');

        echo HTMLHelper::_('uitab.addTab', 'personTabs', 'contacts', Text::_('COM_XDECAROPEOPLE_FIELDSET_CONTACTS'));
        echo $this->form->renderFieldset('contacts');
        echo HTMLHelper::_('uitab.endTab');

        if ($this->form->getFieldset('disability')) {
            echo HTMLHelper::_('uitab.addTab', 'personTabs', 'disability', Text::_('COM_XDECAROPEOPLE_FIELDSET_DISABILITY'));
            echo $this->form->renderFieldset('disability');
            echo HTMLHelper::_('uitab.endTab');
        }

        if ($this->form->getFieldset('accessibility')) {
            echo HTMLHelper::_('uitab.addTab', 'personTabs', 'accessibility', Text::_('COM_XDECAROPEOPLE_FIELDSET_ACCESSIBILITY'));
            echo $this->form->renderFieldset('accessibility');
            echo HTMLHelper::_('uitab.endTab');
        }

        echo HTMLHelper::_('uitab.addTab', 'personTabs', 'residence', Text::_('COM_XDECAROPEOPLE_FIELDSET_RESIDENCE'));
        echo $this->form->renderFieldset('residence');
        echo HTMLHelper::_('uitab.endTab');

        if ($this->form->getFieldset('relations')) {
            echo HTMLHelper::_('uitab.addTab', 'personTabs', 'relations', Text::_('COM_XDECAROPEOPLE_FIELDSET_RELATIONS'));
            echo $this->form->renderFieldset('relations');
            echo HTMLHelper::_('uitab.endTab');
        }

        if ($this->form->getFieldset('documents_tax')) {
            echo HTMLHelper::_('uitab.addTab', 'personTabs', 'documents_tax', Text::_('COM_XDECAROPEOPLE_FIELDSET_DOCUMENTS_TAX'));
            echo $this->form->renderFieldset('documents_tax');
            echo HTMLHelper::_('uitab.endTab');
        }

        echo HTMLHelper::_('uitab.addTab', 'personTabs', 'social', Text::_('COM_XDECAROPEOPLE_FIELDSET_SOCIAL'));
        echo $this->form->renderFieldset('social');
        echo HTMLHelper::_('uitab.endTab');

        if ($this->competitionsHistoryAvailable) {
            echo HTMLHelper::_('uitab.addTab', 'personTabs', 'competitions', Text::_('COM_XDECAROPEOPLE_COMPETITIONS_TAB'));
            ?>
            <div class="xdecaro-card">
                <h3><?php echo Text::_('COM_XDECAROPEOPLE_COMPETITIONS_HISTORY'); ?></h3>
                <p class="text-muted">
                    <?php echo Text::sprintf('COM_XDECAROPEOPLE_COMPETITIONS_HISTORY_COUNT', count($this->competitionsHistory)); ?>
                </p>

                <?php if ($this->competitionsHistory === []) : ?>
                    <div class="alert alert-info mb-0" role="status">
                        <?php echo Text::_('COM_XDECAROPEOPLE_COMPETITIONS_HISTORY_EMPTY'); ?>
                    </div>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_COMPETITIONS_COMPETITION'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_COMPETITIONS_SEASON'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_COMPETITIONS_TEAM'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_COMPETITIONS_ROLE'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_COMPETITIONS_SHIRT_NUMBER'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_COMPETITIONS_STATUS'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->competitionsHistory as $historyRow) :
                                    $season = trim((string) ($historyRow['season_name'] ?? ''));
                                    if ($season === '' && isset($historyRow['season_year'])) {
                                        $season = (string) $historyRow['season_year'];
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo $escape($historyRow['competition_name'] ?? '—'); ?></td>
                                        <td><?php echo $escape($season !== '' ? $season : '—'); ?></td>
                                        <td><?php echo $escape($historyRow['team_name'] ?? '—'); ?></td>
                                        <td><?php echo $escape($historyRow['role'] ?? '—'); ?></td>
                                        <td><?php echo $escape($historyRow['shirt_number'] ?? '—'); ?></td>
                                        <td><?php echo $escape($historyRow['status'] ?? '—'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            echo HTMLHelper::_('uitab.endTab');
        }

        echo HTMLHelper::_('uitab.addTab', 'personTabs', 'publishing', Text::_('JGLOBAL_FIELDSET_PUBLISHING'));
        echo $this->form->renderFieldset('publishing');
        echo HTMLHelper::_('uitab.endTab');

        echo HTMLHelper::_('uitab.addTab', 'personTabs', 'system', Text::_('COM_XDECAROPEOPLE_FIELDSET_SYSTEM'));
        echo $this->form->renderFieldset('system');
        echo HTMLHelper::_('uitab.endTab');

        echo HTMLHelper::_('uitab.endTabSet');
        ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
