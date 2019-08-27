E-commerce shopping cart plugin for the SEOTOASTER 2.0

{$cartitem:price[:price_type:nocurrency]} - Displays the price of one product or several products in the cart.
price_type - total price| price for the unit of the product (total|unit).
nocurrency - displayed price without currency
{$cartitem:photo[:img_size]} - Displays a preview image of the product.
 img-size - output size of the pre-loaded product image (product|small|medium|large|original).
{$cartitem:name} - Displays the name of the product in the cart.
{$cartitem:options} - Displays the selected product options in the cart.
{$cartitem:sku} - Displays product SKU (stock keeping unit) in the cart.
{$cartitem:mpn} - Displays the MPN (manufacture product number) in the cart.
{$cartitem:description[:desc_size]} - Displays a brief or a full description of the product in the cart.
desc_size -  the type of product description output (short|full|maximum number of output symbols).
{$cartitem:tax} - Displays product tax in the cart.
{$cartitem:weight[:weight_type]} - Displays the weight of the product for one unit or for a package in the cart.
weight_type - output type for package | product unit respectively (total|unit).
{$cartitem:qty} - Displays product number field in the cart.
{$cartitem:remove} - Displays a button that allows you to remove a product from the cart.


Magic spaces:

MAGICSPACE: paymentgateways
{paymentgateways}{/paymentgateways} - used to specify a place where payment gateways will be displayed at the checkout
It provides a mechanism to display payment gateways at the latest stage when taxes and shipping was applied to final amount of the purchase
Ex: {paymentgateways}
        {$plugin:paypal:creditcard}
    {/paymentgateways}

MAGICSPACE: toastercart
{toastercart}{/toastercart} - used to specify a place where single cart items will be displayed
Inside this magic space you can put cartitem widgets
Ex: {toastercart}
        {$cartitem:name} {$cartitem:sku} {$cartitem:price}
    {/toastercart}

MAGICSPACE: cartsummary
{cartsummary}{/cartsummary} - used to specify a place where single cartsummary widgets items will be displayed

{cartsummary}
    {$cartsummary:subtotal}
    {$cartsummary:discount}
    {$cartsummary:shipping}
    {$cartsummary:totaltax}
    {$cartsummary:total}
{/cartsummary}
