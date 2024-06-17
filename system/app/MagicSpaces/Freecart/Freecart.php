<?php

/**
 * MAGICSPACE: freecart
 * {freecart}{/freecart} - display content in case cart has atleast one product and cart total is zero
 */
class MagicSpaces_Freecart_Freecart extends Tools_MagicSpaces_Abstract
{


    public function __construct($name = '', $content = '', $toasterData = array())
    {
        parent::__construct($name, $content, $toasterData);
        $this->_sessionHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('session');
    }

    protected function _run()
    {
        $tmp = $this->_content;
        $this->_content = $this->_findCheckoutTemplateContent();
        $paymentZoneTemplate = $this->_parse();
        $this->_sessionHelper->paymentZoneFreeTmpl = $paymentZoneTemplate;
        $this->_content = $tmp;
        return '';
    }

    private function _findCheckoutTemplateContent()
    {
        $checkoutPage = Tools_Misc::getCheckoutPage();
        $checkoutTemplate = Application_Model_Mappers_TemplateMapper::getInstance()->find($checkoutPage->getTemplateId());
        if (!$checkoutTemplate instanceof Application_Model_Models_Template) {
            return false;
        }
        return $checkoutTemplate->getContent();
    }
}
