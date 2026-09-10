<?php

namespace xdecaro\Component\People\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;

final class PersonController extends FormController
{
    protected $option = 'com_xdecaropeople';
    protected $view_item = 'person';
    protected $view_list = 'people';
}
