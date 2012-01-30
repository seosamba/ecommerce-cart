require.config({
	paths: {
		jquery: '/system/js/external/jquery/jquery',
		underscore: 'libs/underscore/underscore-min',
		backbone: 'libs/backbone/backbone-min'
	}

});

require([
	'application'
], function(App) {
	App.initialize();
});
