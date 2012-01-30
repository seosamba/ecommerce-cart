define([
	//'jquery',
	'underscore',
	'backbone',
	'models/cart'
], function(_, Backbone, CartModel) {

	var CartView = Backbone.View.extend({
		model: null,
		el         : $('#toaster-cart'),
		items      : null,
		tpl        : null,
		initialize : function() {
			this.model = new CartModel();
			this.tpl   = _.template($('#cart-template').html());
		},
		events: {
			'change .product-qty': 'updateProductQty',
			'click .remove-item': 'removeItem'
		},
		updateProductQty: function(ev) {
			this.model.updateItemQty($(ev.target).data('sid'), $(ev.target).val());
			this.render();
			this.refreshSummury();
		},
		removeItem: function(ev) {
			var cartView  = this;
			var cartModel = this.model;
			smoke.confirm('You are about to remove a product from you cart. Are you sure?', function(e) {
				if(e) {
                	$.when(cartModel.removeItem($(ev.target).parent().data('sid'))).then(function() {
						cartView.render();
						cartView.refreshSummury();
					});
				} else {

				}
			}, {classname:"errors", 'ok':'Yes', 'cancel':'No'});
		},
		render: function() {
			var cartModel = this.model;
			var cartView  = this;
			$.when(cartModel.collection.fetch()).then(function() {
				$(cartView.el).html(cartView.tpl({collection:cartModel.collection.toJSON()}));
			});
			return this;
		},
		refreshSummury: function() {
			if($('#cart-summary').length) {
				$.post('/plugin/cart/run/summary/', function(response) {
					if(!response.error) {
						$('#cart-summary').replaceWith(response.responseText);
					}
				}, 'json');
			}
		}
	});

	return CartView;
});