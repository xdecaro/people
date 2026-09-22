<?php

namespace xdecaro\Component\People\Administrator\View\Import;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use RuntimeException;
use xdecaro\Component\People\Administrator\Extension\PeopleComponent;

final class HtmlView extends BaseHtmlView
{
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        $isAdmin = $user->authorise('core.admin', 'com_xdecaropeople');

        if (!$isAdmin && (
            !$user->authorise('core.create', 'com_xdecaropeople')
            || !$user->authorise('people.view_sensitive', 'com_xdecaropeople')
        )) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $component = $app->bootComponent('com_xdecaropeople');
        if ($component instanceof PeopleComponent) {
            $component->getCoreIntegrationService()->enableUi($this->document->getWebAssetManager());
        }

        $wam = $this->document->getWebAssetManager();
        $wam->useStyle('com_xdecaropeople.admin');
        $wam->useScript('com_xdecaropeople.import');

        $this->document->addScriptOptions('com_xdecaropeople.import', [
            'analyzeUrl' => Route::_('index.php?option=com_xdecaropeople&task=import.analyze&format=json', false),
            'batchUrl' => Route::_('index.php?option=com_xdecaropeople&task=import.batch&format=json', false),
            'token' => Session::getFormToken(),
            'batchSize' => 100,
            'strings' => [
                'chooseFile' => Text::_('COM_XDECAROPEOPLE_IMPORT_CHOOSE_FILE'),
                'fileError' => Text::_('COM_XDECAROPEOPLE_IMPORT_FILE_ERROR'),
                'rows' => Text::_('COM_XDECAROPEOPLE_IMPORT_ROWS'),
                'valid' => Text::_('COM_XDECAROPEOPLE_IMPORT_VALID'),
                'duplicates' => Text::_('COM_XDECAROPEOPLE_IMPORT_FILE_DUPLICATES'),
                'invalid' => Text::_('COM_XDECAROPEOPLE_IMPORT_INVALID'),
                'existing' => Text::_('COM_XDECAROPEOPLE_IMPORT_EXISTING'),
                'newPeople' => Text::_('COM_XDECAROPEOPLE_IMPORT_NEW'),
                'analyzing' => Text::_('COM_XDECAROPEOPLE_IMPORT_ANALYZING'),
                'ready' => Text::_('COM_XDECAROPEOPLE_IMPORT_READY'),
                'importing' => Text::_('COM_XDECAROPEOPLE_IMPORT_IMPORTING'),
                'complete' => Text::_('COM_XDECAROPEOPLE_IMPORT_COMPLETE'),
                'requestError' => Text::_('COM_XDECAROPEOPLE_IMPORT_REQUEST_ERROR'),
                'mappingMissing' => Text::_('COM_XDECAROPEOPLE_IMPORT_MAPPING_REQUIRED'),
                'reportRow' => Text::_('COM_XDECAROPEOPLE_IMPORT_REPORT_ROW'),
                'reportStatus' => Text::_('COM_XDECAROPEOPLE_IMPORT_REPORT_STATUS'),
                'reportMessage' => Text::_('COM_XDECAROPEOPLE_IMPORT_REPORT_MESSAGE'),
                'statusInserted' => Text::_('COM_XDECAROPEOPLE_IMPORT_STATUS_INSERTED'),
                'statusExisting' => Text::_('COM_XDECAROPEOPLE_IMPORT_STATUS_EXISTING'),
                'statusInvalid' => Text::_('COM_XDECAROPEOPLE_IMPORT_STATUS_INVALID'),
                'statusError' => Text::_('COM_XDECAROPEOPLE_IMPORT_STATUS_ERROR'),
                'sourceColumn' => Text::_('COM_XDECAROPEOPLE_IMPORT_SOURCE_COLUMN'),
                'notMapped' => Text::_('COM_XDECAROPEOPLE_IMPORT_NOT_MAPPED'),
                'encodingCp1252' => Text::_('COM_XDECAROPEOPLE_IMPORT_ENCODING_CP1252'),
                'encodingUtf8' => Text::_('COM_XDECAROPEOPLE_IMPORT_ENCODING_UTF8'),
                'conflictRows' => Text::_('COM_XDECAROPEOPLE_IMPORT_CONFLICT_ROWS'),
                'conflictFields' => Text::_('COM_XDECAROPEOPLE_IMPORT_CONFLICT_FIELDS'),
                'invalidDetailsTitle' => Text::_('COM_XDECAROPEOPLE_IMPORT_INVALID_DETAILS_JS'),
                'duplicateRowsTitle' => Text::_('COM_XDECAROPEOPLE_IMPORT_DUPLICATE_ROWS_JS'),
                'duplicateGroupsTitle' => Text::_('COM_XDECAROPEOPLE_IMPORT_DUPLICATE_GROUPS_JS'),
                'duplicatePrimary' => Text::_('COM_XDECAROPEOPLE_IMPORT_DUPLICATE_PRIMARY'),
                'duplicateConsolidated' => Text::_('COM_XDECAROPEOPLE_IMPORT_DUPLICATE_CONSOLIDATED'),
                'duplicateConflict' => Text::_('COM_XDECAROPEOPLE_IMPORT_DUPLICATE_CONFLICT'),
            ],
            'codes' => [
                'missing_first_name' => Text::_('COM_XDECAROPEOPLE_IMPORT_CODE_MISSING_FIRST_NAME'),
                'missing_last_name' => Text::_('COM_XDECAROPEOPLE_IMPORT_CODE_MISSING_LAST_NAME'),
                'missing_tax_identifier' => Text::_('COM_XDECAROPEOPLE_IMPORT_CODE_MISSING_TAX_IDENTIFIER'),
                'invalid_tax_identifier' => Text::_('COM_XDECAROPEOPLE_IMPORT_CODE_INVALID_TAX_IDENTIFIER'),
                'invalid_birth_date_removed' => Text::_('COM_XDECAROPEOPLE_IMPORT_CODE_INVALID_BIRTH_DATE'),
                'invalid_sex_removed' => Text::_('COM_XDECAROPEOPLE_IMPORT_CODE_INVALID_SEX'),
                'invalid_email_removed' => Text::_('COM_XDECAROPEOPLE_IMPORT_CODE_INVALID_EMAIL'),
                'invalid_country_code_removed' => Text::_('COM_XDECAROPEOPLE_IMPORT_CODE_INVALID_COUNTRY'),
                'existing_person' => Text::_('COM_XDECAROPEOPLE_IMPORT_CODE_EXISTING'),
                'imported' => Text::_('COM_XDECAROPEOPLE_IMPORT_CODE_IMPORTED'),
                'database_error' => Text::_('COM_XDECAROPEOPLE_IMPORT_CODE_DATABASE_ERROR'),
                'duplicate_conflict' => Text::_('COM_XDECAROPEOPLE_IMPORT_CODE_DUPLICATE_CONFLICT'),
            ],
            'targets' => [
                ['key' => 'first_name', 'label' => Text::_('COM_XDECAROPEOPLE_FIELD_FIRST_NAME'), 'required' => true],
                ['key' => 'last_name', 'label' => Text::_('COM_XDECAROPEOPLE_FIELD_LAST_NAME'), 'required' => true],
                ['key' => 'tax_identifier', 'label' => Text::_('COM_XDECAROPEOPLE_FIELD_TAX_IDENTIFIER'), 'required' => false],
                ['key' => 'birth_date', 'label' => Text::_('COM_XDECAROPEOPLE_FIELD_BIRTH_DATE'), 'required' => false],
                ['key' => 'sex', 'label' => Text::_('COM_XDECAROPEOPLE_FIELD_SEX'), 'required' => false],
                ['key' => 'birth_place', 'label' => Text::_('COM_XDECAROPEOPLE_FIELD_BIRTH_PLACE'), 'required' => false],
                ['key' => 'birth_region', 'label' => Text::_('COM_XDECAROPEOPLE_FIELD_BIRTH_REGION'), 'required' => false],
                ['key' => 'address_line', 'label' => Text::_('COM_XDECAROPEOPLE_FIELD_ADDRESS'), 'required' => false],
                ['key' => 'address_number', 'label' => Text::_('COM_XDECAROPEOPLE_FIELD_ADDRESS_NUMBER'), 'required' => false],
                ['key' => 'postal_code', 'label' => Text::_('COM_XDECAROPEOPLE_FIELD_POSTAL_CODE'), 'required' => false],
                ['key' => 'city', 'label' => Text::_('COM_XDECAROPEOPLE_FIELD_CITY'), 'required' => false],
                ['key' => 'region', 'label' => Text::_('COM_XDECAROPEOPLE_FIELD_RESIDENCE_REGION'), 'required' => false],
                ['key' => 'country_code', 'label' => Text::_('COM_XDECAROPEOPLE_FIELD_COUNTRY'), 'required' => false],
                ['key' => 'phone', 'label' => Text::_('COM_XDECAROPEOPLE_FIELD_PHONE'), 'required' => false],
                ['key' => 'email', 'label' => Text::_('JGLOBAL_EMAIL'), 'required' => false],
            ],
        ]);

        ToolbarHelper::title(Text::_('COM_XDECAROPEOPLE_IMPORT_TITLE'), 'upload');
        ToolbarHelper::back(Text::_('JTOOLBAR_BACK'), Route::_('index.php?option=com_xdecaropeople&view=people', false));

        parent::display($tpl);
    }
}
