<?php

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$duplicatesUrl = Route::_('index.php?option=com_xdecaropeople&view=duplicates');
$peopleUrl = Route::_('index.php?option=com_xdecaropeople&view=people');
$informationUrl = Route::_('index.php?option=com_xdecaropeople&view=information');
?>
<div class="xdecaro-scope xdecaro-people-dashboard">
    <div class="xdecaro-dashboard-kpis mb-4">
        <div class="card xdecaro-dashboard-kpi is-primary">
            <div class="card-body">
                <div class="xdecaro-dashboard-kpi-label"><?php echo Text::_('COM_XDECAROPEOPLE_PEOPLE'); ?></div>
                <strong><?php echo (int) $this->total; ?></strong>
            </div>
        </div>

        <a class="card xdecaro-dashboard-kpi is-primary text-decoration-none" href="<?php echo $duplicatesUrl; ?>">
            <div class="card-body">
                <div class="xdecaro-dashboard-kpi-label"><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_DUPLICATE_GROUPS'); ?></div>
                <strong><?php echo (int) $this->duplicateStats['total']; ?></strong>
            </div>
        </a>

        <a class="card xdecaro-dashboard-kpi is-danger text-decoration-none" href="<?php echo $duplicatesUrl; ?>">
            <div class="card-body">
                <div class="xdecaro-dashboard-kpi-label"><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_CONFLICTS'); ?></div>
                <strong><?php echo (int) $this->duplicateStats['conflict']; ?></strong>
            </div>
        </a>

        <a class="card xdecaro-dashboard-kpi is-warning text-decoration-none" href="<?php echo $duplicatesUrl; ?>">
            <div class="card-body">
                <div class="xdecaro-dashboard-kpi-label"><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_POSSIBLE'); ?></div>
                <strong><?php echo (int) $this->duplicateStats['possible']; ?></strong>
            </div>
        </a>

        <a class="card xdecaro-dashboard-kpi is-neutral text-decoration-none" href="<?php echo $duplicatesUrl; ?>">
            <div class="card-body">
                <div class="xdecaro-dashboard-kpi-label"><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_RECORDS_INVOLVED'); ?></div>
                <strong><?php echo (int) $this->duplicateStats['records']; ?></strong>
            </div>
        </a>
    </div>

    <div class="row g-3 mb-4 xdecaro-dashboard-review">
        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                        <div>
                            <h2 class="h5 mb-1"><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_REVIEW'); ?></h2>
                            <p class="text-body-secondary mb-0"><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_REVIEW_DESC'); ?></p>
                        </div>
                        <span class="badge bg-warning text-dark"><?php echo (int) $this->duplicateStats['total']; ?></span>
                    </div>

                    <div class="xdecaro-dashboard-review-grid mb-3">
                        <div>
                            <strong><?php echo (int) $this->duplicateStats['conflict']; ?></strong>
                            <span><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_CONFLICTS'); ?></span>
                        </div>
                        <div>
                            <strong><?php echo (int) $this->duplicateStats['strong']; ?></strong>
                            <span><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATE_STRENGTH_STRONG'); ?></span>
                        </div>
                        <div>
                            <strong><?php echo (int) $this->duplicateStats['possible']; ?></strong>
                            <span><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_POSSIBLE'); ?></span>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <a class="btn btn-outline-primary" href="<?php echo $duplicatesUrl; ?>">
                            <?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_OPEN_DUPLICATES'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                        <div>
                            <h2 class="h5 mb-1"><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_DATA_QUALITY'); ?></h2>
                            <p class="text-body-secondary mb-0"><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_DATA_QUALITY_DESC'); ?></p>
                        </div>
                        <div class="text-end">
                            <strong class="fs-4 d-block"><?php echo (int) $this->dataQuality['missing_any']; ?></strong>
                            <span class="small text-body-secondary"><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_MISSING_ANY'); ?></span>
                        </div>
                    </div>

                    <div class="xdecaro-dashboard-quality-grid">
                        <div class="xdecaro-dashboard-quality-item">
                            <strong><?php echo (int) $this->dataQuality['missing_birth_date']; ?></strong>
                            <span><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_MISSING_BIRTH_DATE'); ?></span>
                        </div>
                        <div class="xdecaro-dashboard-quality-item">
                            <strong><?php echo (int) $this->dataQuality['missing_tax_identifier']; ?></strong>
                            <span><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_MISSING_TIN'); ?></span>
                        </div>
                        <div class="xdecaro-dashboard-quality-item">
                            <strong><?php echo (int) $this->dataQuality['missing_email']; ?></strong>
                            <span><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_MISSING_EMAIL'); ?></span>
                        </div>
                        <div class="xdecaro-dashboard-quality-item">
                            <strong><?php echo (int) $this->dataQuality['missing_phone']; ?></strong>
                            <span><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_MISSING_PHONE'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="xdecaro-dashboard-integrations mb-4" aria-labelledby="xdecaro-dashboard-integrations-title">
        <h2 class="h5 mb-3" id="xdecaro-dashboard-integrations-title"><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_INTEGRATIONS'); ?></h2>
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
                            <h3 class="h6 mb-0"><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_ORGANIZATIONS'); ?></h3>
                            <span class="badge <?php echo $this->organizationsAvailable ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo Text::_($this->organizationsAvailable
                                    ? 'COM_XDECAROPEOPLE_DASHBOARD_CONNECTED'
                                    : 'COM_XDECAROPEOPLE_DASHBOARD_UNAVAILABLE'); ?>
                            </span>
                        </div>
                        <p class="text-body-secondary mb-3"><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_ORGANIZATIONS_DESC'); ?></p>
                        <?php if ($this->organizationsAvailable) : ?>
                            <div class="mt-auto">
                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_xdecaroorganizations'); ?>">
                                    <?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_OPEN_ORGANIZATIONS'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
                            <h3 class="h6 mb-0"><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_MEMBERSHIP'); ?></h3>
                            <span class="badge <?php echo $this->membershipAvailable ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo Text::_($this->membershipAvailable
                                    ? 'COM_XDECAROPEOPLE_DASHBOARD_CONNECTED'
                                    : 'COM_XDECAROPEOPLE_DASHBOARD_UNAVAILABLE'); ?>
                            </span>
                        </div>
                        <p class="text-body-secondary mb-3"><?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_MEMBERSHIP_DESC'); ?></p>
                        <?php if ($this->membershipAvailable) : ?>
                            <div class="mt-auto">
                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_decaromembership'); ?>">
                                    <?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_OPEN_MEMBERSHIP'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="card">
        <div class="card-body">
            <h2 class="h5 mb-3"><?php echo Text::_('COM_XDECAROPEOPLE_QUICK_ACTIONS'); ?></h2>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($this->canCreate) : ?>
                    <a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_xdecaropeople&task=person.add'); ?>">
                        <?php echo Text::_('COM_XDECAROPEOPLE_DASHBOARD_NEW_PERSON'); ?>
                    </a>
                <?php endif; ?>
                <a class="btn btn-outline-secondary" href="<?php echo $peopleUrl; ?>"><?php echo Text::_('COM_XDECAROPEOPLE_PEOPLE'); ?></a>
                <?php if ($this->canImport) : ?>
                    <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_xdecaropeople&view=import'); ?>">
                        <?php echo Text::_('COM_XDECAROPEOPLE_IMPORT'); ?>
                    </a>
                <?php endif; ?>
                <a class="btn btn-outline-secondary" href="<?php echo $duplicatesUrl; ?>"><?php echo Text::_('COM_XDECAROPEOPLE_DUPLICATES'); ?></a>
                <a class="btn btn-outline-secondary" href="<?php echo $informationUrl; ?>"><?php echo Text::_('COM_XDECAROPEOPLE_INFORMATION'); ?></a>
            </div>
        </div>
    </div>
</div>
