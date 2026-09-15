<?php

namespace xdecaro\Component\People\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\AdminController;

final class PeopleController extends AdminController
{
    protected $option = 'com_xdecaropeople';
    protected $view_list = 'people';

    public function getModel($name = 'Person', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }
}
