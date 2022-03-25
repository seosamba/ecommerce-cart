<?php

class MagicSpaces_Cartdiscount_Cartdiscount extends Tools_MagicSpaces_Abstract
{

    protected function _run()
    {
        $cartSession = Tools_ShoppingCart::getInstance();
        $cartSessionMapper = Models_Mapper_CartSessionMapper::getInstance();
        $cartId = $cartSession->getCartId();
        if (!empty($cartId)) {
            $cartSessionModel = $cartSessionMapper->find($cartSession->getCartId());
            if ($cartSessionModel instanceof Models_Model_CartSession) {
                $discount = $cartSessionModel->getDiscount();

                if ($discount > 0) {
                    return $this->_spaceContent;
                }
            }
        }

        return '';
    }

}
