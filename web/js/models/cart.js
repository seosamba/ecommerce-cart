define([
	//'jquery',
	'underscore',
	'backbone',
	'collections/items'
], function(_, Backbone, CartItems) {

	var CartModel = Backbone.Model.extend({
		collection: null,
		initialize: function() {
			this.collection = new CartItems();
		},
		updateItemQty: function(sid, qty) {
			var cartItem = this._getItem(sid);
			if(typeof cartItem != 'undefined') {
				cartItem.set({'qty':cartItem.get('qty') + (qty - cartItem.get('qty'))});
				showSpinner();
				cartItem.save(null, {success:hideSpinner});
			}
		},
		_getItem: function(sid) {
			return _.find(this.collection.toArray(), function(item) {
				return (item.get('sid') == sid) ;
			});
		},
		removeItem: function(sid) {
			var cartItem = this._getItem(sid);
			if(typeof cartItem != 'undefined') {
				this.collection.remove(cartItem);
			}
		}
	});
	return CartModel;
});
