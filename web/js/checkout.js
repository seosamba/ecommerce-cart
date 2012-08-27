/**
 * @author Pavel Kovalyov <pavlo.kovalyov@gmail.com>
 */
requirejs.config({
    paths: {
        'underscore': $('#website_url').val() + 'plugins/shopping/web/js/libs/underscore/underscore-min',
        'backbone'  : $('#website_url').val() + 'plugins/shopping/web/js/libs/backbone/backbone-min'
    },
    shim: {
        underscore: {exports: '_'},
        backbone: {
            deps: ['underscore'],
            exports: 'Backbone'
        }
    }
});

require([
    'modules/checkout/main'
], function(AppView){
    window.CartCheckout = new AppView();
    return CartCheckout;
});