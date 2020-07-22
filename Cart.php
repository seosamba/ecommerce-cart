<?php
/**
 * Shopping cart E-commerce plugin for SEOTOASTER 2.0
 *
 * This plugin is using E-commerce plugin API
 * Depends on shopping (e-commerce) plugin
 * @see http://www.seotoaster.com/
 */
class Cart extends Tools_Cart_Cart {

	const DEFAULT_LOCALE = 'en_US';

	const DEFAULT_WEIGHT_UNIT = 'kg';

	const DEFAULT_CURRENCY_NAME = 'USD';

	const SESSION_NAMESPACE = 'cart_checkout';

	const STEP_LANDING = 'landing';

	const STEP_SIGNUP = 'signup';

	const STEP_SHIPPING_METHOD = 'method';

	const STEP_SHIPPING_OPTIONS = 'shipping';

	const STEP_PICKUP = 'pickup';

	const STEP_SHIPPING_ADDRESS = 'address';

    const DEFAULT_RELATED_QUANTITY = '20';

    /**
     * Add password fields in the registration form at the checkout
     */
    const REGISTRATION_WITH_PASSWORD = 'with-password';

    /**
     * Add subscribe on the checkout registration
     */
    const REGISTRATION_WITH_SUBSCRIPTION = 'with-subscription';

    /**
     * Show price without price
     */
    const WITHOUT_TAX = 'withouttax';

	/**
	 * Shopping cart main storage.
	 *
	 * @var Tools_ShoppingCart
	 */
	protected $_cartStorage = null;

	/**
	 * Product mapper from the shopping plugin
	 *
	 * @var Models_Mapper_ProductMapper
	 */
	protected $_productMapper = null;

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
	protected $_jsonHelper = null;

	/**
	 * Currency object to keep valid number formats
	 *
	 * @var Zend_Currency
	 */
	protected $_currency = null;

	protected $_sessionHelper = null;

	/**
	 * @var Zend_Session_Namespace
	 */
	protected $_checkoutSession = null;

	public static $_allowBuyerSummarRendering = false;

	public static $_lockCartEdit = false;

    public static $_pickupLocationRadius = array('5', '10', '50');

	protected function _init() {
		$this->_cartStorage = Tools_ShoppingCart::getInstance();
		$this->_productMapper = Models_Mapper_ProductMapper::getInstance();
		$this->_shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
		$this->_sessionHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('session');
		$this->_jsonHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('json');
		$this->_view->weightSign = isset($this->_shoppingConfig['weightUnit']) ? $this->_shoppingConfig['weightUnit'] : 'kg';
		$this->_initCurrency();
		$this->_view->setScriptPath(dirname(__FILE__) . '/system/views/');

		$this->_checkoutSession = new Zend_Session_Namespace(self::SESSION_NAMESPACE);

	}

    public function beforeController()
    {
        $currentController = $this->_request->getParam('controller');
        if (!preg_match('~backend_~', $currentController)) {
            $layout = Zend_Layout::getMvcInstance();
            $layout->getView()->inlineScript()
                   ->appendFile($this->_websiteUrl . 'plugins/cart/web/js/toastercart.min.js');
        }
    }

	private function _initCurrency() {
		if (!Zend_Registry::isRegistered('Zend_Currency')) {
			$this->_currency = new Zend_Currency(self::DEFAULT_LOCALE);
		} else {
			$this->_currency = Zend_Registry::get('Zend_Currency');
		}
		$correctCurrency = isset($this->_shoppingConfig['currency']) ? $this->_shoppingConfig['currency'] : self::DEFAULT_CURRENCY_NAME;
		$this->_view->currencySymbol = $this->_currency->getSymbol($correctCurrency, self::DEFAULT_LOCALE);
		$this->_view->currencyShortName = $correctCurrency;
	}

	protected function _getCheckoutPage() {
		$cacheHelper = Zend_Controller_Action_HelperBroker::getExistingHelper('cache');
		if (null === ($checkoutPage = $cacheHelper->load(Shopping::CHECKOUT_PAGE_CACHE_ID, Shopping::CACHE_PREFIX))) {
			$checkoutPage = Tools_Misc::getCheckoutPage();
			if (!$checkoutPage instanceof Application_Model_Models_Page) {
				if (Tools_Security_Acl::isAllowed(Tools_Security_Acl::RESOURCE_ADMINPANEL)) {
					throw new Exceptions_SeotoasterPluginException($this->_translator->translate('Error rendering cart. Please select a checkout page'));
				}
				throw new Exceptions_SeotoasterPluginException($this->_translator->translate('Error rendering cart. Please select a checkout page'));
			}
			$cacheHelper->save(Shopping::CHECKOUT_PAGE_CACHE_ID, $checkoutPage, 'store_', array(), Helpers_Action_Cache::CACHE_SHORT);
		}
		$this->_view->checkoutPage = $checkoutPage;
		return $checkoutPage;
	}

	public function run($requestedParams = array()) {
		$dispatchersResult = parent::run($requestedParams);
		if ($dispatchersResult) {
			return $dispatchersResult;
		}
	}

	public function cartAction() {
		$requestMethod = $this->_request->getMethod();
		$data = array();
		switch ($requestMethod) {
			case 'GET':
				if (isset($this->_requestedParams['sid']) && $this->_requestedParams['sid']) {

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
		if (!$this->_request->isPost()) {
			throw new Exceptions_SeotoasterPluginException($this->_translator->translate('Direct access not allowed'));
		}
		$this->_responseHelper->success($this->_makeOptionCartsummary());
	}

	public function buyersummaryAction() {
		if (!$this->_request->isPost()) {
			throw new Exceptions_SeotoasterPluginException($this->_translator->translate('Direct access not allowed'));
		}
		self::$_allowBuyerSummarRendering = true;
		$this->_responseHelper->success($this->_makeOptionBuyersummary());
	}

	public function cartcontentAction() {
		if (!$this->_request->isPost()) {
			throw new Exceptions_SeotoasterPluginException($this->_translator->translate('Direct access not allowed'));
		}
		$nocurrency = filter_var($this->_request->getParam('nocurrency'), FILTER_SANITIZE_STRING);
        $cartContent = $this->_cartStorage->getContent();
       	foreach($cartContent as $sid => $item){
            $data[$sid] = array(
			    'price'  => Tools_Factory_WidgetFactory::createWidget('Cartitem', array($sid, 'price', $nocurrency))->render(),
			    'weight' => Tools_Factory_WidgetFactory::createWidget('Cartitem', array($sid, 'weight'))->render(),
            );
        }
        $this->_responseHelper->success($data);
	}

	protected function _addToCart() {

		if (!$this->_request->isPost()) {
			throw new Exceptions_SeotoasterPluginException($this->_translator->translate('Direct access not allowed'));
		}

		if (isset($this->_requestedParams['all']) && $this->_requestedParams['all'] == 'all') {
			$allProducts = $this->_requestedParams['allProducts'];
			$sidPid = array();
			foreach ($allProducts as $productId => $productOptions) {
				$product = $this->_productMapper->find($productId);
				$options = ($productOptions['options']) ? $this->_parseProductOptions($productId, $productOptions['options']) : $this->_getDefaultProductOptions($product);
				$storageKey = $this->_cartStorage->add($product, $options, $productOptions['qty']);
				$sidPid[$productId] = $storageKey;
			}
			return $this->_responseHelper->success($sidPid);
		}
		$productId = $this->_requestedParams['pid'];
		$options = $this->_requestedParams['options'];
		$addCount = isset($this->_requestedParams['qty']) ? abs(intval($this->_requestedParams['qty'])) : 1;
		if (!$productId) {
			throw new Exceptions_SeotoasterPluginException($this->_translator->translate('Can\'t add to cart: product not defined'));
		}
		$product = $this->_productMapper->find($productId);
		$inStockCount = $product->getInventory();
        $productDisabled = $product->getEnabled();
        if (!$productDisabled) {
            return $this->_responseHelper->response(
                array('msg' => $this->_translator->translate('This product is not available')),
                1
            );
        }

        $options = ($options) ? $this->_parseProductOptions($productId, $options) : $this->_getDefaultProductOptions($product);
        $sid = $this->_generateStorageKey($product, $options);

        if (null !== ($cartItem = $this->_cartStorage->findBySid($sid))) {
            $inCartCount = $cartItem['qty'];
        } else {
            $inCartCount = 0;
        }

        $customInventory = Tools_Misc::applyInventory($productId, $options, $addCount + $inCartCount, Tools_InventoryObserver::INVENTORY_IN_STOCK_METHOD);

        $errMessageOutOfStock = (!empty($this->_shoppingConfig['outOfStock'])) ? $this->_shoppingConfig['outOfStock'] : $this->_translator->translate('The requested product is out of stock');
        if (!is_null($inStockCount) && !empty($inStockCount)) {
            $errMessageLimitQty = (!empty($this->_shoppingConfig['limitQty'])) ? preg_replace('~{ ?\$product:inventory ?}~i', $inStockCount, $this->_shoppingConfig['limitQty']) : $this->_translator->translate('The requested quantity is not available');
        } else {
            $errMessageLimitQty = (!empty($this->_shoppingConfig['limitQty'])) ? $this->_shoppingConfig['limitQty'] : $this->_translator->translate('The requested quantity is not available');
        }

        if ($customInventory['error'] === true) {
            if (!empty($customInventory['stock'])) {
                return $this->_responseHelper->response(array('stock' => $customInventory['stock'], 'msg' => $errMessageLimitQty), 1);
            }
            return $this->_responseHelper->response(array('stock' => $customInventory['stock'], 'msg' => $errMessageOutOfStock), 1);
        }

		if (!is_null($inStockCount)) {
			$inStockCount = intval($inStockCount);
			if (null !== ($cartItem = $this->_cartStorage->find($productId))) {
				$inCartCount = $cartItem['qty'];
			} else {
				$inCartCount = 0;
			}
			if ($inStockCount <= 0) {
				return $this->_responseHelper->response(array('stock' => $inStockCount, 'msg' => $errMessageOutOfStock), 1);
			}
			if ($inStockCount - ($addCount + $inCartCount) < 0) {
				return $this->_responseHelper->response(array('stock' => $inStockCount, 'msg' => $errMessageLimitQty), 1);
			}
		}
        if (Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('throttleTransactions') === 'true' && Tools_Misc::checkThrottleTransactionsLimit() === false) {
            return $this->_responseHelper->response(
                array('msg' => $this->_translator->translate('Our transaction limit for today has exceeded.')),
                1
            );
        };
        $productFreebiesSettings = Models_Mapper_ProductFreebiesSettingsMapper::getInstance()->getFreebies($productId);
        $freebiesProducts = array();
        if(!empty($productFreebiesSettings)){
            if($productFreebiesSettings[0]['quantity'] == 0 && $productFreebiesSettings[0]['price_value'] != 0){
                if($productFreebiesSettings[0]['price_value'] <= $this->_cartStorage->getTotal()){
                    $freebiesProducts = $this->_prepareFreebies($productFreebiesSettings);
                }
            }elseif($productFreebiesSettings[0]['price_value'] == 0 && $productFreebiesSettings[0]['quantity'] != 0){
                if($productFreebiesSettings[0]['quantity'] <= $addCount){
                    $freebiesProducts = $this->_prepareFreebies($productFreebiesSettings);
                }
            }elseif($productFreebiesSettings[0]['quantity'] <= $addCount && $productFreebiesSettings[0]['price_value'] <= $this->_cartStorage->getTotal()){
                $freebiesProducts = $this->_prepareFreebies($productFreebiesSettings);
            }
        }

        if(!empty($freebiesProducts)){
            foreach($freebiesProducts['freebiesProducts'] as $prodId =>$freebiesProduct){
                $itemKey = $this->_generateStorageKey($freebiesProduct, array(0 => 'freebies_'.$productId));
                if(!$this->_cartStorage->findBySid($itemKey)){
                    $freebiesProduct->setFreebies(1);
                    $this->_cartStorage->add($freebiesProduct, array(0 => 'freebies_'.$productId), $freebiesProducts['freebiesQuantity'][$prodId]);
                }
            }
        }

		$storageKey = $this->_cartStorage->add($product, $options, $addCount);
		return $this->_responseHelper->success($storageKey);
	}

    private function _prepareFreebies($productFreebiesSettings){

        $freebiesQuantity = array();
        foreach($productFreebiesSettings as $freebies){
            $freebiesProduct = $this->_productMapper->find($freebies['freebies_id']);
            if($freebiesProduct instanceof Models_Model_Product){
                $inStockCount = $freebiesProduct->getInventory();
                if(!is_null($inStockCount)) {
                    $inStockCount = intval($inStockCount);
                    if ($inStockCount <= 0 || $inStockCount < $freebies['freebies_quantity']) {
                        $errMessageOutOfStock = (!empty($this->_shoppingConfig['outOfStock'])) ? $this->_shoppingConfig['outOfStock'] : $this->_translator->translate('The requested product is out of stock');
                        return $this->_responseHelper->response(array('stock' => $inStockCount, 'msg' => $errMessageOutOfStock), 1);
                    }
                }
                $freebiesProducts[$freebiesProduct->getId()] = $freebiesProduct;
                $freebiesQuantity[$freebiesProduct->getId()] = $freebies['freebies_quantity'];
            }
        }
        return array('freebiesProducts' => $freebiesProducts, 'freebiesQuantity' => $freebiesQuantity);
    }

    private function _generateStorageKey($item, $options = array()) {
        return substr(md5($item->getName() . $item->getSku() . http_build_query($options)), 0, 10);
    }

	protected function _getDefaultProductOptions(Models_Model_Product $product) {
		$productOptions = $product->getDefaultOptions();
		if (!is_array($productOptions) || empty($productOptions)) {
			return array();
		}
		foreach ($productOptions as $key => $option) {
			if (isset($option['selection']) && is_array($option['selection']) && !empty($option['selection'])) {
				$selections = $option['selection'];
				foreach ($selections as $selectionData) {
					if (!$selectionData['isDefault']) {
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

		if (!$this->_request->isPut()) {
			throw new Exceptions_SeotoasterPluginException($this->_translator->translate('Direct access not allowed'));
		}
		$storageId = filter_var($this->_requestedParams['sid'], FILTER_SANITIZE_STRING);
		$newQty = filter_var($this->_requestedParams['qty'], FILTER_SANITIZE_NUMBER_INT);
		$cartItem = $this->_cartStorage->findBySid($storageId);
		if (null !== ($prod = Models_Mapper_ProductMapper::getInstance()->find($cartItem['id']))) {
            if (!empty($cartItem['options'])) {
                $options = array();
                foreach ($cartItem['options'] as  $optionData) {
                    $options[$optionData['option_id']] = $optionData['id'];
                }
                $customInventory = Tools_Misc::applyInventory($cartItem['id'], $options, $newQty, Tools_InventoryObserver::INVENTORY_IN_STOCK_METHOD);

                $errMessageOutOfStock = (!empty($this->_shoppingConfig['outOfStock'])) ? $this->_shoppingConfig['outOfStock'] : $this->_translator->translate('The requested product is out of stock');
                if (!is_null($prod->getInventory()) && !empty($prod->getInventory())) {
                    $errMessageLimitQty = (!empty($this->_shoppingConfig['limitQty'])) ? preg_replace('~{ ?\$product:inventory ?}~i', $prod->getInventory(), $this->_shoppingConfig['limitQty']) : $this->_translator->translate('The requested quantity is not available');
                } else {
                    $errMessageLimitQty = (!empty($this->_shoppingConfig['limitQty'])) ? $this->_shoppingConfig['limitQty'] : $this->_translator->translate('The requested quantity is not available');
                }

                if ($customInventory['error'] === true) {
                    if (!empty($customInventory['stock'])) {
                        return $this->_responseHelper->fail($errMessageLimitQty);
                    }
                    return $this->_responseHelper->fail($errMessageOutOfStock);
                }
            }

            if (!is_null($prod->getInventory())) {
				$inStock = intval($prod->getInventory());
				if ($inStock === 0) {
					$this->_cartStorage->remove($storageId);
					return $this->_responseHelper->fail(
						$this->_view->translate("Sorry, %1\$s is currently out of stock", $prod->getName())
					);
				} elseif ($newQty > $inStock) {
					$newQty = $inStock;
				}
			}
		}
        if ($this->_cartStorage->updateQty($storageId, $newQty)) {
            $orderMinQty = $this->_analyzeOrderQuantity();
            return $this->_responseHelper->success(array(
				'sid' => $storageId,
				'qty' => $newQty,
                'minqty' => $orderMinQty,
				'msg' => $this->_view->translate("Sorry, we only have %1\$s %2\$s available in stock at the moment", $newQty, $prod->getName())
			));
		}
	}

	protected function _removeFromCart() {
		if (!$this->_request->isDelete()) {
			throw new Exceptions_SeotoasterPluginException($this->_translator->translate('Direct access not allowed'));
		}
		if (isset($this->_requestedParams['all']) && $this->_requestedParams['all'] == 'all') {
			foreach ($this->_requestedParams['sids'] as $sid) {
				$this->_cartStorage->remove($sid['sid']);
			}
			$this->_responseHelper->success($this->_translator->translate('Removed.'));
		}
		if ($this->_cartStorage->remove($this->_requestedParams['sid'])) {
            $orderMinQty = $this->_analyzeOrderQuantity();
            $this->_responseHelper->success(array('sidQuantity' => count($this->_cartStorage->getContent()), 'message'=> $this->_translator->translate('Removed.'), 'minqty' => $orderMinQty));
        }
		$this->_responseHelper->fail($this->_translator->translate('Cant remove product.'));
	}

    protected function _analyzeOrderQuantity()
    {
        $orderMinQty = true;
        $cartContent = $this->_cartStorage->getContent();
        $orderConfig = Models_Mapper_ShippingConfigMapper::getInstance()->find(
            Shopping::ORDER_CONFIG
        );
        if (!empty($cartContent) && !empty($orderConfig) && $orderConfig['enabled'] === 1) {
            $quantity = $this->_cartStorage->findProductQuantityInCart();
            $previousQuantity = $quantity;
            if (isset($this->_sessionHelper->orderQuantityState)) {
                $previousQuantity = $this->_sessionHelper->orderQuantityState;
            }
            $minOrderLimit = $orderConfig['config']['quantity'];
            if (($quantity >= $minOrderLimit && $previousQuantity < $minOrderLimit) ||
                ($previousQuantity >= $minOrderLimit && $quantity < $minOrderLimit)
            ) {
                $orderMinQty = false;
            }
            $this->_sessionHelper->orderQuantityState = $quantity;
        }
        return $orderMinQty;
    }


	protected function _getCart() {
		return array_values($this->_cartStorage->getContent());
	}

	protected function _makeOptionAddtocart() {
		if (isset($this->_options[1]) && $this->_options[1] == 'addall') {
			return $this->_view->render('addalltocart.phtml');
		}
		if (!isset($this->_options[1]) || !intval($this->_options[1])) {
			throw new Exceptions_SeotoasterPluginException($this->_translator->translate('Product id is missing!'));
		}
		$this->_view->checkOutPageUrl = $this->_getCheckoutPage()->getUrl();
		$this->_view->productId = $this->_options[1];
		if (isset($this->_options[2]) && $this->_options[2] == 'checkbox') {
			return $this->_view->render('addtocartcheckbox.phtml');
		}
		$this->_view->gotocart = array_search('gotocart', $this->_options) ? true : false;
		return $this->_view->render('addtocart.phtml');
	}

	protected function _makeOptionCartblock() {
		$cartContent = $this->_cartStorage->getContent();
		$itemsCount = 0;
		if (is_array($cartContent) && !empty($cartContent)) {
			array_walk($cartContent, function ($cartItem) use (&$itemsCount) {
				$itemsCount += $cartItem['qty'];
			});
		}
		$this->_view->itemsCount = $itemsCount;
		$this->_view->summary = $this->_cartStorage->calculate();
		$this->_getCheckoutPage();
		return $this->_view->render('cartblock.phtml');
	}

	protected function _makeOptionCart() {
		$this->_view->showTaxCol = isset($this->_shoppingConfig['showPriceIncTax']) ? $this->_shoppingConfig['showPriceIncTax'] : 0;
		$this->_view->config = $this->_shoppingConfig;
		$this->_view->cartContent = $this->_cartStorage->getContent();
		return $this->_view->render('cart.phtml');
	}

	protected function _makeOptionCheckout() {
        $shoppingCart = Tools_ShoppingCart::getInstance();
        if (count($shoppingCart->getContent()) === 0) {
            $shoppingCart->setShippingData(array());
            $shoppingCart->calculate(true);
            $shoppingCart->save();
            return $this->_view->render('checkout/keepshopping.phtml');
		}

		$this->_view->actionUrl = $this->_websiteUrl . $this->_seotoasterData['url'];

		if ($this->_request->has('step')) {
			$step = strtolower($this->_request->getParam('step'));
			if ($this->_request->isGet() && (empty($this->_checkoutSession->returnAllowed)
							|| !in_array($step, $this->_checkoutSession->returnAllowed))
			) {
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

        if (isset($type)) {
            if($type == 'json') {
                $summary = $this->_cartStorage->calculate();
                if (Zend_Registry::isRegistered('Zend_Currency')) {
                    $currency = Zend_Registry::get('Zend_Currency');
                    return array('subTotal' => $currency->toCurrency($summary['subTotal']), 'totalTax' => $currency->toCurrency($summary['totalTax']),
                        'shipping' => $summary['shipping'], 'total' => $currency->toCurrency($summary['total']));
                }
                return $this->_cartStorage->calculate();
            } elseif ($type == 'ms') {
                $checkoutPage = $this->_getCheckoutPage();

                if($checkoutPage instanceof Application_Model_Models_Page) {
                    $content = $checkoutPage->getContent();

                    preg_match('~{cartsummary}(.*){/cartsummary}~suiU', $content, $found);

                    $foundContent = (is_array($found) && !empty($found) && isset($found[1])) ? $found[1] : '';
                    $parser          = new Tools_Content_Parser($foundContent, array());
                    $foundedParserContent = $parser->parseSimple();

                    return '<div id="cart-summary-magic-space">' . $foundedParserContent . '</div>';
                }
            }
        }
        $subtotalWithoutTax = false;
        if(in_array(self::WITHOUT_TAX, $this->_options) || $this->_request->getParam('subtotalWithoutTax')) {
            $subtotalWithoutTax = true;
        }

        $this->_view->subtotalWithoutTax = $subtotalWithoutTax;
        $this->_view->summary = $this->_cartStorage->calculate();
        $this->_view->taxIncPrice = (bool)$this->_shoppingConfig['showPriceIncTax'];
        $this->_view->returnAllowed = $this->_checkoutSession->returnAllowed;
        return $this->_view->render('cartsummary.phtml');
	}

	protected function _makeOptionBuyersummary() {
		$cart = Tools_ShoppingCart::getInstance();
		if (sizeof($cart->getContent()) !== 0) {
			if (isset(self::$_allowBuyerSummarRendering) && !self::$_allowBuyerSummarRendering) {
				return '{$store:buyersummary}';
			}

			$this->_getCheckoutPage();

            $pickup = Models_Mapper_ShippingConfigMapper::getInstance()->find(Shopping::SHIPPING_PICKUP);
            $defaultPickup = true;
            if ($pickup && (bool)$pickup['enabled']) {
                if(isset($pickup['config']['defaultPickupConfig']) && $pickup['config']['defaultPickupConfig'] === '0'){
                    $defaultPickup = false;
                }
            }
            $this->_view->defaultPickup = $defaultPickup;

			$this->_view->returnAllowed = $this->_checkoutSession->returnAllowed;
			$this->_view->yourInformation = $this->_checkoutSession->initialCustomerInfo;
			$this->_view->shippingData = $cart->getShippingData();
			$this->_view->shippingAddress = $cart->getAddressById($cart->getAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING));
            if (isset($cart->getShippingData()['service'])) {
                $serviceLabelMapper = Models_Mapper_ShoppingShippingServiceLabelMapper::getInstance();
                $shippingServiceLabel = $serviceLabelMapper->findByName($cart->getShippingData()['service']);
                if (!empty($shippingServiceLabel)) {
                    $this->_view->shippingServiceLabel = $shippingServiceLabel;
                }
            }

			return $this->_view->render('buyersummary.phtml');
		}
	}

	protected function _parseProductOptions($productId, $options) {
		parse_str($options, $options);
		if (is_array($options)) {
			foreach ($options as $key => $option) {
				$options[str_replace('product-' . $productId . '-option-', '', $key)] = $option;
				unset($options[$key]);
			}
		}
		return $options;
	}

	protected function _getParamsFromRawHttp() {
		parse_str($this->_request->getRawBody(), $this->_requestedParams);
	}

    protected function _makeOptionCartrelated(){
        $cartContent = Tools_ShoppingCart::getInstance()->getContent();
        $miscConfig = Zend_Registry::get('misc');
        $step = $this->_request->getParam('step');
        $currentStep = $this->_checkoutSession->returnAllowed;
        if(is_array($currentStep) && !empty($currentStep)){
            $currentStep = $currentStep[0];
        }
        if(isset($step) && $step != ''){
            $currentStep = strtolower($step);
        }
        $this->_view->addScriptPath($this->_websiteHelper->getPath() .$miscConfig['pluginsPath']. 'shopping/system/app/Widgets/Product/views/');
        if(!empty($cartContent)){
            $page = Application_Model_Mappers_PageMapper::getInstance()->find($this->_seotoasterData['id']);
            $pageOptions = $page->getExtraOptions();
            $currentUser = $this->_sessionHelper->getCurrentUser()->getRoleId();
            $widgetLocation = '';
            $widgetDisplay  = true;
            if(!empty($pageOptions) && in_array(Shopping::OPTION_CHECKOUT, $pageOptions)){
                $widgetLocation = self::SESSION_NAMESPACE;
                $this->_view->onCheckoutPage = true;
            }
            if($widgetLocation == self::SESSION_NAMESPACE && $currentUser == Tools_Security_Acl::ROLE_GUEST && $currentStep !== null){
                $widgetDisplay = false;
            }elseif($widgetLocation == self::SESSION_NAMESPACE && $currentUser != Tools_Security_Acl::ROLE_GUEST && ($currentStep == self::STEP_SIGNUP
                || $currentStep == self::STEP_SHIPPING_METHOD || $currentStep == self::STEP_PICKUP || $currentStep == self::STEP_SHIPPING_ADDRESS)){
                $widgetDisplay = false;
            }

            if($widgetDisplay){
                $ids = array();
                foreach($cartContent as $content){
                    $product = $this->_productMapper->find($content['id']);
                    if($product instanceof Models_Model_Product){
                        $relatedProductIds = $product->getRelated();
                        if(!empty($relatedProductIds)){
                            $ids = array_merge($ids, $relatedProductIds);
                        }
                    }
                }

                if(!empty($ids)){
                    $where = $this->_productMapper->getDbTable()->getAdapter()->quoteInto('p.id in (?)', $ids);
                    $limit = (isset($this->_options[3])) ? $this->_options[3] : self::DEFAULT_RELATED_QUANTITY;

                    if((end($this->_options) == 'wojs')){
                        $this->_view->onCheckoutPage = true;
                    }

                    $related = $this->_productMapper->fetchAll($where, null, null, $limit);
                    $checkoutPage = Tools_Misc::getCheckoutPage();
                    $checkoutPageUrl = $checkoutPage != null?$checkoutPage->getUrl():'';
                    $imageSize = 'small';
                    if ($related !== null) {
                        $this->_view->related = $related instanceof Models_Model_Product ? array($related) : $related ;
                        $this->_view->imageSize = (isset($this->_options[1])) ? $this->_options[1] : $imageSize;
                        if(isset($this->_options[2]) && $this->_options[2] == 'addtocart'){
                            $this->_view->checkoutPageUrl = $checkoutPageUrl;
                        }
                        return $this->_view->render('related.phtml');
                    }
                }
            }
        }

    }


	public function checkoutAction() {
		$step = filter_var($this->_request->getParam('step'), FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
		$methodName = '_checkoutStep' . ucfirst(strtolower($step));
		if (method_exists($this, $methodName)) {
			if ($this->_request->isXmlHttpRequest()) {
				$content = $this->$methodName();
				if (!empty($content)) {
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
			if ($this->_request->isXmlHttpRequest()) {
				$this->_response->clearAllHeaders()->clearBody();
				return $this->_response->setHttpResponseCode(Api_Service_Abstract::REST_STATUS_BAD_REQUEST)->sendResponse();
			}
		}
		$checkoutPage = Tools_Misc::getCheckoutPage();
		if ($checkoutPage instanceof Application_Model_Models_Abstract) {
			$this->_redirector->gotoUrlAndExit($checkoutPage->getUrl());
		} else {
			$this->_redirector->gotoUrlAndExit($this->_websiteUrl);
		}
	}

	private function _checkoutStepAddress() {
		$form = new Forms_Checkout_Address();
		if (!empty($this->_options)) {
			$requiredFields = array();
			foreach (preg_grep('/^required-.*$/', $this->_options) as $reqOpt) {
				$fields = explode(',', str_replace('required-', '', $reqOpt));
				$requiredFields = array_merge($requiredFields, $fields);
				unset($reqOpt);
			}

			if (!empty($requiredFields)) {
				$form->resetRequiredFields($requiredFields);
			}
		}

		if ($this->_request->isPost() && $form->isValid($this->_request->getParams())) {
			$addressType = Models_Model_Customer::ADDRESS_TYPE_SHIPPING;
			$shoppingCart = Tools_ShoppingCart::getInstance();
			$customer = $shoppingCart->getCustomer();
            $addressValues = $this->_normalizeMobilePhoneNumber($form->getValues());
			$addressId = Models_Mapper_CustomerMapper::getInstance()->addAddress($customer, $addressValues, $addressType);
			$shoppingCart->setShippingAddressKey($addressId)
					->setNotes($form->getValue('notes'));
            $shoppingCart->setIsGift((int)$form->getValue('isGift'));
            $shoppingCart->setGiftEmail($form->getValue('giftEmail'));
			$shoppingCart->calculate(true);
			$shoppingCart->save()->saveCartSession($customer);

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
		if ($this->_request->isPost()) {
			$shipper = filter_var($this->_request->getParam('shipper'), FILTER_SANITIZE_STRING);
			if ($shipper) {
				list($shipper, $index) = explode('::', $shipper);
				if ($shipper === Shopping::SHIPPING_PICKUP) {
					$service = array(
						'service' => Shopping::SHIPPING_PICKUP,
						'type'    => '',
						'price'   => 0
					);
				} else {
					$vault = $this->_sessionHelper->shippingRatesVault;
					if (is_array($vault) && isset($vault[$shipper])) {
						if (isset($vault[$shipper][$index])) {
							$service = array(
								'service' => $shipper,
								'type'    => $vault[$shipper][$index]['type'],
								'price'   => $vault[$shipper][$index]['price']
							);

                            if (!empty($vault[$shipper][$index]['service_id'])) {
                                $service['service_id'] = $vault[$shipper][$index]['service_id'];
                            }

                            if (!empty($vault[$shipper][$index]['service_id'])) {
                                $service['availability_days'] = $vault[$shipper][$index]['availability_days'];
                            }

                            if (!empty($vault[$shipper][$index]['service_id'])) {
                                $service['service_info'] = $vault[$shipper][$index]['service_info'];
                            }
						}
					}
				}
				if (isset($service)) {
					$cart = Tools_ShoppingCart::getInstance();
					$cart->setShippingData($service)->calculate(true);
					$cart->save()->saveCartSession(null);
					$this->_checkoutSession->returnAllowed = array(
						self::STEP_LANDING,
						self::STEP_SHIPPING_OPTIONS,
						self::STEP_SHIPPING_METHOD
					);
					return $this->_renderPaymentZone();
				}
			}
		} else {
			$cart = Tools_ShoppingCart::getInstance();
			$cart->setShippingData(null)->calculate(true);
			$cart->save()->saveCartSession(null);
			$this->_checkoutSession->returnAllowed = array(
				self::STEP_LANDING,
				self::STEP_SHIPPING_OPTIONS
			);
		}
		return $this->_renderShippingMethods();
	}

	private function _checkoutStepPickup() {
        $pickup = Models_Mapper_ShippingConfigMapper::getInstance()->find(Shopping::SHIPPING_PICKUP);
        $pickupForm = new Forms_Checkout_Pickup();
        $pickupForm->setMobilecountrycode(Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('country'));
        $defaultPickup = true;
        $price = 0;
        if ($pickup && (bool)$pickup['enabled']) {
            if(isset($pickup['config']['defaultPickupConfig']) && $pickup['config']['defaultPickupConfig'] === '0'){
                $pickupForm = new Forms_Checkout_PickupWithPrice();
                $defaultPickup = false;
            }
        }
		if ($this->_request->isPost()) {
			if ($pickupForm->isValid($this->_request->getPost())) {
                $cart = Tools_ShoppingCart::getInstance();
				$customer = Tools_ShoppingCart::getInstance()->getCustomer();
                $address = $this->_normalizeMobilePhoneNumber($pickupForm->getValues());
                if ($defaultPickup) {
                    $address = array_merge(
                        $address,
                        array(
                            'country' => isset($this->_shoppingConfig['country']) ? $this->_shoppingConfig['country'] : null,
                            'state' => isset($this->_shoppingConfig['state']) ? $this->_shoppingConfig['state'] : null,
                            'zip' => isset($this->_shoppingConfig['zip']) ? $this->_shoppingConfig['zip'] : null
                        )
                    );
                } else {
                    $pickupLocationConfigMapper = Store_Mapper_PickupLocationConfigMapper::getInstance();
                    if (!isset($address['pickupLocationId']) || $address['pickupLocationId'] === '') {
                        $this->_redirector->gotoUrl($this->_websiteUrl);
                    }
                    $locationId = filter_var($address['pickupLocationId'], FILTER_SANITIZE_NUMBER_INT);

                    if (empty($cart)) {
                        throw new Exceptions_SeotoasterPluginException($this->_translator->translate('empty cart content'));
                    }
                    if (!$pickup || !isset($pickup['config'])) {
                        throw new Exceptions_SeotoasterPluginException($this->_translator->translate('pickup not configured'));
                    }
                    $comparator = 0;
                    switch ($pickup['config']['units']) {
                        case Shopping::COMPARE_BY_AMOUNT:
                            $comparator = $cart->getTotal();
                            break;
                        case Shopping::COMPARE_BY_WEIGHT:
                            $comparator = $cart->calculateCartWeight();
                            break;
                    }

                    $result = $pickupLocationConfigMapper->getLocations($comparator, $locationId);
                    if (empty($result)) {
                        $this->_redirector->gotoUrl($this->_websiteUrl);
                    }
                    $countries = Tools_Geo::getCountries(true, true);
                    $price = $result['price'];
                    if ($result['limitType'] === Shopping::AMOUNT_TYPE_EACH_OVER) {
                        $price = round(($comparator - $result['amount_limit']) * $result['price'], 2);
                    }
                    $address['country'] = array_search($result['country'], $countries);
                    $address['zip'] = $result['zip'];
                    $address['address1'] = $result['address1'];
                    $address['address2'] = $result['address2'];
                    $address['city'] = $result['city'];
                    $pickupLocationConfigMapper->saveCartPickupLocation($cart->getCartId(), $result);
                    unset($address['pickupLocationId']);

                }
				$addressId = Models_Mapper_CustomerMapper::getInstance()->addAddress($customer, $address, Models_Model_Customer::ADDRESS_TYPE_SHIPPING);
				$cart->setShippingAddressKey($addressId)
						->setShippingData(array(
							'service' => Shopping::SHIPPING_PICKUP,
							'type'    => null,
							'price'   => $price
						))
						->calculate(true);

				$cart->save()->saveCartSession($customer);

				$this->_checkoutSession->returnAllowed = array(
					self::STEP_LANDING,
					self::STEP_SHIPPING_OPTIONS
				);
				return $this->_renderPaymentZone();
			}
		}
		return $this->_renderShippingOptions($pickupForm);
	}

	private function _checkoutStepSignup() {
		$this->_checkoutSession->returnAllowed = array(
			self::STEP_LANDING
		);
		$form = new Forms_Signup();
        $form->setMobilecountrycode(Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('country'));
		$withPassword = array_search(self::REGISTRATION_WITH_PASSWORD, $this->_options);
		$withSubscription = array_search(self::REGISTRATION_WITH_SUBSCRIPTION, $this->_options);
        $customFields = current(preg_grep('/custom-fields=*/', $this->_options));
        $elementOptions = array();
        if (!empty($customFields)) {
            $elementOptions = $this->_parseCustomFieldsData($customFields);
            $form = $this->_addAdditionalFormFields($elementOptions, $form);
            $this->_view->additionalFieldsInfo = $elementOptions;
        }

        if ($withPassword === false) {
            $form->removeElement('customerPassword');
            $form->removeElement('customerPassConfirmation');
            $this->_view->registrationWithPassword = false;
        } else {
            $this->_view->registrationWithPassword = true;
        }

        if ($withSubscription === false) {
            $form->removeElement('subscribed');
            $this->_view->withSubscribe = false;
        } else {
            $this->_view->withSubscribe = true;
        }

        $cart = Tools_ShoppingCart::getInstance();
		if ($this->_request->isPost()) {
            $email = $this->_request->getParam('email');
		    if($withPassword && !empty($email)){
                $customerIsRegistered = Models_Mapper_CustomerMapper::getInstance()->findByEmail($email);
                if($customerIsRegistered instanceof Models_Model_Customer){
                    $this->_view->emailExists = true;
                    $form->getElement('email')->setErrors(array(''));
                }
            }

			if ($form->isValid($this->_request->getPost())) {
				$customerData = $this->_normalizeMobilePhoneNumber($form->getValues());
				$this->_checkoutSession->initialCustomerInfo = $customerData;
				$customer = Shopping::processCustomer($customerData, $elementOptions);
				if ($customer->getId()) {
                    $customer->setAttribute('mobilecountrycode', $customerData['mobilecountrycode']);
                    foreach ($elementOptions as $paramName => $paramLabel) {
                        $customer->setAttribute($paramName, $customerData[$paramName]);
                    }
                    Application_Model_Mappers_UserMapper::getInstance()->saveUserAttributes($customer);
					$cart->setCustomerId($customer->getId())->calculate(true);
					$cart->save()->saveCartSession($customer);
				}
				return $this->_renderShippingOptions();
			}
		} else {
            $currentUser = $this->_sessionHelper->getCurrentUser();
            if ($currentUser->getId()) {
                $customerInfo = Models_Mapper_CustomerMapper::getInstance()->find($currentUser->getId());
                $customerData = $customerInfo->toArray();
                $customerData['firstname'] = $currentUser->getFullName();
                $customerData['lastname']  = '';
                $customerData['email']     = $currentUser->getEmail();
                $this->_checkoutSession->initialCustomerInfo = $customerData;
                if ($customerData['id']) {
                    $cart->setCustomerId($customerData['id'])->calculate(true);
                    $cart->save()->saveCartSession($customerInfo);
                }
                return $this->_renderShippingOptions();
            }else{
                $this->_checkoutSession->unsetAll();
                $cart->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_BILLING, null)
                    ->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING, null)
                    ->setCustomerId(null)
                    ->setShippingData(null);
                    //->setNotes(null)
                    //->setCoupons(null);

                $cart->calculate(true);
                $cart->save();
            }
		}

		return $this->_renderLandingForm($form);
	}

	private function _checkoutStepShipping() {
		if (!$this->_checkoutSession->returnAllowed) {
			return $this->_checkoutStepSignup();
		}

		$content = $this->_renderShippingOptions();
		self::$_lockCartEdit = false;
		Tools_ShoppingCart::getInstance()
				->setBillingAddressKey(null)
				->setShippingAddressKey(null)
				->setShippingData(null)
				->save();
		return $content;
	}

	protected function _renderLandingForm($signupForm = null) {
		if (!isset($this->_view->actionUrl)) {
			$this->_view->actionUrl = $this->_websiteUrl . $this->_getCheckoutPage()->getUrl();
		}

		$form = (bool)$signupForm ? $signupForm : new Forms_Signup();
		$form->setAction($this->_view->actionUrl);

		$this->_view->signupForm = $form;
		$this->_view->isError = $form->isErrors();

		$this->_view->hideLoginForm = in_array('nologin', $this->_options);

		$flashMessenger = Zend_Controller_Action_HelperBroker::getStaticHelper('flashMessenger');
		if ($flashMessenger) {
			$msg = $flashMessenger->getMessages();
			if (!empty($msg) && (in_array($this->_translator->translate('There is no user with such login and password.'), $msg[0]) ||
                    in_array($this->_translator->translate('Login should be a valid email address'), $msg[0]) ||
                    in_array($this->_translator->translate('Value is required and can\'t be empty'), $msg[0]) ||
                    in_array($this->_translator->translate('There is no user with such login and password.'), $msg[0]))) {
				$this->_view->isError = true;
			}
		}

        $listMasksMapper = Application_Model_Mappers_MasksListMapper::getInstance();
        $this->_view->mobileMasks = $listMasksMapper->getListOfMasksByType(Application_Model_Models_MaskList::MASK_TYPE_MOBILE);

		return $this->_view->render('checkout/landing.phtml');
	}

	protected function _renderShippingOptions($pickupForm = null, $shippingForm = null) {
		$pickup = Models_Mapper_ShippingConfigMapper::getInstance()->find(Shopping::SHIPPING_PICKUP);
		$shippers = Models_Mapper_ShippingConfigMapper::getInstance()->fetchByStatus(Models_Mapper_ShippingConfigMapper::STATUS_ENABLED);

		if (!empty($shippers)) {
			$shippers = array_filter($shippers, function ($shipper) {
				return !in_array($shipper['name'], array(
					Shopping::SHIPPING_MARKUP,
					Shopping::SHIPPING_PICKUP
				));
			});

            $orderConfig = array_filter($shippers, function ($shipper) {
                    return in_array($shipper['name'], array(
                            Shopping::ORDER_CONFIG
                    ));
            });
		}

        if (!empty($orderConfig)) {
            $cartContent = $this->_cartStorage->getContent();
            foreach($orderConfig as $orderConf){
                if($orderConf['name'] === Shopping::ORDER_CONFIG){
                    $minOrderLimit = $orderConf['config']['quantity'];
                }
            }
            if (!empty($cartContent)) {
                $quantity = $this->_cartStorage->findProductQuantityInCart();
                $this->_sessionHelper->orderQuantityState = $quantity;
                if ($quantity < $minOrderLimit) {
                    return '{$content:orderQuantityError:static}';
                }
            }
        }

		if (is_null($pickup) || (bool)$pickup['enabled'] == false) {
			if (empty($shippers)) {
				return $this->_renderPaymentZone();
			}
		}

		// preparing user info for forms
		$addrType = Models_Model_Customer::ADDRESS_TYPE_SHIPPING;
		if (null !== ($uniqKey = Tools_ShoppingCart::getInstance()->getAddressKey($addrType))) {
			$customerAddress = Tools_ShoppingCart::getAddressById($uniqKey);
		} else {
			$customer = Tools_ShoppingCart::getInstance()->getCustomer();
			if (Tools_Security_Acl::isAllowed(Shopping::RESOURCE_CART) && null === ($customerAddress = $customer->getDefaultAddress($addrType))) {
				$name = explode(' ', $customer->getFullName());
				$userData = array(
					'firstname' => isset($name[0]) ? $name[0] : '',
					'lastname'  => isset($name[1]) ? $name[1] : '',
					'email'     => $customer->getEmail()
				);
			}
			$customerAddress = array_merge(
				isset($userData) ? $userData : array(),
				!empty($this->_checkoutSession->initialCustomerInfo) ? $this->_checkoutSession->initialCustomerInfo : array(),
				array(
					'country' => $this->_shoppingConfig['country'],
					'state'   => $this->_shoppingConfig['state'],
					'zip'     => $this->_shoppingConfig['zip']
				)
			);
		}

		if ($pickup && (bool)$pickup['enabled']) {
			if ((bool)$pickupForm) {
				$this->_view->pickupForm = $pickupForm;
			} else {
                if(isset($pickup['config']['defaultPickupConfig']) && $pickup['config']['defaultPickupConfig'] === '1' || $pickup['config'] === null){
                    $defaultPickup = true;
                    $formPickup = new Forms_Checkout_Pickup();
                }else{
                    $pickupLocationMapper = Store_Mapper_PickupLocationMapper::getInstance();
                    $uniqueSearchCountries = $pickupLocationMapper->getUniqueCountries();
                    $defaultPickup = false;
                    $countries = Tools_Geo::getCountries(true, true);
                    $countriesWithLocalization = Tools_Geo::getCountries(true);
                    $this->_view->originalCountryNames = $countries;
                    $countries = array_flip($countries);
                    $searchCountries = array();
                    if (!empty($uniqueSearchCountries)) {
                        foreach ($uniqueSearchCountries as $uniqueSearchCountry) {
                            if (!empty($countries[$uniqueSearchCountry])) {
                                $searchCountries[$countries[$uniqueSearchCountry]] = $countriesWithLocalization[$countries[$uniqueSearchCountry]];
                            }
                        }
                    }
                    $this->_view->pickupLocationConfig = $pickup['config'];
                    $this->_view->locationList = self::getPickupLocationCities();
                    $this->_view->uniqueSearchCountries = $searchCountries;
                    $formPickup = new Forms_Checkout_PickupWithPrice();
                }
                $formPickup->setMobilecountrycode(Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('country'));
                $this->_view->defaultPickup = $defaultPickup;
                $checkoutPage = Tools_Misc::getCheckoutPage();
                if ($checkoutPage instanceof Application_Model_Models_Page) {
                    $this->_view->checkoutPage = $checkoutPage;
                }
                $formPickup->setLegend($this->_translator->translate('Enter pick up information'));
                $this->_view->pickupForm = $formPickup;
				if (is_array($customerAddress) && !empty($customerAddress)) {
                    // take from $customerAddress
					$this->_view->pickupForm->populate($customerAddress);
                    if(empty($customerAddress['mobilecountrycode'])) {
                        if(!empty($customerAddress['attributes']['mobilecountrycode'])) {
                            $this->_view->pickupForm->setMobilecountrycode($customerAddress['attributes']['mobilecountrycode']);
                            $this->_view->pickupForm->setMobile($customerAddress['mobilePhone']);
                        }
                    }else {
                        $this->_view->pickupForm->setMobilecountrycode($customerAddress['mobilecountrycode']);
                        $this->_view->pickupForm->setMobile($customerAddress['mobile']);
                    }
				}
			}
			$this->_view->pickupForm->setAction($this->_view->actionUrl);
		}

		if (!empty($shippers)) {
			if ((bool)$shippingForm) {
				$this->_view->shippingForm = $shippingForm;
			} else {
				$shippingForm = new Forms_Checkout_Address();
                $shippingForm->setLegend($this->_translator->translate('Enter your shipping address'));
                $this->_view->shippingForm = $shippingForm;
				if (is_array($customerAddress) && !empty($customerAddress)) {
					$this->_view->shippingForm->populate($customerAddress);
                    if(empty($customerAddress['mobilecountrycode'])) {
                        if(!empty($customerAddress['attributes']['mobilecountrycode'])) {
                            $this->_view->shippingForm->setMobilecountrycode($customerAddress['attributes']['mobilecountrycode']);
                            $this->_view->shippingForm->setMobile($customerAddress['mobilePhone']);
                        }
                    }else {
                        $this->_view->shippingForm->setMobilecountrycode($customerAddress['mobilecountrycode']);
                        $this->_view->shippingForm->setMobile($customerAddress['mobile']);
                    }
				}
			}
			// looking for mandatory fields
			$requiredFields = array();
			foreach (preg_grep('/^' . Forms_Checkout_Address::CSS_CLASS_REQUIRED . '-.*$/', $this->_options) as $reqOpt) {
				$fields = explode(',', str_replace(Forms_Checkout_Address::CSS_CLASS_REQUIRED . '-', '', $reqOpt));
				$requiredFields = array_merge($requiredFields, $fields);
				unset($reqOpt);
			}

			if (!empty($requiredFields)) {
				$this->_view->shippingForm->resetRequiredFields($requiredFields);
			}

            if(array_search('pickaddress', $this->_options) && Tools_Security_Acl::ROLE_GUEST != $this->_sessionHelper->getCurrentUser()->getRoleId()){
                $customerId = $this->_sessionHelper->getCurrentUser()->getId();
                $customer = Models_Mapper_CustomerMapper::getInstance()->find($customerId);
                if ($customer) {
                    $this->_view->shippingForm->setLegend($this->_translator->translate('Select a shipping address or create new'));
                    $this->_view->customer = $customer;
                    $this->_view->checkOutPageUrl = $this->_getCheckoutPage()->getUrl();
                    $params = $this->_request->getParams();
                    $pickupLocationAddresses = Store_Mapper_PickupLocationConfigMapper::getInstance()->getUserAddressByUserId($customerId);
                    if(isset($params['shippingAddress'])){
                        $shippingAddress = Tools_ShoppingCart::getInstance()->getAddressById($params['shippingAddress']);
                        $this->_view->shippingForm->populate($shippingAddress);
                        $this->_view->pickupForm = false;
                    }
                    $this->_view->pickupLocationAddresses = $pickupLocationAddresses;
                    $this->_view->pickAddress = true;
                }
            }

			$this->_view->shippingForm->setAction($this->_view->actionUrl);

			$this->_view->shippingForm->populate(array(
				'notes' => Tools_ShoppingCart::getInstance()->getNotes()
			));
		}

        $listMasksMapper = Application_Model_Mappers_MasksListMapper::getInstance();
        $this->_view->mobileMasks = $listMasksMapper->getListOfMasksByType(Application_Model_Models_MaskList::MASK_TYPE_MOBILE);
        $this->_view->desktopMasks = $listMasksMapper->getListOfMasksByType(Application_Model_Models_MaskList::MASK_TYPE_DESKTOP);
		$this->_view->shoppingConfig = $this->_shoppingConfig;

        $configMapper = Application_Model_Mappers_ConfigMapper::getInstance();
        $configData = $configMapper->getConfig();

        if(!empty($configData['googleApiKey'])){
            $this->_view->googleApiKey = $configData['googleApiKey'];
        }

        $session = Zend_Registry::get('session');

        $locale = (isset($session->locale)) ? $session->locale : Zend_Registry::get('Zend_Locale');
        $this->_view->locale = $locale->getLanguage();

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
            if (Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('throttleTransactions') === 'true' && Tools_Misc::checkThrottleTransactionsLimit() === false) {
                return '<div id="payment-zone" data-throttle="1"><p class="payment-zone-message">'.$this->_translator->translate('Our transaction limit for today has exceeded.').'</p></div>';
            };

			return '<div id="payment-zone">' . $parser->parse() . '</div>';
		}
	}

	protected function _renderShippingMethods() {
		if (false !== ($freeShipping = $this->_qualifyFreeShipping())) {
			return $freeShipping;
		}

        if (false !== ($shippingRestriction = $this->_qualifyShippingRestriction())) {
            return $shippingRestriction;
        }

		$shippingServices = Models_Mapper_ShippingConfigMapper::getInstance()->fetchByStatus(Models_Mapper_ShippingConfigMapper::STATUS_ENABLED);
		if (!empty($shippingServices)) {
			$shippingServices = array_map(function ($shipper) {
				return !in_array($shipper['name'], array(Shopping::SHIPPING_TRACKING_URL, Shopping::SHIPPING_MARKUP, Shopping::SHIPPING_PICKUP, Shopping::SHIPPING_FREESHIPPING, Shopping::ORDER_CONFIG, Shopping::SHIPPING_RESTRICTION_ZONES)) ? array(
					'name'  => $shipper['name'],
					'title' => isset($shipper['config']) && isset($shipper['config']['title']) ? $shipper['config']['title'] : null
				) : null;
			}, $shippingServices);

			$shippingServices = array_values(array_filter($shippingServices));

			if (sizeof($shippingServices) === 1 && $shippingServices[0]['name'] === Shopping::SHIPPING_FLATRATE) {
				if (false !== ($flatrate = $this->_qualifyFlatRateOnly())) {
					return $flatrate;
				} else {
					$shippingServices = null;
				}
			}
		}

        if (!empty($this->_shoppingConfig['skipSingleShippingResult'])) {
            if (false !== ($singleShipmentResult = $this->_qualifySingleShippingServiceResult())) {
                return $singleShipmentResult;
            }
        }

		$this->_view->shoppingConfig = $this->_shoppingConfig;
		$this->_view->shippers = $shippingServices;
        $this->_view->checkOutPageUrl = $this->_getCheckoutPage()->getUrl();

		return $this->_view->render('checkout/shipping_methods.phtml');
	}

	protected function _qualifyFreeShipping() {
		$cart = Tools_ShoppingCart::getInstance();
		$shippingAddress = $cart->getAddressById($cart->getShippingAddressKey());
		$result = false; //flag if any kind of free shipping applied
		//checking if freeshipping is enabled and eligible for this order
		if (!empty($shippingAddress)) {
			//check if free shipping coupons was provided
			$couponStatus = false;
            if (!is_null($cart->getCoupons())) {
				$fsCoupons = Tools_CouponTools::filterCoupons($cart->getCoupons(), Store_Model_Coupon::COUPON_TYPE_FREESHIPPING);
				if (!empty($fsCoupons)) {
					$result = Tools_CouponTools::processFreeshippingCoupon(reset($fsCoupons));
                    $couponStatus = true;
				}
			}

            if(!$couponStatus){
                $freeShipping = Models_Mapper_ShippingConfigMapper::getInstance()->find(Shopping::SHIPPING_FREESHIPPING);
                if ($freeShipping && (bool)$freeShipping['enabled'] && isset($freeShipping['config']) && !empty($freeShipping['config'])) {
                    $cartAmount = $cart->calculateCartPrice();
                    $cartContent = $cart->getContent();
                    if(isset($freeShipping['config']['errormessage']) && $freeShipping['config']['errormessage'] != ''){
                        $this->_view->freeShippingErrorMessage = $freeShipping['config']['errormessage'];
                    }
                    $quantityOfCartProducts = count($cartContent);
                    $freeShippingProductsQuantity = 0;
                    if (is_array($cartContent) && !empty($cartContent)) {
                        foreach ($cartContent as $cartItem) {
                            if ($cartItem['freeShipping'] == 1) {
                                $freeShippingProductsQuantity += 1;
                            }
                        }
                    }
                    if ($cartAmount > $freeShipping['config']['cartamount'] || $freeShippingProductsQuantity == $quantityOfCartProducts) {
                        $deliveryType = $this->_shoppingConfig['country'] == $shippingAddress['country'] ? Forms_Shipping_FreeShipping::DESTINATION_NATIONAL : Forms_Shipping_FreeShipping::DESTINATION_INTERNATIONAL;

                        $freeShippingFlag = false;
                        if ($freeShipping['config']['destination'] === Forms_Shipping_FreeShipping::DESTINATION_BOTH
                            || $freeShipping['config']['destination'] === $deliveryType
                        ) {
                            $freeShippingFlag = true;
                        }elseif($freeShipping['config']['destination'] > 0){
                            $zoneId = Tools_Tax_Tax::getZone($shippingAddress, false);
                            if($zoneId == $freeShipping['config']['destination']){
                                $freeShippingFlag = true;
                            }
                        }

                        if($freeShippingFlag){
                            $cart->setShippingData(array(
                                'service' => Shopping::SHIPPING_FREESHIPPING,
                                'type'    => '',
                                'price'   => 0
                            ));

//							$cart->calculate(true);
                            $cart->save()->saveCartSession(null);

                            $result = true;
                        }
                    }
                }
            }
            $successMessage =  Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('checkoutShippingSuccessMessage');
			if ($result === true) {
				return '<h3>' . $successMessage . '</h3>' .
				$this->_renderPaymentZone();
			}

		}

		return false;
	}

	protected function _qualifyFlatRateOnly() {
		try {
			$flatratePlugin = Tools_Factory_PluginFactory::createPlugin(Shopping::SHIPPING_FLATRATE);

			$result = $flatratePlugin->calculateAction(true);

			if (isset($result['price']) && !empty($result['price'])) {
				$cart = Tools_ShoppingCart::getInstance();
				$cart->setShippingData(array(
					'service' => Shopping::SHIPPING_FLATRATE,
					'type'    => isset($result['type']) ? $result['type'] : Shopping::SHIPPING_FLATRATE,
					'price'   => $result['price']
				));
				$cart->calculate(true);
				$cart->save()->saveCartSession(null);
				return $this->_renderPaymentZone(); // returning only positive result
			}
		} catch (Exception $e) {
			Tools_System_Tools::debugMode() && error_log($e->getMessage());
			return false;
		}

		return false;
	}

    /**
     * Analyze pickup locations on checkout
     * Search by pickup location id and use the same city and country
     * or
     * by provided information about user location
     *
     * @throws Exceptions_SeotoasterPluginException
     */
    public function getPickupLocationsAction()
    {
        if ($this->_request->isPost()) {
            $locationSearch = filter_var($this->_request->getParam('locationAddress'), FILTER_SANITIZE_STRING);
            if (!$locationSearch) {
                $this->_responseHelper->fail('');
            }
            $searchByLocationId = false;
            if (is_numeric($locationSearch)) {
                $searchByLocationId = true;
            }
            if (!$searchByLocationId) {
                $locationCoordinates = Tools_Geo::getMapCoordinates($locationSearch);
                if ($locationCoordinates['lat'] === null || $locationCoordinates['lng'] === null) {
                    $this->_responseHelper->fail('');
                }
            }

            $cartContent = Tools_ShoppingCart::getInstance();
            if (!empty($cartContent)) {
                $pickupSettings = Models_Mapper_ShippingConfigMapper::getInstance()->find(Shopping::SHIPPING_PICKUP);
                if (!$pickupSettings || !isset($pickupSettings['config'])) {
                    throw new Exceptions_SeotoasterPluginException($this->_translator->translate('pickup not configured'));
                }
                switch ($pickupSettings['config']['units']) {
                    case Shopping::COMPARE_BY_AMOUNT:
                        $comparator = $cartContent->getTotal();
                        break;
                    case Shopping::COMPARE_BY_WEIGHT:
                        $comparator = $cartContent->calculateCartWeight();
                        break;
                }
                $cartFullWeight = $cartContent->calculateCartWeight();
                $result = array();
                $pickupLocationConfigMapper = Store_Mapper_PickupLocationConfigMapper::getInstance();
                $pickupLocationLinks = false;

                if (!empty($this->_shoppingConfig['pickupLocationLinks'])) {
                    $pickupLocationLinks = true;
                }

                if (!$searchByLocationId) {
                    $locationsRadius = self::$_pickupLocationRadius;
                    if (!empty($this->_shoppingConfig['additionalPickupRadius'])) {
                        array_unshift($locationsRadius, $this->_shoppingConfig['additionalPickupRadius']);
                    }

                    foreach ($locationsRadius as $key => $radius) {
                        $radiusDiffValue = $radius / 111;
                        $radiusDiffValue = number_format($radiusDiffValue, 7, '.', '');
                        $userLatitude = $locationCoordinates['lat'];
                        $userLongitude = $locationCoordinates['lng'];
                        $coordinates = array(
                            'latitudeStart' => $userLatitude - $radiusDiffValue,
                            'latitudeEnd' => $userLatitude + $radiusDiffValue,
                            'longitudeStart' => $userLongitude - $radiusDiffValue,
                            'longitudeEnd' => $userLongitude + $radiusDiffValue
                        );
                        $result = $pickupLocationConfigMapper->getLocations(
                            $comparator,
                            false,
                            $coordinates,
                            $cartFullWeight,
                            false,
                            array(),
                            $userLatitude,
                            $userLongitude,
                            $pickupLocationLinks
                        );
                        if (!empty($result)) {
                            break;
                        }
                    }
                } else {
                    $initialLocation = Store_Mapper_PickupLocationMapper::getInstance()->find($locationSearch);
                    if ($initialLocation instanceof Store_Model_PickupLocation) {
                        $result = $pickupLocationConfigMapper->getLocations(
                            $comparator,
                            false,
                            array(),
                            $cartFullWeight,
                            false,
                            array(
                                'shpl.city' => $initialLocation->getCity(),
                                'shpl.country' => $initialLocation->getCountry()
                            )
                        );
                    } else {
                        $this->_responseHelper->fail('');
                    }
                }
                if (!empty($result)) {
                    $multiplyPickupInfoDays = (isset($this->_shoppingConfig['multiplyPickupInfoDays']) ? $this->_shoppingConfig['multiplyPickupInfoDays'] : '');
                    $result = array_map(
                        function ($pickupLocation) use ($comparator, $multiplyPickupInfoDays) {
                            $pickupLocation['working_hours'] = unserialize($pickupLocation['working_hours']);
                            $pickupLocation['comparator'] = $comparator;
                            $pickupLocation['multiplyPickupInfoDays'] = $multiplyPickupInfoDays;
                            return $pickupLocation;
                        },
                        $result
                    );
                    if($searchByLocationId){
                        $userLatitude = '';
                        $userLongitude = '';
                    }

                    $locationsLinks = array();

                    if(!empty($pickupLocationLinks) && !empty($this->_shoppingConfig['pickupLocationLinksLimit'])) {
                        $pickupLocationLinksLimit = (int) $this->_shoppingConfig['pickupLocationLinksLimit'];

                        if($pickupLocationLinksLimit) {
                            foreach ($result as $key => $location) {
                                if($key < $pickupLocationLinksLimit) {
                                    $locationsLinks[] = array(
                                      'id' => $location['id'],
                                      'name' => htmlspecialchars($location['name'], ENT_QUOTES, 'UTF-8')
                                    );
                                }
                            }
                        }
                    }

                    $result[] = array('userLocation' => true, 'lat' => $userLatitude, 'lng' => $userLongitude);
                    $this->_responseHelper->success(
                        array(
                            'result' => $result,
                            'userLocation' => array('lat' => $userLatitude, 'lng' => $userLongitude),
                            'locationsLinks' => $locationsLinks
                        )
                    );
                }
            }
            $this->_responseHelper->fail('');
        }
    }

    /**
     * Calculate pickup location with tax
     */
    public function pickupLocationTaxAction()
    {
        if ($this->_request->isPost()) {
            $locationId = filter_var($this->_request->getParam('locationId'), FILTER_SANITIZE_NUMBER_INT);
            $price = filter_var($this->_request->getParam('price'), FILTER_SANITIZE_STRING);
            $cartContent = Tools_ShoppingCart::getInstance();
            if (!empty($cartContent)) {
                $pickupSettings = Store_Mapper_PickupLocationMapper::getInstance()->find($locationId);
                $correctCurrency = isset($this->_shoppingConfig['currency']) ? $this->_shoppingConfig['currency'] : self::DEFAULT_CURRENCY_NAME;
                $currencySymbol = $this->_currency->getSymbol($correctCurrency, self::DEFAULT_LOCALE);
                if (!empty($pickupSettings)) {
                    $result = $pickupSettings->toArray();
                    $countries = Tools_Geo::getCountries(true);
                    $address = Tools_Misc::clenupAddress($result);
                    $address['country'] = array_search($address['country'], $countries);
                    $shippingTax = Tools_Tax_Tax::calculateShippingTax($price, $address);

                    $result['working_hours'] = unserialize($result['workingHours']);
                    $result['withTax'] = '';
                    $result['price'] = $price + $shippingTax;
                    $result['currency'] = $currencySymbol;
                    $multiplyPickupInfoDays = (isset($this->_shoppingConfig['multiplyPickupInfoDays']) ? $this->_shoppingConfig['multiplyPickupInfoDays'] : '');
                    $result['multiplyPickupInfoDays'] = $multiplyPickupInfoDays;
                    $this->_responseHelper->success($result);
                }
            }
        }
    }

    private function _normalizeMobilePhoneNumber($form) {
        if(isset($form['mobile']) && !empty($form['mobile'])) {
            $countryMobileCode = Zend_Locale::getTranslation($form['mobilecountrycode'], 'phoneToTerritory');
            $form['mobile'] = preg_replace('~\D~ui', '', $form['mobile']);
            $mobileNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($form['mobile'], $countryMobileCode);
            if ($mobileNumber !== false) {
                $form['mobile_country_code_value'] = '+'.$countryMobileCode;
            }
        }

        if(isset($form['phone']) && !empty($form['phone'])) {
            $countryPhoneCode = Zend_Locale::getTranslation($form['phonecountrycode'], 'phoneToTerritory');
            if (empty($form['phone'])) {
                $form['phone'] = '';
            } else {
                $form['phone'] = preg_replace('~\D~ui', '', $form['phone']);
            }
            $phoneNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($form['phone'], $countryPhoneCode);
            if ($phoneNumber !== false) {
                $form['phone_country_code_value'] = '+' . $countryPhoneCode;
            }
        }

        return $form;
    }


    /**
     * Return pickup locations with distinct cities and countries
     *
     * @return array|mixed
     * @throws Exceptions_SeotoasterPluginException (if pickup locations not configured)
     */
    public static function getPickupLocationCities()
    {
        $cartContent = Tools_ShoppingCart::getInstance();
        if (!empty($cartContent)) {
            $pickupSettings = Models_Mapper_ShippingConfigMapper::getInstance()->find(Shopping::SHIPPING_PICKUP);
            if (!$pickupSettings || !isset($pickupSettings['config'])) {
                throw new Exceptions_SeotoasterPluginException('pickup not configured');
            }
            switch ($pickupSettings['config']['units']) {
                case Shopping::COMPARE_BY_AMOUNT:
                    $comparator = $cartContent->getTotal();
                    break;
                case Shopping::COMPARE_BY_WEIGHT:
                    $comparator = $cartContent->calculateCartWeight();
                    break;
            }
            $cartFullWeight = $cartContent->calculateCartWeight();
            $pickupLocationConfigMapper = Store_Mapper_PickupLocationConfigMapper::getInstance();
            $result = $pickupLocationConfigMapper->getLocations($comparator, false, array(), $cartFullWeight, true);
            if (!empty($result)) {
                return $result;
            }
            return array();
        }

    }

    /**
     * Analyze if shipping accepted for user destination
     *
     * @return bool
     */
    private function _qualifyShippingRestriction()
    {
        $restrictionSettings = Models_Mapper_ShippingConfigMapper::getInstance()->find(Shopping::SHIPPING_RESTRICTION_ZONES);
        if (empty($restrictionSettings['config']) || empty($restrictionSettings['enabled'])) {
            return false;
        }
        $cart = Tools_ShoppingCart::getInstance();
        $shippingAddress = $cart->getAddressById($cart->getShippingAddressKey());
        $shippingRestricted = true;

        if (!empty($shippingAddress)) {
            $deliveryType = Forms_Shipping_FreeShipping::DESTINATION_INTERNATIONAL;
            if ($this->_shoppingConfig['country'] == $shippingAddress['country']) {
                $deliveryType = Forms_Shipping_FreeShipping::DESTINATION_NATIONAL;
            }
            if (empty($restrictionSettings['config']['restrictDestination'])) {
                $shippingRestricted = false;
            }
            if (!empty($restrictionSettings['config']['restrictDestination']) && $restrictionSettings['config']['restrictDestination'] === $deliveryType) {
                $shippingRestricted = false;
            }

            if(!empty($restrictionSettings['config']['restrictZones'])) {
                $zoneIds = $restrictionSettings['config']['restrictZones'];
                if (!empty($zoneIds)) {
                    $currentZoneId = Tools_Tax_Tax::getZone($shippingAddress, false);
                    if (in_array($currentZoneId, $zoneIds)) {
                        $shippingRestricted = false;
                    }
                }
            }

        }
        if ($shippingRestricted) {
            if (!empty($restrictionSettings['config']['restrictionMessage'])) {
                return $restrictionSettings['config']['restrictionMessage'];
            } else {
                return $this->_translator->translate('Sorry, we can\'t ship to your location at this time');
            }
        }
        return $shippingRestricted;
    }

    /**
     * Parse custom fields
     *
     * @param string $customFields fields in format custom-fields=social|First label,your_id|Second label
     * @return array
     */
    private function _parseCustomFieldsData($customFields)
    {
        $fieldsData = array();
        $customFields = explode(',', str_replace('custom-fields=', '', $customFields));
        if (!empty($customFields)) {
            foreach ($customFields as $fieldData) {
                $fieldInfo = explode('|', $fieldData);
                $fieldName = strtolower(preg_replace('~[^ \w]~', '',
                    filter_var($fieldInfo[0], FILTER_SANITIZE_STRING)));
                $fieldsData[$fieldName] = $fieldInfo[1];
            }
        }

        return $fieldsData;
    }

    /**
     * Add additional fields for form
     *
     * @param array $fieldsInfo
     * @param $form
     * @return mixed
     */
    private function _addAdditionalFormFields(array $fieldsInfo, $form)
    {
        foreach ($fieldsInfo as $fieldName => $fieldLabel) {
            $form->addElement('text', filter_var($fieldName, FILTER_SANITIZE_STRING));
        }

        return $form;
    }


    /**
     * Analyze if only one shipment in the service enabled and one result returned
     *
     * @return bool
     */
    private function _qualifySingleShippingServiceResult()
    {
        try {
            $shippingServices = Models_Mapper_ShippingConfigMapper::getInstance()->fetchByStatus(Models_Mapper_ShippingConfigMapper::STATUS_ENABLED);
            if (!empty($shippingServices)) {
                $shippingServices = array_map(function ($shipper) {
                    return !in_array($shipper['name'], array(
                        Shopping::SHIPPING_TRACKING_URL,
                        Shopping::SHIPPING_MARKUP,
                        Shopping::SHIPPING_PICKUP,
                        Shopping::SHIPPING_FREESHIPPING,
                        Shopping::ORDER_CONFIG,
                        Shopping::SHIPPING_RESTRICTION_ZONES
                    )) ? array(
                        'name' => $shipper['name'],
                        'title' => isset($shipper['config']) && isset($shipper['config']['title']) ? $shipper['config']['title'] : null
                    ) : null;
                }, $shippingServices);
            }

            $shippingServices = array_filter($shippingServices);
            if (empty($shippingServices) || count($shippingServices) > 1) {
                return false;
            }

            $shippingService = current($shippingServices);
            $result = Tools_System_Tools::firePluginMethodByPluginName($shippingService['name'], 'calculateShipping', array(), false);

            if (!empty($result) && count($result) === 1 && empty($result['error'])) {
                $result = current($result);
                $cart = Tools_ShoppingCart::getInstance();

                $shippingData = array(
                    'service' => $shippingService['name'],
                    'type'    => isset($result['type']) ? $result['type'] : Shopping::SHIPPING_FLATRATE,
                    'price'   => $result['price']

                );
                if (!empty($result['service_id'])) {
                    $shippingData['service_id'] = $result['service_id'];
                }

                if (!empty($result['service_id'])) {
                    $shippingData['availability_days'] = $result['availability_days'];
                }

                if (!empty($result['service_id'])) {
                    $shippingData['service_info'] = $result['service_info'];
                }
                $cart->setShippingData($shippingData);
                $cart->calculate(true);
                $cart->save()->saveCartSession(null);
                $this->_checkoutSession->returnAllowed = array(
                    self::STEP_LANDING,
                    self::STEP_SHIPPING_OPTIONS,
                    self::STEP_SHIPPING_METHOD
                );
                return $this->_renderPaymentZone();
            }
        } catch (Exception $e) {
            Tools_System_Tools::debugMode() && error_log($e->getMessage());
            return false;
        }

        return false;
    }



//	@TODO implement widget maker
//	public static function getWidgetMakerContent(){
//		return array('title'=> 'Store: Checkout', 'content' => '');
//	}
}
