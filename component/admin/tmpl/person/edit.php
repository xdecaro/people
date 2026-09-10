<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');
?>
<form
    action="<?php echo Route::_('index.php?option=com_xdecaropeople&view=person&layout=edit&id=' . (int) ($this->item->id ?? 0)); ?>"
    method="post"
    name="adminForm"
    id="adminForm"
    class="form-validate"
>
    <div class="xdecaro-scope">
        <?php
        echo HTMLHelper::_('uitab.startTabSet', 'personTabs', ['active' => 'identity']);
        echo HTMLHelper::_('uitab.addTab', 'personTabs', 'identity', Text::_('COM_XDECAROPEOPLE_FIELDSET_IDENTITY'));
        echo $this->form->renderFieldset('identity');
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'personTabs', 'contacts', Text::_('COM_XDECAROPEOPLE_FIELDSET_CONTACTS'));
        echo $this->form->renderFieldset('contacts');
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'personTabs', 'publishing', Text::_('JGLOBAL_FIELDSET_PUBLISHING'));
        echo $this->form->renderFieldset('publishing');
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.endTabSet');
        ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
