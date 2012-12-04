<?php
/**
 * Shopping cart E-commerce plugin for SEOTOASTER 2.0
 *
 * This plugin is using E-commerce plugin API
 * Depends on shopping (e-commerce) plugin
 * @see http://www.seotoaster.com/
 */
class Cart extends Tools_Cart_Cart {

	const DEFAULT_LOCALE        = 'en_US';

	const DEFAULT_WEIGHT_UNIT   = 'kg';

	const DEFAULT_CURRENCY_NAME = 'USD';

	const SESSION_NAMESPACE = 'cart_checkout';

	const STEP_LANDING  = 'landing';

	const STEP_SIGNUP   = 'signup';

	const STEP_SHIPPING_METHOD  = 'method';

	const STEP_SHIPPING_OPTIONS = 'shipping';

	const STEP_PICKUP   = 'pickup';

	const STEP_SHIPPING_ADDRESS = 'address';
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

	/**
	 * @var Zend_Session_Namespace
	 */
	protected $_checkoutSession = null;

	public static $_allowBuyerSummarRendering = false;

	public static $_lockCartEdit = false;

	protected function _init() {
		$this->_cartStorage      = Tools_ShoppingCart::getInstance();
		$this->_productMapper    = Models_Mapper_ProductMapper::getInstance();
		$this->_shoppingConfig   = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
		$this->_sessionHelper    = Zend_Controller_Action_HelperBroker::getStaticHelper('session');
		$this->_jsonHelper       = Zend_Controller_Action_HelperBroker::getStaticHelper('json');
		$this->_view->weightSign = isset($this->_shoppingConfig['weightUnit']) ? $this->_shoppingConfig['weightUnit'] : 'kg';
		$this->_initCurrency();
		$this->_view->setScriptPath(dirname(__FILE__) . '/system/views/');

		$this->_checkoutSession  = new Zend_Session_Namespace(self::SESSION_NAMESPACE);

	}

	public function beforeController(){
		$layout = Zend_Layout::getMvcInstance();
		$layout->getView()->headScript()->appendFile($this->_websiteUrl.'plugins/cart/web/js/toastercart.js');
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

	public function buyersummaryAction() {
		if(!$this->_request->isPost()) {
			throw new Exceptions_SeotoasterPluginException('Direct access not allowed');
		}
		self::$_allowBuyerSummarRendering = true;
		$this->_responseHelper->success($this->_makeOptionBuyersummary());
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
        
        if(isset($this->_requestedParams['all']) && $this->_requestedParams['all'] == 'all'){
            $allProducts = $this->_requestedParams['allProducts'];
            $sidPid = array();
            foreach($allProducts as $productId=>$productOptions){
                $product = $this->_productMapper->find($productId);
                $options = ($productOptions['options']) ? $this->_parseProductOptions($productId, $productOptions['options']) : $this->_getDefaultProductOptions($product);
                $this->_cartStorage->add($product, $options, $productOptions['qty']);
                $storageKey = $this->_cartStorage->getStorageKey($product, $options);
                $sidPid[$productId] = $storageKey;
            }
            return $this->_responseHelper->success($sidPid);
        }
		$productId = $this->_requestedParams['pid'];
		$options   = $this->_requestedParams['options'];
		$qty       = isset($this->_requestedParams['qty']) ? $this->_requestedParams['qty'] : 1;
		if(!$productId) {
			throw new Exceptions_SeotoasterPluginException('Can\'t add to cart: product not defined');
		}
		$product = $this->_productMapper->find($productId);
		$options = ($options) ? $this->_parseProductOptions($productId, $options) : $this->_getDefaultProductOptions($product);
		$this->_cartStorage->add($product, $options, $qty);
        return $this->_responseHelper->success($this->_cartStorage->getStorageKey($product, $options));
		
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
        if(isset($this->_requestedParams['all']) && $this->_requestedParams['all'] == 'all'){
            foreach($this->_requestedParams['sids'] as $sid){
                $this->_cartStorage->remove($sid['sid']);
            }
            $this->_responseHelper->success($this->_translator->translate('Removed.'));
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
        if(isset($this->_options[1]) && $this->_options[1] == 'addall'){
            return $this->_view->render('addalltocart.phtml');
        }
        if(!isset($this->_options[1]) || !intval($this->_options[1])) {
            throw new Exceptions_SeotoasterPluginException('Product id is missing!');
        }
		$this->_view->checkOutPageUrl = $this->_getCheckoutPage()->getUrl();
		$this->_view->productId       = $this->_options[1];
        if(isset($this->_options[2]) && $this->_options[2] == 'checkbox'){
            return $this->_view->render('addtocartcheckbox.phtml');
        }
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
		if (count(Tools_ShoppingCart::getInstance()->getContent()) === 0 ) {
			return $this->_view->render('checkout/keepshopping.phtml');
		}

		$this->_view->actionUrl = $this->_websiteUrl.$this->_seotoasterData['url'];

		if ($this->_request->has('step')) {
			$step = strtolower($this->_request->getParam('step'));
			if ($this->_request->isGet() && (empty($this->_checkoutSession->returnAllowed)
					|| !in_array($step, $this->_checkoutSession->returnAllowed))){
				$step = self::STEP_LANDING;
				self::$_lockCartEdit = false;
			} else {
				self::$_lockCartEdit = true;
			}
			switch ($step) {
				case self::STEP_SHIPPING_ADDRESS:
					$content = $this->_checkoutStepAddress();
					break;
				case self::STEP_PICKUP:
					$content = $this->_checkoutStepPickup();
					break;
				case self::STEP_SHIPPING_METHOD:
					$content = $this->_checkoutStepMethod();
					break;
				case self::STEP_SHIPPING_OPTIONS:
					$content = $this->_checkoutStepShipping();
					break;
				default:
					$content = $this->_checkoutStepSignup();
					break;
			}
		} else {
			$content = $this->_checkoutStepSignup();
		}

		$this->_view->content = $content;

		self::$_allowBuyerSummarRendering = true;
    	return $this->_view->render('checkout/wrapper.phtml');
	}


	protected function _makeOptionCartsummary() {
		$type = $this->_request->getParam('type');
        if (isset($type) && $type == 'json'){
            $summary = $this->_cartStorage->calculate();
            if (Zend_Registry::isRegistered('Zend_Currency')){
               $currency = Zend_Registry::get('Zend_Currency'); 
               return array('subTotal'=>$currency->toCurrency($summary['subTotal']), 'totalTax'=>$currency->toCurrency($summary['totalTax']),
                   'shipping'=>$summary['shipping'], 'total'=>$currency->toCurrency($summary['total']));
           }
           return $this->_cartStorage->calculate();
        }
        $this->_view->summary = $this->_cartStorage->calculate();
        $this->_view->taxIncPrice = (bool)$this->_shoppingConfig['showPriceIncTax'];
		$this->_getCheckoutPage();
		$this->_view->returnAllowed = $this->_checkoutSession->returnAllowed;
		return $this->_view->render('cartsummary.phtml');
	}

    protected function _makeOptionBuyersummary() {
	    $cart = Tools_ShoppingCart::getInstance();
	    if (sizeof($cart->getContent()) !== 0) {
		    if (isset(self::$_allowBuyerSummarRendering) && !self::$_allowBuyerSummarRendering){
                return '{$store:buyersummary}';
            }

		    $this->_getCheckoutPage();

		    $this->_view->returnAllowed = $this->_checkoutSession->returnAllowed;
		    $this->_view->yourInformation = $this->_checkoutSession->initialCustomerInfo;
		    $this->_view->shippingData = $cart->getShippingData();
		    $this->_view->shippingAddress = $cart->getAddressById($cart->getAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING));
		    return $this->_view->render('buyersummary.phtml');
	    }
    }
    
	protected function _parseProductOptions($productId, $options) {
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
		$step = filter_var($this->_request->getParam('step'), FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
		$methodName = '_checkoutStep'.ucfirst(strtolower($step));
		if (method_exists($this, $methodName)){
			if ($this->_request->isXmlHttpRequest()){
				$content = $this->$methodName();
				if (!empty($content)){
					$themeData = Zend_Registry::get('theme');
					$parserOptions = array(
						'websiteUrl'   => $this->_websiteHelper->getUrl(),
						'websitePath'  => $this->_websiteHelper->getPath(),
						'currentTheme' => Zend_Controller_Action_HelperBroker::getExistingHelper('config')->getConfig('currentTheme'),
						'themePath'    => $themeData['path'],
					);
					$parser = new Tools_Content_Parser($content, $this->_getCheckoutPage()->toArray(), $parserOptions);
					echo $parser->parse();
				} else {
					echo $content;
				}
				return;
			} else {

			}
		} else {
			if ($this->_request->isXmlHttpRequest()){
				$this->_response->clearAllHeaders()->clearBody();
				return $this->_response->setHttpResponseCode(Api_Service_Abstract::REST_STATUS_BAD_REQUEST)->sendResponse();
			}
		}
		$checkoutPage = Tools_Misc::getCheckoutPage();
		if ($checkoutPage instanceof Application_Model_Models_Abstract){
			$this->_redirector->gotoUrlAndExit($checkoutPage->getUrl());
		} else {
			$this->_redirector->gotoUrlAndExit($this->_websiteUrl);
		}
	}

	private function _checkoutStepAddress(){
		$form = new Forms_Checkout_Address();

		if ($this->_request->isPost() && $form->isValid($this->_request->getParams())){
			$addressType = Models_Model_Customer::ADDRESS_TYPE_SHIPPING;
			$shoppingCart = Tools_ShoppingCart::getInstance();
            $customer  = $shoppingCart->getCustomer();
			$addressId = Models_Mapper_CustomerMapper::getInstance()->addAddress($customer, $form->getValues(), $addressType);
			$shoppingCart->setAddressKey($addressType, $addressId)
                ->setNotes($form->getValue('notes'))
				->save()
				->saveCartSession($customer);

			$this->_checkoutSession->returnAllowed = array(
				self::STEP_LANDING,
				self::STEP_SHIPPING_OPTIONS
			);

			return $this->_renderShippingMethods();
		} else {

		}
		return $this->_renderShippingOptions(null, $form);
	}

	private function _checkoutStepMethod() {
		if ($this->_request->isPost()){
			$shipper = filter_var($this->_request->getParam('shipper'), FILTER_SANITIZE_STRING);
			if ($shipper){
				list($shipper, $index) = explode('::', $shipper);
				if ($shipper === Shopping::SHIPPING_PICKUP) {
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
					$this->_checkoutSession->returnAllowed = array(
						self::STEP_LANDING,
						self::STEP_SHIPPING_OPTIONS,
						self::STEP_SHIPPING_METHOD
					);
					return $this->_renderPaymentZone();
				}
			}
		} else {
			Tools_ShoppingCart::getInstance()->setShippingData(null)->save()->saveCartSession(null);
			$this->_checkoutSession->returnAllowed = array(
				self::STEP_LANDING,
				self::STEP_SHIPPING_OPTIONS
			);
		}
		return $this->_renderShippingMethods();
	}

	private function _checkoutStepPickup() {
		$pickupForm = new Forms_Checkout_Pickup();
		if ($this->_request->isPost()){
			if ($pickupForm->isValid($this->_request->getPost())){
				$customer = Tools_ShoppingCart::getInstance()->getCustomer();
				$address = array_merge($pickupForm->getValues(), array(
					'country'   => isset($this->_shoppingConfig['country']) ? $this->_shoppingConfig['country'] : null,
					'state'     => isset($this->_shoppingConfig['state'])   ? $this->_shoppingConfig['state']   : null,
					'zip'       => isset($this->_shoppingConfig['zip'])     ? $this->_shoppingConfig['zip']     : null
				));
				$addressId = Models_Mapper_CustomerMapper::getInstance()->addAddress($customer, $address, Models_Model_Customer::ADDRESS_TYPE_SHIPPING);
				Tools_ShoppingCart::getInstance()->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING, $addressId)
					->setShippingData(array(
						'service'   => Shopping::SHIPPING_PICKUP,
						'type'      => null,
						'price'     => 0
					))
					->save()
					->saveCartSession($customer);

				$this->_checkoutSession->returnAllowed = array(
					self::STEP_LANDING,
					self::STEP_SHIPPING_OPTIONS
				);
				return $this->_renderPaymentZone();
			}
		}
		return $this->_renderShippingOptions($pickupForm);
	}

	private function _checkoutStepSignup(){
		$this->_checkoutSession->returnAllowed = array(
			self::STEP_LANDING
		);
		$form = new Forms_Signup();

		if ($this->_request->isPost()){
			if ($form->isValid($this->_request->getPost())){
				$customerData = $form->getValues();
				$this->_checkoutSession->initialCustomerInfo = $customerData;
				$customer    = Shopping::processCustomer($customerData);
				if ($customer->getId()) {
					Tools_ShoppingCart::getInstance()
							->setCustomerId($customer->getId())
							->save()
							->saveCartSession($customer);
	            }
				return $this->_renderShippingOptions();
			}
		} else {
			$this->_checkoutSession->unsetAll();
			Tools_ShoppingCart::getInstance()
				->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_BILLING, null)
				->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING, null)
				->setCustomerId(null)
				->setShippingData(null)
				->setNotes(null)
				->save();
		}

		return $this->_renderLandingForm($form);
	}

	private function _checkoutStepShipping(){
		if (!$this->_checkoutSession->returnAllowed) {
			return $this->_checkoutStepSignup();
		}

		$content = $this->_renderShippingOptions();
		self::$_lockCartEdit = false;
		Tools_ShoppingCart::getInstance()
			->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_BILLING, null)
			->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING, null)
			->setShippingData(null)
			->save();
		return $content;
	}

	protected function _renderLandingForm($signupForm = null) {
		if (!isset($this->_view->actionUrl)){
			$this->_view->actionUrl = $this->_websiteUrl.$this->_getCheckoutPage()->getUrl();
		}

		$form = (bool)$signupForm ? $signupForm : new Forms_Signup();
		$form->setAction($this->_view->actionUrl);

		$this->_view->signupForm        = $form;

		$this->_view->isError = $form->isErrors();

		$flashMessenger = Zend_Controller_Action_HelperBroker::getStaticHelper('flashMessenger');
		if ($flashMessenger){
			$msg = $flashMessenger->getMessages();
			if (!empty($msg) && (in_array('There is no user with such login and password.', $msg) || in_array('Login should be a valid email address', $msg))){
				$this->_view->isError = true;
			}
		}

		return $this->_view->render('checkout/landing.phtml');
	}

	protected function _renderShippingOptions($pickupForm = null, $shippingForm = null){
		$pickup = Models_Mapper_ShippingConfigMapper::getInstance()->find(Shopping::SHIPPING_PICKUP);
		$shippers = Models_Mapper_ShippingConfigMapper::getInstance()->fetchByStatus(Models_Mapper_ShippingConfigMapper::STATUS_ENABLED);

		if (!empty($shippers)){
			$shippers = array_filter($shippers, function($shipper){
				return !in_array($shipper['name'], array(
					Shopping::SHIPPING_MARKUP,
					Shopping::SHIPPING_PICKUP
				));
			});
		}

		if (is_null($pickup) || (bool)$pickup['enabled'] == false){
			if (empty($shippers)){
				return $this->_renderPaymentZone();
			}
		}

		// preparing user info for forms
		$addrType = Models_Model_Customer::ADDRESS_TYPE_SHIPPING;
		if (null !== ($uniqKey = Tools_ShoppingCart::getInstance()->getAddressKey($addrType))){
            $customerAddress = Tools_ShoppingCart::getAddressById($uniqKey);
        } else {
            $customer = Tools_ShoppingCart::getInstance()->getCustomer();
            if (Tools_Security_Acl::isAllowed(Shopping::RESOURCE_CART) && null === ($customerAddress = $customer->getDefaultAddress($addrType))){
                $name = explode(' ', $customer->getFullName());
                $userData = array(
	                'firstname' => isset($name[0]) ? $name[0] : '',
	                'lastname'  => isset($name[1]) ? $name[1] : '',
	                'email'     => $customer->getEmail()
                );
	            $customerAddress = array_merge(
		            $userData,
                    !empty($this->_checkoutSession->initialCustomerInfo) ? $this->_checkoutSession->initialCustomerInfo : array(),
                    array(
                        'country'   => $this->_shoppingConfig['country'],
                        'state'     => $this->_shoppingConfig['state'],
                        'zip'       => $this->_shoppingConfig['zip']
                    )
                );
            }
        }

		if ($pickup && (bool)$pickup['enabled']){
			if ((bool)$pickupForm){
				$this->_view->pickupForm = $pickupForm;
			} else {
				$this->_view->pickupForm = new Forms_Checkout_Pickup();
				if (is_array($customerAddress) && !empty($customerAddress)) {
					$this->_view->pickupForm->populate($customerAddress);
				}
			}
			$this->_view->pickupForm->setAction($this->_view->actionUrl);
		}

		if (!empty($shippers)){
			if ((bool)$shippingForm){
				$this->_view->shippingForm = $shippingForm;
			} else {
				$this->_view->shippingForm = new Forms_Checkout_Address();
				if (is_array($customerAddress) && !empty($customerAddress)) {
					$this->_view->shippingForm->populate($customerAddress);
				}
			}
			$this->_view->shippingForm->setAction($this->_view->actionUrl);

			$this->_view->shippingForm->populate(array(
				'notes' => Tools_ShoppingCart::getInstance()->getNotes()
			));
		}

		$this->_view->shoppingConfig = $this->_shoppingConfig;
		return $this->_view->render('checkout/shipping_options.phtml');
	}

	protected function _renderPaymentZone() {
		$paymentZoneTmpl = isset($this->_sessionHelper->paymentZoneTmpl) ? $this->_sessionHelper->paymentZoneTmpl : null;
		if ($paymentZoneTmpl !== null) {
            $themeData = Zend_Registry::get('theme');
			$extConfig = Zend_Registry::get('extConfig');
			$parserOptions = array(
				'websiteUrl'   => $this->_websiteHelper->getUrl(),
				'websitePath'  => $this->_websiteHelper->getPath(),
				'currentTheme' => $extConfig['currentTheme'],
				'themePath'    => $themeData['path'],
			);
			$parser = new Tools_Content_Parser($paymentZoneTmpl, Tools_Misc::getCheckoutPage()->toArray(), $parserOptions);
			return '<div id="payment-zone">'.$parser->parse().'</div>';
		}
	}

	protected function _renderShippingMethods() {
		if (false !== ($freeShipping = $this->_qualifyFreeShipping())) {
			return $freeShipping;
		}

		$shippingServices = Models_Mapper_ShippingConfigMapper::getInstance()->fetchByStatus(Models_Mapper_ShippingConfigMapper::STATUS_ENABLED);
		if (!empty($shippingServices)){
			$shippingServices = array_map(function($shipper){
				return !in_array($shipper['name'], array(Shopping::SHIPPING_MARKUP, Shopping::SHIPPING_PICKUP, Shopping::SHIPPING_FREESHIPPING)) ? array(
					'name' => $shipper['name'],
					'title' => isset($shipper['config']) && isset($shipper['config']['title']) ? $shipper['config']['title'] : null
				) : null ;
			}, $shippingServices );
			$shippingServices = array_values(array_filter($shippingServices));
		}

		$this->_view->shoppingConfig = $this->_shoppingConfig;
		$this->_view->shippers = $shippingServices;

		return $this->_view->render('checkout/shipping_methods.phtml');
	}

	protected function _qualifyFreeShipping(){
		$cart = Tools_ShoppingCart::getInstance();
		$shippingAddress = $cart->getAddressById($cart->getAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING));

		//checking if freeshipping is enabled and eligible for this order
		if (!empty($shippingAddress)){
			$freeShipping = Models_Mapper_ShippingConfigMapper::getInstance()->find(Shopping::SHIPPING_FREESHIPPING);
			if ($freeShipping && (bool)$freeShipping['enabled'] && isset($freeShipping['config']) && !empty($freeShipping['config'])){
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

						return '<h3>'.$this->_translator->translate('Great news! Your purchase is eligible for free shipping').'</h3>'.
							$this->_renderPaymentZone();
					}
				}
			}
		}

		return false;
	}
}
