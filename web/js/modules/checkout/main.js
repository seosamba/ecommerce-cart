/**
 * @author Pavel Kovalyov <pavlo.kovalyov@gmail.com>
 */
require.config({
    paths: {
        'underscore': '/plugins/shopping/web/js/libs/underscore/underscore-min',
        'backbone'  : '/plugins/shopping/web/js/libs/backbone/backbone-min',
        'text'      : '/plugins/shopping/web/js/libs/require/text'
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
    './views/app'
], function(AppView){
    window.CartCheckout = new AppView();
    return CartCheckout;
});