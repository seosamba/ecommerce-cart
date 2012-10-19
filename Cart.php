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

	const CART_WIDGET_JS_NS   = 'CartCheckout.';

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

	protected $_sessionHelper  = null;

	protected function _init() {
		$this->_cartStorage      = Tools_ShoppingCart::getInstance();
		$this->_productMapper    = Models_Mapper_ProductMapper::getInstance();
		$this->_shoppingConfig   = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
		$this->_sessionHelper    = Zend_Controller_Action_HelperBroker::getStaticHelper('session');
		$this->_jsonHelper       = Zend_Controller_Action_HelperBroker::getStaticHelper('json');
		$this->_view->weightSign = isset($this->_shoppingConfig['weightUnit']) ? $this->_shoppingConfig['weightUnit'] : 'kg';
		$this->_initCurrency();
		$this->_view->setScriptPath(dirname(__FILE__) . '/system/views/');
	}

	private function _initCurrency() {
		if (!Zend_Registry::isRegistered('Zend_Currency')){
			$this->_currency = new Zend_Currency(self::DEFAULT_LOCALE);
		} else {
			$this->_currency = Zend_Registry::get('Zend_Currency');
		}
		$correctCurrency                = isset($this->_shoppingConfig['currency']) ? $this->_shoppingConfig['currency'] : self::DEFAULT_CURRENCY_NAME;
		$this->_view->currencySymbol    = $this->_currency->getSymbol($correctCurrency, self::DEFAULT_LOCALE);
		$this->_view->currencyShortName = $correctCurrency;
	}

	protected function _getCheckoutPage() {
		$cacheHelper = Zend_Controller_Action_HelperBroker::getExistingHelper('cache');
		if (null === ($checkoutPage = $cacheHelper->load(Shopping::CHECKOUT_PAGE_CACHE_ID, Shopping::CACHE_PREFIX))){
			$checkoutPage = Tools_Misc::getCheckoutPage();
			if(!$checkoutPage instanceof Application_Model_Models_Page) {
				if(Tools_Security_Acl::isAllowed(Tools_Security_Acl::RESOURCE_ADMINPANEL)) {
					throw new Exceptions_SeotoasterPluginException('Error rendering cart. Please select a checkout page');
				}
				throw new Exceptions_SeotoasterPluginException('<!-- Error rendering cart. Please select a checkout page -->');
			}
			$cacheHelper->save(Shopping::CHECKOUT_PAGE_CACHE_ID, $checkoutPage, 'store_', array(), Helpers_Action_Cache::CACHE_SHORT);
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
		$this->_responseHelper->success($this->_makeOptionCartsummary());
	}

	public function cartcontentAction() {
		if(!$this->_request->isPost()) {
			throw new Exceptions_SeotoasterPluginException('Direct access not allowed');
		}
		$sid  = $this->_request->getParam('sid');
		$data = array(
			'price'  => Tools_Factory_WidgetFactory::createWidget('Cartitem', array($sid, 'price'))->render(),
			'weight' => Tools_Factory_WidgetFactory::createWidget('Cartitem', array($sid, 'weight'))->render()
		);
		$this->_responseHelper->success($data);
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
		$options = ($options) ? $this->_parseProductOtions($productId, $options) : $this->_getDefaultProductOptions($product);
		$this->_cartStorage->add($product, $options, $qty);
		return true;
	}

	protected function _getDefaultProductOptions(Models_Model_Product $product) {
		$productOptions = $product->getDefaultOptions();
		if(!is_array($productOptions) || empty($productOptions)) {
			return array();
		}
		foreach($productOptions as $key => $option) {
			if(isset($option['selection']) && is_array($option['selection']) && !empty($option['selection'])) {
				$selections = $option['selection'];
				foreach($selections as $selectionData) {
					if(!$selectionData['isDefault']) {
						continue;
					}
		 	        return array(
				        $selectionData['option_id'] => $selectionData['id']
			        );
				}
			} else {
				return array();
			}
		}
	}

	protected function _updateCart() {
		if(!$this->_request->isPut()) {
			throw new Exceptions_SeotoasterPluginException('Direct access not allowed');
		}
		$storageId = $this->_requestedParams['sid'];
		$newQty    = $this->_requestedParams['qty'];
		if($this->_cartStorage->updateQty($storageId, $newQty)) {
			return $this->_cartStorage->findBySid($storageId);
		}
	}

	protected function _removeFromCart() {
		if(!$this->_request->isDelete()) {
			throw new Exceptions_SeotoasterPluginException('Direct access not allowed');
		}
		if($this->_cartStorage->remove($this->_requestedParams['sid'])) {
			$this->_responseHelper->success($this->_translator->translate('Removed.'));
		}
		$this->_responseHelper->fail($this->_translator->translate('Cant remove product.'));
	}

	protected function _getCart() {
		return array_values($this->_cartStorage->getContent());
	}

	protected function _makeOptionAddtocart() {
        if(!isset($this->_options[1]) || !intval($this->_options[1])) {
            throw new Exceptions_SeotoasterPluginException('Product id is missing!');
        }
		$this->_view->checkOutPageUrl = $this->_getCheckoutPage()->getUrl();
		$this->_view->productId       = $this->_options[1];
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
		if (count(Tools_ShoppingCart::getInstance()->getContent()) === 0 ){
			return $this->_view->render('checkout/keepshopping.phtml');
		}

        //if user is guest we will show him to sign-up form
		//otherwise address form we show him shipping options
		if(Tools_ShoppingCart::getInstance()->getCustomer()->getId()) {
			$this->_view->content = $this->_renderShippingOptions();
		} else {
			$this->_view->content = $this->_renderSignupForm();
		}

    	return $this->_view->render('checkout/landing.phtml');
	}


	protected function _makeOptionCartsummary() {
		$this->_view->summary = $this->_cartStorage->calculate();
		$this->_view->taxIncPrice = (bool)$this->_shoppingConfig['showPriceIncTax'];
		return $this->_view->render('cartsummary.phtml');
	}

    protected function _makeOptionBuyersummary() {
        $this->_view->customer = Tools_ShoppingCart::getInstance()->getCustomer()->toArray();
		return $this->_view->render('buyersummary.phtml');
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

	public function checkoutAction(){
		$step = filter_var($this->_request->getParam('check'), FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
		$methodName = '_checkoutApply'.ucfirst(strtolower($step));
		if (method_exists($this, $methodName)){
			return $this->$methodName();
		} else {
			$this->_response->clearAllHeaders()->clearBody();
			return $this->_response->setHttpResponseCode(Api_Service_Abstract::REST_STATUS_BAD_REQUEST)
				->sendResponse();
		}
	}

	private function _checkoutApplyAddress(){
		$form = new Forms_Checkout_Address();
		$addressType = Models_Model_Customer::ADDRESS_TYPE_SHIPPING;
		$headers = array('Content-type' => 'application/json');

		if ($form->isValid($this->_request->getParams())){
			$shoppingCart = Tools_ShoppingCart::getInstance();
			$shippingAddress   = $form->getValues();
			//$customer   = Shopping::processCustomer($shippingAddress);
            $customer  = Tools_ShoppingCart::getInstance()->getCustomer();
			$addressId = Models_Mapper_CustomerMapper::getInstance()->addAddress($customer, $shippingAddress, $addressType);
			$shoppingCart->setAddressKey($addressType, $addressId)
				->save()
				->saveCartSession($customer);

			echo json_encode($this->_checkShippingPlugins($shippingAddress));
		} else {
			return $this->_response->setHttpResponseCode(Api_Service_Abstract::REST_STATUS_BAD_REQUEST)
			        ->setBody(json_encode($form->getMessages()))
			        ->sendResponse();
		}
	}

	private function _checkoutApplyShipper() {
		$shipper = filter_var($this->_request->getParam('shipper'), FILTER_SANITIZE_STRING);
		if ($shipper){
			list($shipper, $index) = explode('::', $shipper);
			if ($shipper === Shopping::SHIPPING_PICKUP){
				$service = array(
					'service'   => Shopping::SHIPPING_PICKUP,
					'type'      => '',
					'price'     => 0
				);
			} else {
				$vault = $this->_sessionHelper->shippingRatesVault;
				if (is_array($vault) && isset($vault[$shipper])){
					if (isset($vault[$shipper][$index])){
						$service = array(
							'service'   => $shipper,
							'type'      => $vault[$shipper][$index]['type'],
							'price'     => $vault[$shipper][$index]['price']
						);
					}
				}
			}
			if (isset($service)){
				Tools_ShoppingCart::getInstance()->setShippingData($service)->save()->saveCartSession(null);
				return $this->_response->clearAllHeaders()->setBody($this->_renderPaymentZone())->sendResponse();
			}
		}
		return $this->_response->setHttpResponseCode(Api_Service_Abstract::REST_STATUS_BAD_REQUEST)->sendResponse();
	}

    private function _checkoutApplySignup() {
        $form = new Forms_Signup();
        if($form->isValid($this->_request->getParams())) {
            $customer    = Shopping::processCustomer($form->getValues());
            if($customer) {
	            Tools_ShoppingCart::getInstance()->saveCartSession($customer);
            }
	        echo $this->_renderShippingOptions();
        } else {
	        return $this->_response->setHttpResponseCode(Api_Service_Abstract::REST_STATUS_BAD_REQUEST)
			        ->setBody(json_encode($form->getMessages()))
			        ->sendResponse();
        }
    }

	private function _checkoutApplyPickup() {
		$form = new Forms_Checkout_Pickup();
		if ($form->isValid($this->_request->getPost())){
			$customer = Tools_ShoppingCart::getInstance()->getCustomer();
			$addressId = Models_Mapper_CustomerMapper::getInstance()->addAddress($customer, $form->getValues(), Models_Model_Customer::ADDRESS_TYPE_SHIPPING);
			Tools_ShoppingCart::getInstance()->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING, $addressId)
				->setShippingData(array(
					'service'   => Shopping::SHIPPING_PICKUP,
					'type'      => null,
					'price'     => 0
				))
				->save()
				->saveCartSession($customer);

			echo $this->_renderPaymentZone();
		} else {
			return $this->_response->setHttpResponseCode(Api_Service_Abstract::REST_STATUS_BAD_REQUEST)
					->setBody(json_encode($form->getMessages()))
					->sendResponse();
        }
	}

	protected function _renderSignupForm() {
		$form = new Forms_Signup();
		$form->setAction(trim($this->_websiteUrl, '/') . $this->_view->url(array(
            'run' => 'checkout',
            'name' => strtolower(__CLASS__)
        ), 'pluginroute'));

        $this->_view->signupForm        = $form;
		$this->_view->redirectUrl       = $this->_seotoasterData['url'];

		$flashMessenger = Zend_Controller_Action_HelperBroker::getStaticHelper('flashMessenger');
		$msg = $flashMessenger->getMessages();
		$this->_view->loginError = false;

		if (!empty($msg) && (in_array('There is no user with such login and password.', $msg) || in_array('Login should be a valid email address', $msg))){
			$this->_view->loginError = true;
		}

		return $this->_view->render('checkout/signup.phtml');
	}

	protected function _renderShippingOptions(){
		$pickup = Models_Mapper_ShippingConfigMapper::getInstance()->find(Shopping::SHIPPING_PICKUP);

		$shippers = Models_Mapper_ShippingConfigMapper::getInstance()->fetchByStatus(Models_Mapper_ShippingConfigMapper::STATUS_ENABLED);

		if ((is_null($pickup) || (bool)$pickup['enabled'] == false) && is_null($shippers)){
			return $this->_renderPaymentZone();
		} else {

		}

		$shippers = array_filter($shippers, function($shipper){
			return !in_array($shipper['name'], array(
				Shopping::SHIPPING_FREESHIPPING,
				Shopping::SHIPPING_MARKUP,
				Shopping::SHIPPING_PICKUP
			));
		});

		if ($pickup && (bool)$pickup['enabled']){
			$this->_view->pickupForm = new Forms_Checkout_Pickup();
			$this->_view->pickupForm->setAction(trim($this->_websiteUrl, '/') . $this->_view->url(array(
	            'run' => 'checkout',
	            'name' => strtolower(__CLASS__)
	        ), 'pluginroute'));
		}

		if (!empty($shippers)){
			$shippingForm     = new Forms_Checkout_Address();
			$this->_view->shippingForm = $shippingForm;
			$this->_view->shippingForm->setAction(trim($this->_websiteUrl, '/') . $this->_view->url(array(
	            'run' => 'checkout',
	            'name' => strtolower(__CLASS__)
	        ), 'pluginroute'));
		}

		//. preparing user info for forms
		$addrType = Models_Model_Customer::ADDRESS_TYPE_SHIPPING;
		if (null !== ($uniqKey = Tools_ShoppingCart::getInstance()->getAddressKey($addrType))){
            $customerAddress = Tools_ShoppingCart::getAddressById($uniqKey);
        } else {
            $customer = Tools_ShoppingCart::getInstance()->getCustomer();
            if (null === ($customerAddress = $customer->getDefaultAddress($addrType)) && $customer->getId()){
                $name = explode(' ', $customer->getFullName());
                $customerAddress = array(
	                'firstname' => $name[0],
	                'lastname'  => $name[1],
	                'email'     => $customer->getEmail(),
	                'country' => $this->_shoppingConfig['country'],
                    'state'   => $this->_shoppingConfig['state']
                );
            }
        }
        if (empty($customerAddress)) {
            $customerAddress = array(
                'country' => $this->_shoppingConfig['country'],
                'state'   => $this->_shoppingConfig['state']
            );
        }
		$this->_view->customerAddress = $customerAddress;

		return $this->_view->render('checkout/shipping_options.phtml');
	}

	protected function _renderPaymentZone() {
		$paymentZoneTmpl = isset($this->_sessionHelper->paymentZoneTmpl) ? $this->_sessionHelper->paymentZoneTmpl : null;
		if ($paymentZoneTmpl !== null) {
			$paymentZoneTmpl = '<h3>{$header:payment-zone}</h3>'.$paymentZoneTmpl; 
            $themeData = Zend_Registry::get('theme');
			$extConfig = Zend_Registry::get('extConfig');
			$parserOptions = array(
				'websiteUrl'   => $this->_websiteHelper->getUrl(),
				'websitePath'  => $this->_websiteHelper->getPath(),
				'currentTheme' => $extConfig['currentTheme'],
				'themePath'    => $themeData['path'],
			);
			$parser = new Tools_Content_Parser($paymentZoneTmpl, Tools_Misc::getCheckoutPage()->toArray(), $parserOptions);
			return $parser->parse();
		}
	}

	protected function _checkShippingPlugins($shippingAddress){
		$freeShipping = Models_Mapper_ShippingConfigMapper::getInstance()->find(Shopping::SHIPPING_FREESHIPPING);

		if ($freeShipping && isset($freeShipping['config']) && !empty($freeShipping['config'])){
			$cartAmount = Tools_ShoppingCart::getInstance()->calculateCartPrice();
			if ($cartAmount > $freeShipping['config']['cartamount'] ){
				$deliveryType = $this->_shoppingConfig['country'] == $shippingAddress['country'] ? Forms_Shipping_FreeShipping::DESTINATION_NATIONAL : Forms_Shipping_FreeShipping::DESTINATION_INTERNATIONAL ;

				if ($freeShipping['config']['destination'] === Forms_Shipping_FreeShipping::DESTINATION_BOTH
					|| $freeShipping['config']['destination'] === $deliveryType ) {

					Tools_ShoppingCart::getInstance()->setShippingData(array(
							'service'   => Shopping::SHIPPING_FREESHIPPING,
							'type'      => '',
							'price'     => 0
					))->save()->saveCartSession(null);

					$this->_jsonpResponse(self::CART_WIDGET_JS_NS.'renderPaymentZone', $this->_renderPaymentZone());
				}
			}
		}

		$shippers = Models_Mapper_ShippingConfigMapper::getInstance()->fetchByStatus(Models_Mapper_ShippingConfigMapper::STATUS_ENABLED);
		if (!empty($shippers)){
			$shippers = array_map(function($shipper){
				return !in_array($shipper['name'], array(Shopping::SHIPPING_MARKUP, Shopping::SHIPPING_PICKUP, Shopping::SHIPPING_FREESHIPPING)) ? array(
					'name' => $shipper['name'],
					'title' => isset($shipper['config']) && isset($shipper['config']['title']) ? $shipper['config']['title'] : null
				) : null ;
			}, $shippers
			);
			$shippers = array_values(array_filter($shippers));
		}

		$data = array(
			'cartid'    => Tools_ShoppingCart::getInstance()->getCartId(),
			'shippers'  => $shippers,
			'caption'   => $this->_translator->translate('Select shipping method')
		);

		return $data;
	}

	protected function _jsonpResponse($callback, $data, $forceSend = true){
		$this->_response->clearAllHeaders()->clearBody();
		$body = sprintf('%s(%s);', $callback, $data);
		$this->_response->setBody($body);

		if (!$forceSend){
			return $this->_response;
		}

		return $this->_response->sendResponse();
	}
}
