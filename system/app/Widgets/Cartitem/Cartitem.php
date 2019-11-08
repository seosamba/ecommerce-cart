<?php

class Widgets_Cartitem_Cartitem extends Widgets_Abstract{

	protected $_websiteHelper  = null;

	protected $_cartContent    = array();

	protected $_translator     = null;

	protected $_shoppingConfig = array();

	protected $_cacheable      = false;

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
		$sid          = array_shift($this->_options);

		if(!isset($this->_cartContent[$sid])) {
			return '';
		}

		$option       = strtolower(array_shift($this->_options));

		$rendererName = '_render' . ucfirst($option);
		if(method_exists($this, $rendererName)) {
			return $this->$rendererName($sid);
		}
		return '<span class="toastercart-item-' . $option . '">' . $this->_cartContent[$sid][$option] . '</span>';
	}

	protected function _renderWeight($sid) {
		if(isset($this->_options[0]) && $this->_options[0] == 'unit') {
			return '<span class="toastercart-item-unitweight">' . $this->_cartContent[$sid]['weight'] . '</span>';
		}
		return '<span class="toastercart-item-weight" data-sidweight="' . $sid . '">' . ($this->_cartContent[$sid]['weight'] * $this->_cartContent[$sid]['qty']) . '</span>';
	}

	protected function _renderPrice($sid) {
		$this->_view->sid = $sid;
        if (null !== ($addrId = Tools_ShoppingCart::getInstance()->getAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING))){
            $destinationAddress = Tools_ShoppingCart::getInstance()->getAddressById($addrId);
		} else {
            $destinationAddress = null;
		}
        $product = new Models_Model_Product(array(
			'price'     => $this->_cartContent[$sid]['price'],
            'taxClass'  => $this->_cartContent[$sid]['taxClass']
		));

        $taxEnabled = false;
        if(isset($this->_cartContent[$sid]['freebies']) && $this->_cartContent[$sid]['freebies'] == 1){
            $price = 0;
            $this->_view->freebies = true;
        }else{
            if(isset($this->_shoppingConfig['showPriceIncTax']) && $this->_shoppingConfig['showPriceIncTax'] === '1'){
                $taxEnabled = true;
                $taxRate = Tools_Tax_Tax::calculateProductTax($product, isset($destinationAddress) ? $destinationAddress : null, true);
                $cartItem['tax'] = Tools_Tax_Tax::calculateProductTax($product, isset($destinationAddress) ? $destinationAddress : null);
                $price = $this->_cartContent[$sid]['price'] + $cartItem['tax'];
                $this->_view->taxRate = $taxRate;
            }else{
                $price = $this->_cartContent[$sid]['price'];
            }
        }

        if(isset($this->_options[0]) && $this->_options[0] == 'unit') {
            $priceToShow              = $price;
            $this->_view->priceOption = 'unitprice';
            $discounts = array_filter(
                $this->_cartContent[$sid]['productDiscounts'],
                function ($discount) {
                    if (!empty($discount['discount'])) {
                        return $discount;
                    }
                }
            );
            $this->_view->discountList = $discounts;
        } else {
            $priceToShow              = $price * $this->_cartContent[$sid]['qty'];
            $this->_view->priceOption = 'price';
        }

		$this->_view->quantity = $this->_cartContent[$sid]['qty'];
        $this->_view->taxEnabled = $taxEnabled;


        $currency = Zend_Registry::isRegistered('Zend_Currency') ? Zend_Registry::get('Zend_Currency') : new Zend_Currency();

        $nocurrency = '';
        if(in_array('nocurrency', $this->_options)) {
            $nocurrency = 'nocurrency';
            $this->_view->price = number_format(round($priceToShow, 2), 2, '.', '');
        } else {
            $this->_view->price = $currency->toCurrency($priceToShow);
        }

        $this->_view->nocurrency = $nocurrency;


		return $this->_view->render('commonprice.phtml');
	}

	protected function _renderQty($sid) {
		$html = '';
        if((isset($this->_options[0]) && $this->_options[0] == 'noedit') || (Cart::$_lockCartEdit === true) || $this->_cartContent[$sid]['freebies'] == 1) {
			$html = '<span class="toastercart-item-qty">' . $this->_cartContent[$sid]['qty'] . '</span>';
		} else {
			$html = '<input type="text" class="toastercart-item-qty product-qty" min="0" data-sid="' . $sid . '" data-pid="' . $this->_cartContent[$sid]['id'] . '" value="' . $this->_cartContent[$sid]['qty'] . '" />';
		}
		return $html;
	}

	protected function _renderPhoto($sid) {
        $websiteHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('website');
        $websiteUrl = (Zend_Controller_Action_HelperBroker::getStaticHelper('config')->getConfig(
            'mediaServers'
        ) ? Tools_Content_Tools::applyMediaServers($websiteHelper->getUrl()) : $websiteHelper->getUrl());

		if(isset($this->_options[0])) {
			$folder = $this->_options[0];
		} else {
			$folder = 'product';
		}
		$photoSrc = $this->_cartContent[$sid]['photo'];

        if (preg_match('~^https?://.*~', $photoSrc)) {
            $tmp = parse_url($photoSrc);
            $path = explode('/', trim($tmp['path'], '/'));
            if (is_array($path)) {
                $imgName = array_pop($path);
                $guessSize = array_pop($path);
                if (in_array($guessSize, array('small', 'medium', 'large', 'original')) && $guessSize !== $folder) {
                    $guessSize = $folder;
                }
                $photoSrc = $tmp['scheme'] . '://' . implode(
                        '/',
                        array(
                            $tmp['host'],
                            implode('/', $path),
                            $guessSize,
                            $imgName
                        )
                    );
            }
        } else {
            $photoSrc = $websiteUrl . $websiteHelper->getMedia() . str_replace('/', '/' . $folder . '/', $photoSrc);
        }
        return '<img class="cart-product-image" src="'.$photoSrc.'" alt="' . $this->_cartContent[$sid]['name'] . '">';
	}

	protected function _renderDescription($sid) {
		if(isset($this->_options[0]) && $this->_options[0] == 'full') {
			$description = $this->_cutDescription($this->_cartContent[$sid]['description']);
			return '<span class="toastercart-item-description-full">' . $description . '</span>';
		}
		$description = $this->_cutDescription($this->_cartContent[$sid]['shortDescription']);
		return '<span class="toastercart-item-description-short">' . $description . '</span>';
	}

	protected function _renderRemove($sid) {
		if($this->_cartContent[$sid]['freebies'] == 1){
            return '';
        }
        return Cart::$_lockCartEdit === true ? '' : '<a href="javascript:;" class="remove-item error ticon-close icon16" data-sid="' . $sid . '" title="' . $this->_translator->translate('remove ') . $this->_cartContent[$sid]['name'] . $this->_translator->translate(' from the cart') . '">' . $this->_translator->translate('Remove') . '</a>';
	}

	protected function _renderOptions($sid) {
		$this->_view->cartItem   = $this->_cartContent[$sid];
		$this->_view->weightSign = $this->_shoppingConfig['weightUnit'];

		$this->_view->taxRate = 0;
		if ($this->_shoppingConfig['showPriceIncTax']) {
			$product = Models_Mapper_ProductMapper::getInstance()->find($this->_cartContent[$sid]['id']);
			if ($product instanceof Models_Model_Product){
				$addressKey = Tools_ShoppingCart::getInstance()->getAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING);
				$addressKey = is_null($addressKey) ? null : Tools_ShoppingCart::getAddressById($addressKey);
				$this->_view->taxRate = Tools_Tax_Tax::calculateProductTax($product, $addressKey, true) / 100;
			}
		}

		return $this->_view->render('options.phtml');
	}

	protected function _renderNote($sid) {
		return '<span class="toaster-item-note">' . $this->_cartContent[$sid]['note'] . '</span>';
	}

    protected function _renderMpn($sid) {
        return $this->_cartContent[$sid]['mpn'];
    }
    
	private function _cutDescription($description) {
		if(isset($this->_options[0]) && intval($this->_options[0])) {
			return Tools_Text_Tools::cutText($description, $this->_options[0]);
		} else if(isset($this->_options[1]) && intval($this->_options[1])) {
			return Tools_Text_Tools::cutText($description, $this->_options[1]);
		}
		return $description;
	}

	protected function _renderBrand($sid)
    {
        return $this->_cartContent[$sid]['brand'];
    }

}
