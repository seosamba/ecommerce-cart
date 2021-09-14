<?php
/**
 * MAGICSPACE: hideremoveproduct
 * {hideremoveproduct}{/hideremoveproduct} - used to hide remove product column on the checkout
 */
class MagicSpaces_Hideremoveproduct_Hideremoveproduct extends Tools_MagicSpaces_Abstract {

	public function __construct($name = '', $content = '', $toasterData = array()) {
		parent::__construct($name, $content, $toasterData);
		$this->_sessionHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('session');
	}

	protected function _run() {
        $lockCartEdit = false;
        $checkoutSession = new Zend_Session_Namespace(Cart::SESSION_NAMESPACE);
        $front = Zend_Controller_Front::getInstance();
        $request = $front->getRequest();
        if ($request->has('step')) {
            $step = strtolower($request->getParam('step'));
            if ($request->isGet() && (empty($checkoutSession->returnAllowed)
                    || !in_array($step, $checkoutSession->returnAllowed))
            ) {
                $lockCartEdit = false;
            } else {
                $lockCartEdit = true;
            }
        }

        if ($lockCartEdit === true) {
            return '';
        }

        return $this->_spaceContent;

	}
}
