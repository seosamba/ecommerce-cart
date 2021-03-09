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

    protected function  _init() {
        parent::_init();
        $this->_websiteHelper    = Zend_Controller_Action_HelperBroker::getStaticHelper('website');
        $this->_view->websiteUrl = $this->_websiteHelper->getUrl();
        $this->_cartContent      = Tools_ShoppingCart::getInstance();
        $this->_shoppingConfig   = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
        $this->_usNumericFormat  = $this->_shoppingConfig['usNumericFormat'];
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
            $subTotal = number_format(round($summary['subTotal'], 2), 2, '.', '');

            if(!empty($this->_usNumericFormat)) {
                $subTotal = number_format(round($summary['subTotal'], 2), 2);
            }
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

            $discount = number_format(round($summary['discount'], 2), 2, '.', '');

            if(!empty($this->_usNumericFormat)) {
                $discount = number_format(round($summary['discount'], 2), 2);
            }
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
            $shipping = number_format(round($summary['shipping'], 2), 2, '.', '');

            if(!empty($this->_usNumericFormat)) {
                $shipping = number_format(round($summary['shipping'], 2), 2);
            }
        }
        return $shipping;
    }

    protected function _renderTotaltax() {
        $summary = $this->_cartContent->calculate();

        $totalTax = '';
        if(isset($summary['totalTax'])) {
            $totalTax = number_format(round($summary['totalTax'], 2), 2, '.', '');

            if(!empty($this->_usNumericFormat)) {
                $totalTax = number_format(round($summary['totalTax'], 2), 2);
            }
        }
        return $totalTax;
    }

    protected function _renderTotal() {
        $summary = $this->_cartContent->calculate();

        $total = '';
        if(isset($summary['total'])) {
            $total = number_format(round($summary['total'], 2), 2, '.', '');

            if(!empty($this->_usNumericFormat)) {
                $total = number_format(round($summary['total'], 2), 2);
            }
        }
        return $total;
    }
}
