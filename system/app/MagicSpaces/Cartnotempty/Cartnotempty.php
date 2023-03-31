<?php
/**
 * MAGICSPACE: cartnotempty
 *
 * {cartnotempty}{/cartnotempty}
 *
 * Class MagicSpaces_Cartnotempty_Cartnotempty
 */

class MagicSpaces_Cartnotempty_Cartnotempty extends Tools_MagicSpaces_Abstract
{

    protected function _run()
    {
        $cartStorage = Tools_ShoppingCart::getInstance();
        $cartContent = $cartStorage->getContent();
        $cartSize = sizeof($cartContent);

        if (!empty($cartSize)) {
            return $this->_spaceContent;
        }

        return '';

    }
}