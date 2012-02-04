define([
	'underscore',
	'backbone'
], function(_, Backbone) {

	var CartItem = Backbone.Model.extend({
		urlRoot: '/plugin/cart/run/cart/',
		defaults: {
			qty         : 0,
			photo       : '',
			description : '',
			name        : '',
			sid         : '',
			price       : 0,
			weight      : 0,
			tax         : 0,
			taxPrice    : 0,
			taxIncluded : false
		},
		initialize: function() {
			this.bind('remove', function() {
				showSpinner();
				this.destroy({success: hideSpinner});
			});
		}
	});

	return CartItem;

});
