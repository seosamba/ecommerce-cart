<?php

/**
 * Class MagicSpaces_Cartsummary_Cartsummary
 */
class MagicSpaces_Cartsummary_Cartsummary extends Tools_MagicSpaces_Abstract
{
    protected function _run()
    {
        $spaceContent   = $this->_parse();
        $content = '<div id="cart-summary-magic-space">'. $spaceContent . '</div>';

        return $content;
    }
}
