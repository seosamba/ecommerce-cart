define([
	//'jquery',
	'underscore',
	'backbone',
	'views/cart'
], function(_, Backbone, CartView){
	var Router = Backbone.Router.extend({
		cartView: null,
		routes: {
			''            : 'showCart',
			'cart'        : 'showCart'
			//'remove/:sid' : 'removeFromCart'
		},
		initialize: function() {
			this.cartView = new CartView();
		},
		showCart: function() {
			this.cartView.render();
		},
        removeFromCart: function() {
			console.log('Removing from cart');
		}
	});

	var initialize = function(){
		var appRouter = new Router;
		Backbone.history.start();
	};

	return {
		initialize: initialize
	};
});