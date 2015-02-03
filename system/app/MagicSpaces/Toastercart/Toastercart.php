<?php
/**
 * MAGICSPACE: toastercart
 *
 * {toastercart}{/toastercart} - used to specify a place where single cart items will be displayed
 * Inside this magic space you can put cartitem widgets
 *
 * Class MagicSpaces_Toastercart_Toastercart
 */

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
		$this->_content  = $this->_findPageTemplateContent();
		$spaceContent    = $this->_parse();
		$this->_content  = $tmpPageContent;
        if(!$spaceContent) {
            $spaceContent = $this->_parse();
        }

		$cartStorage = Tools_ShoppingCart::getInstance();
		$cartContent = $cartStorage->getContent();
		$cartSize    = sizeof($cartContent);

		if($cartSize) {
			foreach($cartContent as $sid => $cartItem) {
				$content .= preg_replace_callback('~{\$cartitem:(.+)}~uU', function($matches) use($sid) {
					$options = array_merge(array($sid), explode(':', $matches[1]));
					return Tools_Factory_WidgetFactory::createWidget('Cartitem', $options)->render();
				}, $spaceContent);
			}
		}
		$this->_view->websiteUrl = Zend_Controller_Action_HelperBroker::getExistingHelper('website')->getUrl();
		$this->_view->cartContent = $content;
		return $this->_view->render('toastercart.phtml');
	}

	protected function _findPageTemplateContent() {
		$page     = Application_Model_Mappers_PageMapper::getInstance()->find($this->_toasterData['id']);
		$template = Application_Model_Mappers_TemplateMapper::getInstance()->find($page->getTemplateId());
		unset($page);
        if(!$template instanceof Application_Model_Models_Template) {
			return false;
		}
		return $template->getContent();
	}

	protected function _buildItem($option, $sid) {
		$cartItemWidget = Tools_Factory_WidgetFactory::createWidget('Cartitem', array($option, $sid));
		return $cartItemWidget->render();
	}
}