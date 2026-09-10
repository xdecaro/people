<?php

namespace xdecaro\Component\People\Administrator\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use xdecaro\Component\People\Administrator\Service\CountryMetadata;

final class CountryField extends ListField
{
    protected $type = 'Country';

    protected function getOptions()
    {
        $options = parent::getOptions();
        $codeType = strtolower((string) ($this->element['code'] ?? 'alpha2'));
        $language = Factory::getApplication()->getLanguage();
        $languageTag = $language ? $language->getTag() : 'en-GB';

        foreach (CountryMetadata::countries($languageTag) as $country) {
            $code = $codeType === 'alpha3' ? $country['alpha3'] : $country['alpha2'];
            $options[] = HTMLHelper::_('select.option', $code, $country['name'] . ' — ' . $code);
        }

        return $options;
    }
}
