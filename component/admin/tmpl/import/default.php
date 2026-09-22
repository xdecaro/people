<?php

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<div class="xdecaro-scope xdecaro-people-import">
    <div class="alert alert-info" role="status">
        <?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_PRIVACY_NOTICE'); ?>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h5 mb-3"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_STEP_FILE'); ?></h2>
            <div class="mb-3">
                <label class="form-label" for="xdecaro-people-import-file"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_FILE'); ?></label>
                <input class="form-control" type="file" id="xdecaro-people-import-file" accept=".csv,text/csv">
                <div class="form-text"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_FILE_HELP'); ?></div>
            </div>
            <div id="xdecaro-people-import-file-info" class="small text-body-secondary" aria-live="polite"></div>
        </div>
    </div>

    <div class="card mb-3" id="xdecaro-people-import-mapping-card" hidden>
        <div class="card-body">
            <h2 class="h5 mb-2"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_STEP_MAPPING'); ?></h2>
            <p class="text-body-secondary"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_MAPPING_HELP'); ?></p>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_PEOPLE_FIELD'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_SOURCE_COLUMN'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="xdecaro-people-import-mapping"></tbody>
                </table>
            </div>
            <button type="button" class="btn btn-primary" id="xdecaro-people-import-analyze">
                <?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_ANALYZE'); ?>
            </button>
        </div>
    </div>

    <div class="card mb-3" id="xdecaro-people-import-summary-card" hidden>
        <div class="card-body">
            <h2 class="h5 mb-3"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_STEP_REVIEW'); ?></h2>
            <div class="xdecaro-import-summary" id="xdecaro-people-import-summary" aria-live="polite"></div>

            <details class="xdecaro-import-review mt-3" id="xdecaro-people-import-invalid-panel" hidden>
                <summary>
                    <span id="xdecaro-people-import-invalid-title"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_INVALID_DETAILS_TITLE'); ?></span>
                </summary>
                <p class="text-body-secondary mt-2 mb-2"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_INVALID_DETAILS_HELP'); ?></p>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_REPORT_ROW'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_PERSON'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_FIELD_TAX_IDENTIFIER'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_PROBLEM'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="xdecaro-people-import-invalid-body"></tbody>
                    </table>
                </div>
            </details>

            <div class="alert alert-warning mt-3 mb-3">
                <?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_EXISTING_HELP'); ?>
            </div>
            <button type="button" class="btn btn-success" id="xdecaro-people-import-start" disabled>
                <?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_START'); ?>
            </button>
        </div>
    </div>

    <div class="card mb-3" id="xdecaro-people-import-progress-card" hidden>
        <div class="card-body">
            <h2 class="h5 mb-3"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_PROGRESS'); ?></h2>
            <div class="progress mb-2" role="progressbar" aria-label="<?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_PROGRESS'); ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" id="xdecaro-people-import-progress" style="width:0%">0%</div>
            </div>
            <div id="xdecaro-people-import-progress-text" aria-live="polite"></div>
        </div>
    </div>

    <div class="card" id="xdecaro-people-import-report-card" hidden>
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h5 mb-0"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_REPORT'); ?></h2>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="xdecaro-people-import-download-report">
                    <?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_DOWNLOAD_REPORT'); ?>
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_REPORT_ROW'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_REPORT_STATUS'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_XDECAROPEOPLE_IMPORT_REPORT_MESSAGE'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="xdecaro-people-import-report"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
