<?php
/**
 * Shopping cart E-commerce plugin for SEOTOASTER 2.0
 *
 * This plugin is using E-commerce plugin API
 * Depends on shopping (e-commerce) plugin
 *
 */
class Cart extends Tools_Cart_Cart {

	const DEFAULT_LOCALE        = 'en_US';

	const DEFAULT_WEIGHT_UNIT   = 'kg';

	const DEFAULT_CURRENCY_NAME = 'USD';

	/**
	 * Shopping cart main storage.
	 *
	 * @var Tools_ShoppingCart
	 */
	protected $_cartStorage    = null;

	/**
	 * Product mapper from the shopping plugin
	 *
	 * @var Models_Mapper_ProductMapper
	 */
	protected $_productMapper  = null;

	/**
	 * Shopping config data
	 *
	 * @var array
	 */
	protected $_shoppingConfig = array();

	/**
	 * JSON helper for sending well-formated json response
	 *
	 * @var Zend_Controller_Action_Helper_Json
	 */
	protected $_jsonHelper     = null;

	/**
	 * Currency object to keep valid number formats
	 *
	 * @var Zend_Currency
	 */
	protected $_currency       = null;

	protected function _init() {
		$this->_cartStorage      = Tools_ShoppingCart::getInstance();
		$this->_productMapper    = Models_Mapper_ProductMapper::getInstance();
		$this->_shoppingConfig   = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
		$this->_jsonHelper       = Zend_Controller_Action_HelperBroker::getStaticHelper('json');
		$this->_view->weightSign = isset($this->_shoppingConfig['weightUnit']) ? $this->_shoppingConfig['weightUnit'] : 'kg';
		$this->_initCurrency();
		$this->_view->setScriptPath(dirname(__FILE__) . '/system/views/');
	}

	private function _initCurrency() {
		$this->_currency = new Zend_Currency();
		$this->_currency->setFormat(array(
			'display' => Zend_Currency::NO_SYMBOL
		));
		$correctCurrency                = isset($this->_shoppingConfig['currency']) ? $this->_shoppingConfig['currency'] : self::DEFAULT_CURRENCY_NAME;
		$this->_view->currencySymbol    = $this->_currency->getSymbol($correctCurrency, self::DEFAULT_LOCALE);
		$this->_view->currencyShortName = $correctCurrency;
	}

	protected function _getCheckoutPage() {
		$checkoutPage = Tools_Page_Tools::getCheckoutPage();
		if(!$checkoutPage instanceof Application_Model_Models_Page) {
			if(Tools_Security_Acl::isAllowed(Tools_Security_Acl::RESOURCE_ADMINPANEL)) {
				throw new Exceptions_SeotoasterPluginException('Error rendering cart. Please select a checkout page');
			}
			throw new Exceptions_SeotoasterPluginException('<!-- Error rendering cart. Please select a checkout page -->');
		}
		$this->_view->checkoutPage = $checkoutPage;
		return $checkoutPage;
	}

	public function run($requestedParams = array()) {
		$dispatchersResult = parent::run($requestedParams);
		if($dispatchersResult) {
			return $dispatchersResult;
		}
	}

	public function cartAction() {
		$requestMethod = $this->_request->getMethod();
		$data          = array();
		switch($requestMethod) {
			case 'GET':
				if(isset($this->_requestedParams['sid']) && $this->_requestedParams['sid']) {

				}
				$data = $this->_getCart();
			break;
			case 'POST':
				$data = $this->_addToCart();
			break;
			case 'PUT':
				$this->_getParamsFromRawHttp();
				$data = $this->_updateCart();
			break;
			case 'DELETE':
				$this->_getParamsFromRawHttp();
				$this->_removeFromCart();
			break;
			default:
			break;
		}
		return $this->_jsonHelper->direct($data);
	}

	public function summaryAction() {
		if(!$this->_request->isPost()) {
			throw new Exceptions_SeotoasterPluginException('Direct access not allowed');
		}
		$this->_responseHelper->success($this->_makeOptionSummary());
	}

	public function cartcontentAction() {
		if(!$this->_request->isPost()) {
			throw new Exceptions_SeotoasterPluginException('Direct access not allowed');
		}
		$this->_responseHelper->success($this->_makeOptionCart());
	}

	protected function _addToCart() {
		if(!$this->_request->isPost()) {
			throw new Exceptions_SeotoasterPluginException('Direct access not allowed');
		}
		$productId = $this->_requestedParams['pid'];
		$options   = $this->_requestedParams['options'];
		$qty       = isset($this->_requestedParams['qty']) ? $this->_requestedParams['qty'] : 1;
		if(!$productId) {
			throw new Exceptions_SeotoasterPluginException('Can\'t add to cart: product not defined');
		}
		$product = $this->_productMapper->find($productId);
		$options = ($options) ? $this->_parseProductOtions($productId, $options) : array();
		$this->_cartStorage->add($product, $options, $qty);
		$this->_saveCartSession();
		return true;
	}

	protected function _updateCart() {
		if(!$this->_request->isPut()) {
			throw new Exceptions_SeotoasterPluginException('Direct access not allowed');
		}
		$storageId = $this->_requestedParams['sid'];
		$newQty    = $this->_requestedParams['qty'];
		if($this->_cartStorage->updateQty($storageId, $newQty)) {
			$this->_saveCartSession();
			return $this->_cartStorage->findBySid($storageId);
		}
	}

	protected function _removeFromCart() {
		if(!$this->_request->isDelete()) {
			throw new Exceptions_SeotoasterPluginException('Direct access not allowed');
		}
		if($this->_cartStorage->remove($this->_requestedParams['sid'])) {
			$this->_saveCartSession();
			$this->_responseHelper->success($this->_translator->translate('Removed.'));
		}
		$this->_responseHelper->fail($this->_translator->translate('Cant remove product.'));
	}

	protected function _getCart() {
		return array_values($this->_cartStorage->getContent());
	}

	protected function _makeOptionAddtocart() {
		$productId = (isset($this->_options[1]) && intval($this->_options[1])) ? $this->_options[1] : 0;
		if(!$productId) {
			$product = $this->_productMapper->findByPageId($this->_seotoasterData['id']);
			if($product instanceof Models_Model_Product) {
				$productId = $product->getId();
				unset($product);
			}
		}
		$checkOutPage                 = $this->_getCheckoutPage();
		$this->_view->checkOutPageUrl = $checkOutPage->getUrl();
		unset($checkOutPage);
		$this->_view->productId = $productId;
   	    return $this->_view->render('addtocart.phtml');
	}

	protected function _makeOptionCartblock() {
		$cartContent = $this->_cartStorage->getContent();
		$itemsCount  = 0;
		if(is_array($cartContent) && !empty($cartContent)) {
			array_walk($cartContent, function($cartItem) use(&$itemsCount) {
				$itemsCount += $cartItem['qty'];
			});
		}
		$this->_view->itemsCount = $itemsCount;
		$this->_view->summary    = $this->_cartStorage->calculate();
		$this->_getCheckoutPage();
		return $this->_view->render('cartblock.phtml');
	}

	protected function _makeOptionCart() {
		$this->_view->showTaxCol   = isset($this->_shoppingConfig['showPriceIncTax']) ? $this->_shoppingConfig['showPriceIncTax'] : 0;
		$this->_view->config       = $this->_shoppingConfig;
		$this->_view->cartContent  = $this->_cartStorage->getContent();
		return $this->_view->render('cart.phtml');
	}

	protected function _makeOptionCheckout() {
		$this->_view->shippingForm = ($this->_shoppingConfig['shippingType'] != 'pickup') ? new Forms_Shipping() : null;
		return $this->_view->render('checkout.phtml');
	}

	protected function _makeOptionSummary() {
		$this->_view->summary = $this->_cartStorage->calculate();
		return $this->_view->render('summary.phtml');
	}

	protected function _saveCartSession() {
		$cartSession = new Cart_Models_Model_CartSession();
		$cartSession->setId(Zend_Session::getId())
			->setCartContent(serialize($this->_cartStorage->getContent()))
			->setIpAddress($_SERVER['REMOTE_ADDR']);
        return Cart_Models_Mapper_CartSessionMapper::getInstance()->save($cartSession);
	}

	protected function _parseProductOtions($productId, $options) {
		parse_str($options, $options);
		if(is_array($options)) {
			foreach($options as $key => $option) {
				$options[str_replace('product-' . $productId . '-option-', '', $key)] = $option;
				unset($options[$key]);
			}
		}
		return $options;
	}

	protected function _getParamsFromRawHttp() {
		parse_str($this->_request->getRawBody(), $this->_requestedParams);
	}
}
