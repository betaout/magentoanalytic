<?php

class Betaout_Amplify_Block_Adminhtml_System_Config_Logo
    extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{

    /**
     * Description for protected
     *
     * @var string
     * @access protected
     */
    protected $_module = 'betaout_amplify';

    /**
     * Description for protected
     *
     * @var string
     * @access protected
     */
    protected $_name = 'About Betaout Get Amplify';
     public function _construct()
    {
        parent::_construct();
        $this->setTemplate('betaout_amplify/logo.phtml');
    }
    
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->toHtml();
    }

}
