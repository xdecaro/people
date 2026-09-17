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
$formatOrganizationDate = static function (mixed $value): string {
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return '—';
    }

    return HTMLHelper::_('date', $value, Text::_('DATE_FORMAT_FILTER_DATE'));
};
$organizationRoleLabel = static function (array $appointment): string {
    $custom = trim((string) ($appointment['role_custom'] ?? ''));
    if ($custom !== '') {
        return $custom;
    }

    $key = trim((string) ($appointment['role_label_key'] ?? ''));
    return $key !== '' ? Text::_($key) : '—';
};
$organizationStatusLabel = static function (array $appointment): string {
    $status = strtolower(trim((string) ($appointment['visual_status'] ?? '')));
    if ($status === '') {
        return '—';
    }

    return Text::_('COM_XDECAROORGANIZATIONS_STATUS_' . strtoupper($status));
};
$organizationEndReasonLabel = static function (array $appointment): string {
    return match (strtolower(trim((string) ($appointment['end_reason'] ?? '')))) {
        'term_end' => Text::_('COM_XDECAROORGANIZATIONS_END_TERM'),
        'resignation' => Text::_('COM_XDECAROORGANIZATIONS_END_RESIGNATION'),
        'revocation' => Text::_('COM_XDECAROORGANIZATIONS_END_REVOCATION'),
        'forfeiture' => Text::_('COM_XDECAROORGANIZATIONS_END_FORFEITURE'),
        'other' => Text::_('COM_XDECAROORGANIZATIONS_END_OTHER'),
        default => '—',
    };
};
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

        if ($this->organizationsHistoryAvailable) {
            echo HTMLHelper::_('uitab.addTab', 'personTabs', 'organizations', Text::_('COM_XDECAROPEOPLE_ORGANIZATIONS_TAB'));
            ?>
            <div class="xdecaro-card mb-4">
                <h3><?php echo Text::_('COM_XDECAROPEOPLE_ORGANIZATIONS_CURRENT'); ?></h3>
                <?php if ($this->organizationsCurrent === []) : ?>
                    <div class="alert alert-info mb-0" role="status">
                        <?php echo Text::_('COM_XDECAROPEOPLE_ORGANIZATIONS_CURRENT_EMPTY'); ?>
                    </div>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_ORGANIZATIONS_ORGANIZATION'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_ORGANIZATIONS_ROLE'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_ORGANIZATIONS_START'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_ORGANIZATIONS_PLANNED_END'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_ORGANIZATIONS_STATUS'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->organizationsCurrent as $appointment) :
                                    $organizationId = (int) ($appointment['organization_id'] ?? 0);
                                    $organizationName = trim((string) ($appointment['organization_name'] ?? ''));
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if ($organizationId > 0) : ?>
                                                <a href="<?php echo Route::_('index.php?option=com_xdecaroorganizations&task=organization.edit&id=' . $organizationId); ?>">
                                                    <?php echo $escape($organizationName !== '' ? $organizationName : '—'); ?>
                                                </a>
                                            <?php else : ?>
                                                <?php echo $escape($organizationName !== '' ? $organizationName : '—'); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $escape($organizationRoleLabel($appointment)); ?></td>
                                        <td><?php echo $escape($formatOrganizationDate($appointment['starts_on'] ?? '')); ?></td>
                                        <td><?php echo $escape($formatOrganizationDate($appointment['planned_ends_on'] ?? '')); ?></td>
                                        <td><?php echo $escape($organizationStatusLabel($appointment)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="xdecaro-card">
                <h3><?php echo Text::_('COM_XDECAROPEOPLE_ORGANIZATIONS_HISTORY'); ?></h3>
                <?php if ($this->organizationsHistory === []) : ?>
                    <div class="alert alert-info mb-0" role="status">
                        <?php echo Text::_('COM_XDECAROPEOPLE_ORGANIZATIONS_HISTORY_EMPTY'); ?>
                    </div>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_ORGANIZATIONS_ORGANIZATION'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_ORGANIZATIONS_ROLE'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_ORGANIZATIONS_MANDATE'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_ORGANIZATIONS_ENDED_ON'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_ORGANIZATIONS_END_REASON'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->organizationsHistory as $appointment) :
                                    $organizationId = (int) ($appointment['organization_id'] ?? 0);
                                    $organizationName = trim((string) ($appointment['organization_name'] ?? ''));
                                    $mandate = $formatOrganizationDate($appointment['starts_on'] ?? '')
                                        . ' → '
                                        . $formatOrganizationDate($appointment['planned_ends_on'] ?? '');
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if ($organizationId > 0) : ?>
                                                <a href="<?php echo Route::_('index.php?option=com_xdecaroorganizations&task=organization.edit&id=' . $organizationId); ?>">
                                                    <?php echo $escape($organizationName !== '' ? $organizationName : '—'); ?>
                                                </a>
                                            <?php else : ?>
                                                <?php echo $escape($organizationName !== '' ? $organizationName : '—'); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $escape($organizationRoleLabel($appointment)); ?></td>
                                        <td><?php echo $escape($mandate); ?></td>
                                        <td><?php echo $escape($formatOrganizationDate($appointment['ended_on'] ?? '')); ?></td>
                                        <td><?php echo $escape($organizationEndReasonLabel($appointment)); ?></td>
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

        if ($this->membershipAvailable) {
            echo HTMLHelper::_('uitab.addTab', 'personTabs', 'membership', Text::_('COM_XDECAROPEOPLE_MEMBERSHIP_TAB'));
            ?>
            <div class="xdecaro-card">
                <h3><?php echo Text::_('COM_XDECAROPEOPLE_MEMBERSHIP_TITLE'); ?></h3>
                <?php if ($this->memberships === []) : ?>
                    <div class="alert alert-info mb-0" role="status">
                        <?php echo Text::_('COM_XDECAROPEOPLE_MEMBERSHIP_EMPTY'); ?>
                    </div>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead><tr>
                                <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_MEMBERSHIP_NUMBER'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_MEMBERSHIP_CATEGORY'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_MEMBERSHIP_LOCATION'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_MEMBERSHIP_STATUS'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_MEMBERSHIP_SINCE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_MEMBERSHIP_VOTE_ACTIVE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_MEMBERSHIP_VOTE_PASSIVE'); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($this->memberships as $membership) : ?>
                                <tr>
                                    <td>
                                    <?php if (!empty($membership['member_id'])) : ?>
                                        <a href="<?php echo Route::_('index.php?option=com_decaromembership&view=record&entity=members&id=' . (int) $membership['member_id']); ?>">
                                            <?php echo $escape($membership['member_number'] ?? '—'); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo $escape($membership['member_number'] ?? '—'); ?>
                                    <?php endif; ?>
                                    </td>
                                    <td><?php echo $escape($membership['category_name'] ?? '—'); ?></td>
                                    <td><?php echo $escape($membership['location_name'] ?? '—'); ?></td>
                                    <td><?php echo $escape($membership['status'] ?? '—'); ?></td>
                                    <td><?php echo $escape($membership['first_registration_date'] ?? '—'); ?></td>
                                    <td><?php echo !empty($membership['voting_active']) ? Text::_('JYES') : Text::_('JNO'); ?></td>
                                    <td><?php echo !empty($membership['voting_passive']) ? Text::_('JYES') : Text::_('JNO'); ?></td>
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
