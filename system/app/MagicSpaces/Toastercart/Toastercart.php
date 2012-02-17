<?php

class MagicSpaces_Toastercart_Toastercart extends Tools_MagicSpaces_Abstract {

	protected $_parser = null;

	protected $_view   = null;

	protected function _init() {
		$this->_view = new Zend_View(array(
			'scriptPath' => __DIR__  . '/views/'
		));
		$this->_view->addHelperPath('ZendX/JQuery/View/Helper/', 'ZendX_JQuery_View_Helper');
	}

	protected function _run() {
		$content         = '';
		$tmpPageContent  = $this->_content;
		$this->_content  = $this->_findCheckoutTemplateContent();
		$spaceContent    = $this->_parse();
		$this->_content  = $tmpPageContent;

		$cartStorage = Tools_ShoppingCart::getInstance();
		$cartContent = $cartStorage->getContent();
		$cartSize    = sizeof($cartContent);
		//var_dump($spaceContent);
		//var_dump($cartContent);

		if($cartSize) {
			foreach($cartContent as $sid => $cartItem) {
				$content .= preg_replace_callback('~{\$cartitem:(.+)}~', function($matches) use($sid) {
					return Tools_Factory_WidgetFactory::createWidget('Cartitem', array($matches[1], $sid))->render();
				}, $spaceContent);
			}
		}
		$this->_view->cartContent = $content;
		return ($content) ? $this->_view->render('toastercart.phtml') : 'Cart is empty';
	}

	private function _findCheckoutTemplateContent() {
		$checkoutPage     = Tools_Page_Tools::getCheckoutPage();
		$checkoutTemplate = Application_Model_Mappers_TemplateMapper::getInstance()->find($checkoutPage->getTemplateId());
		if(!$checkoutTemplate instanceof Application_Model_Models_Template) {
			return false;
		}
		return $checkoutTemplate->getContent();
	}

	protected function _buildItem($option, $sid) {
		$cartItemWidget = Tools_Factory_WidgetFactory::createWidget('Cartitem', array($option, $sid));
		return $cartItemWidget->render();
	}
}