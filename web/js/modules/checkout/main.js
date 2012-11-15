/**
 * @author Pavel Kovalyov <pavlo.kovalyov@gmail.com>
 */
require.config({
    paths: {
        'underscore': '../../../../../shopping/web/js/libs/underscore/underscore-min',
        'backbone'  : '../../../../../shopping/web/js/libs/backbone/backbone-min'
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
    if (!window.Toastr){
        window.Toastr = {}
    }

    $(function(){
        Toastr.CartCheckout = new AppView();
    });

    return Toastr;
});