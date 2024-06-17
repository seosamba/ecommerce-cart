<?php

/**
 * Class Widgets_Cartsummary_Cartsummary
 */
class Widgets_Cartsummary_Cartsummary extends Widgets_Abstract
{
    protected $_websiteHelper  = null;

    protected $_cartContent    = array();

    protected $_shoppingConfig = array();

    protected $_cacheable      = false;

    /**
     * @var null|Zend_Currency Zend_Currency holder
     */
    protected $_currency = null;

    protected function  _init() {
        parent::_init();
        $this->_websiteHelper    = Zend_Controller_Action_HelperBroker::getStaticHelper('website');
        $this->_cartContent      = Tools_ShoppingCart::getInstance();
        $this->_shoppingConfig   = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
        $this->_initCurrency();
    }

    private function _initCurrency() {
        $locale = Cart::DEFAULT_LOCALE;
        if (!Zend_Registry::isRegistered('Zend_Currency')) {
            if(!empty($this->_shoppingConfig['currencyCountry'])) {
                $locale = Zend_Locale::getLocaleToTerritory($this->_shoppingConfig['currencyCountry']);
            }

            $this->_currency = new Zend_Currency($locale);
        } else {
            $this->_currency = Zend_Registry::get('Zend_Currency');
        }
    }

    protected function _load() {
        if(!isset($this->_options[0])) {
            return '';
        }
        $option       = strtolower(array_shift($this->_options));

        $rendererName = '_render' . ucfirst($option);
        if(method_exists($this, $rendererName)) {
            return $this->$rendererName();
        }
    }

    protected function _renderSubtotal() {
        $summary = $this->_cartContent->calculate();
        $taxIncPrice = (bool)$this->_shoppingConfig['showPriceIncTax'];

        $subTotal = '';
        if(isset($summary['subTotal'])) {
            if(!empty($taxIncPrice)) {
                $summary['subTotal'] +=  $summary['subTotalTax'];
            }
            $subTotal = trim(preg_replace('/[^0-9.,\s]/ui', '', $this->_currency->toCurrency($summary['subTotal'])), " ");
        }
        return $subTotal;
    }

    protected function _renderDiscount() {
        $summary = $this->_cartContent->calculate();
        $taxIncPrice = (bool)$this->_shoppingConfig['showPriceIncTax'];

        $discount = '';
        if(isset($summary['discount'])) {
            if(!empty($taxIncPrice)) {
                $summary['discount'] +=  $summary['discountTax'];
            }
            $discount = trim(preg_replace('/[^0-9.,\s]/ui', '', $this->_currency->toCurrency($summary['discount'])), " ");
        }

        return $discount;
    }

    protected function _renderShipping() {
        $summary = $this->_cartContent->calculate();
        $taxIncPrice = (bool)$this->_shoppingConfig['showPriceIncTax'];

        $shipping = '';
        if(isset($summary['shipping'])) {
            if(!empty($taxIncPrice)) {
                $summary['shipping'] +=  $summary['shippingTax'];
            }
            $shipping = trim(preg_replace('/[^0-9.,\s]/ui', '', $this->_currency->toCurrency($summary['shipping'])), " ");
        }
        return $shipping;
    }

    protected function _renderTotaltax() {
        $summary = $this->_cartContent->calculate();

        $totalTax = '';
        if(isset($summary['totalTax'])) {
            $totalTax = trim(preg_replace('/[^0-9.,\s]/ui', '', $this->_currency->toCurrency($summary['totalTax'])), " ");
        }
        return $totalTax;
    }

    protected function _renderTotal() {
        $summary = $this->_cartContent->calculate();

        $total = '';
        if(isset($summary['total'])) {
            $total = trim(preg_replace('/[^0-9.,\s]/ui', '', $this->_currency->toCurrency($summary['total'])), " ");
        }
        return $total;
    }
}
