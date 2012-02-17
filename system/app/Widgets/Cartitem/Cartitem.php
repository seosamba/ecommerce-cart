<?php

class Widgets_Cartitem_Cartitem extends Widgets_Abstract{

	protected $_websiteHelper  = null;

	protected $_cartContent    = array();

	protected $_translator     = null;

	protected $_shoppingConfig = array();

	public function setOptions($options) {
		$this->_options = $options;
		return $this;
	}

	protected function  _init() {
		parent::_init();
		$this->_view = new Zend_View(array(
			'scriptPath' => __DIR__ . '/views/'
		));
		$this->_websiteHelper    = Zend_Controller_Action_HelperBroker::getStaticHelper('website');
		$this->_view->websiteUrl = $this->_websiteHelper->getUrl();
		$this->_translator       = Zend_Registry::get('Zend_Translate');
		$this->_cartContent      = Tools_ShoppingCart::getInstance()->getContent();
		$this->_shoppingConfig   = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
	}

	protected function _load() {
		if(!isset($this->_options[0]) || !isset($this->_options[1])) {
			return '';
		}
		$itemOption    = $this->_options[0];
		$itemStorageId = $this->_options[1];
		$rendererName  = '_render' . ucfirst($itemOption);
		if(method_exists($this, $rendererName)) {
			return $this->$rendererName($itemStorageId);
		}
		throw new Exceptions_SeotoasterException('Can not render <strong>' . $rendererName . '</strong>');
	}

	protected function _renderName($sid) {
   	    return '<span class="toastercart-item-name">' . $this->_cartContent[$sid]['name'] . '</span>';
	}

	protected function _renderUnitprice($sid) {
		$this->_view->sid         = $sid;
		$this->_view->price       = $this->_cartContent[$sid]['price'];
		$this->_view->priceOption = 'unitprice';
		return $this->_view->render('commonprice.phtml');
	}

	protected function _renderPrice($sid) {
		$this->_view->sid         = $sid;
		$this->_view->price       = $this->_cartContent[$sid]['price'] * $this->_cartContent[$sid]['qty'];
		$this->_view->priceOption = 'price';
		return $this->_view->render('commonprice.phtml');
	}

	protected function _renderQty($sid) {
		return '<input type="number" class="product-qty" min="0" data-sid="' . $sid . '" data-pid="' . $this->_cartContent[$sid]['id'] . '" value="' . $this->_cartContent[$sid]['qty'] . '" />';
	}

	protected function _renderPhoto($sid) {
		return '<img src="/media/' . str_replace('/', '/product/', $this->_cartContent[$sid]['photo']) . '" alt="' . $this->_cartContent[$sid]['name'] . '">';
	}

	protected function _renderDescription($sid) {
		return '<span class="toastercart-item-description">' . Tools_Text_Tools::cutText($this->_cartContent[$sid]['description'], 100) . '</span>';
	}

	protected function _renderRemove($sid) {
		return '<a href="javascript:;" class="remove-item" data-sid="' . $sid . '" title="' . $this->_translator->translate('remove ') . $this->_cartContent[$sid]['name'] . $this->_translator->translate(' from the cart') . '"><img src="' . $this->_websiteHelper->getUrl() . 'plugins/cart/web/images/trash.png" alt="' . $this->_translator->translate('Remove') . '"></a>';
	}

	protected function _renderOptions($sid) {
		$this->_view->cartItem   = $this->_cartContent[$sid];
		$this->_view->weightSign = $this->_shoppingConfig['weightUnit'];
		return $this->_view->render('options.phtml');
	}

}
