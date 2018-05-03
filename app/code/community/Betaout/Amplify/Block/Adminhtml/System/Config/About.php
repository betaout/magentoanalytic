<?php

class Betaout_Amplify_Block_Adminhtml_System_Config_About
    extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{

    protected $_module = 'betaout_amplify';

    protected $_name = 'About Betaout Get Amplify';
     public function _construct()
    {
        parent::_construct();
        $this->setTemplate('betaout_amplify/amplify.phtml');
    }
    
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->toHtml();
    }
}