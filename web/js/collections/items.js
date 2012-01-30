define([
	'underscore',
	'backbone',
	'models/item'
], function(_, Backbone, CartItem) {
	var CartItems = Backbone.Collection.extend({
		model : CartItem,
		url   : '/plugin/cart/run/cart/'
	});

	return CartItems;

});
