<?php
/**
 * MAGICSPACE: paymentgateways
 * {paymentgateways}{/paymentgateways} - used to specify a place where payment gateways will be displayed at the checkout
 * It provides a mechanism to display payment gateways at the latest stage when taxes and shipping was applied to final amount of the purchase
 */
class MagicSpaces_Paymentgateways_Paymentgateways extends Tools_MagicSpaces_Abstract {

    protected $_parseBefore = true;

	public function __construct($name = '', $content = '', $toasterData = array()) {
		parent::__construct($name, $content, $toasterData);
		$this->_sessionHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('session');
	}

	protected function _run() {
        $shoppingCart = Tools_ShoppingCart::getInstance();
        $total = $shoppingCart->getTotal();
        if (empty($total)) {
            $this->_sessionHelper->paymentZoneTmpl = null;
            return '';
        }

	    $tmp = $this->_content;
		$this->_content = $this->_findCheckoutTemplateContent();
		$paymentZoneTemplate = $this->_parse();
		$this->_sessionHelper->paymentZoneTmpl = $paymentZoneTemplate;
		$this->_content = $tmp;
		return '';
	}

	private function _findCheckoutTemplateContent() {
		$checkoutPage     = Tools_Misc::getCheckoutPage();
		$checkoutTemplate = Application_Model_Mappers_TemplateMapper::getInstance()->find($checkoutPage->getTemplateId());
		if(!$checkoutTemplate instanceof Application_Model_Models_Template) {
			return false;
		}
		return $checkoutTemplate->getContent();
	}
}
