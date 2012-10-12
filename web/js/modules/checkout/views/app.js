/**
 * @author Pavel Kovalyov <pavlo.kovalyov@gmail.com>
 */
define([
    'backbone',
    'text!../templates/shippersform.html',
    'text!../templates/shippermethods.html',
    'text!../templates/addresspreview.html'
], function(
    Backbone,
    ShippersFormTmpl, ShipperMethodsTmpl, AddressPreviewTmpl
    ){
    var AppView = Backbone.View.extend({
        el: $('#checkout-widget'),
        events: {
            'submit form#checkout-user-address': 'submitAddress',
            'click a#checkout-action': 'toggleCheckoutStart',
            //'submit form#checkout-signup': 'signupAction',
            'click input#edit-cart-btn': function(){
                this.switchCheckoutLock(false);
            }
        },
        templates: {
            error: '',
            addressPreview: _.template(AddressPreviewTmpl),
            shipperMethods: _.template(ShipperMethodsTmpl)
        },
        websiteUrl: $('#website_url').val(),
        initialize: function(){
            $(document).on('click', '#edit-cart-btn', function() {
                $('.shipping-address-header').show();
                $('#checkout').attr("disabled", false);
                $('#shipper-select').remove();
                $('#checkout-user-address').show();
                $('#checkout-widget-address-preview').hide();
                $('#shipping-type-selected').hide();
                switchCheckoutLock(false);
            });

            $('.checkout-button').show();

            $.fn.addressChain.options.url = this.websiteUrl + 'api/store/geo/type/state';
            this.form = this.$el.find('form');
            if (this.form.hasClass('address-form')){
                this.form.addressChain();
            }
            this.$el.ajaxComplete(function(event, xhr, options){
            });
        },
        submitAddress: function(e){
            e.preventDefault();
            $('.shipping-address-header').hide();
            $('#checkout').attr("disabled", 'disabled');
            var self    = this,
                form    = $(e.currentTarget),
                valid   = true;

            $('.required:input', form).each(function(){
                if (_.isEmpty($(this).val())){
                    valid = false;
                    $(this).addClass('notvalid');
                } else {
                    $(this).removeClass('notvalid');
                }
            });

            if (!valid) {
                showMessage('Missing required fields', true);
                $('.notvalid:input:first', form).focus();
                return false;
            }

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                dataType: 'jsonp'
            });
        },
        toggleCheckoutStart: function() {
            this.$('.checkout-button').toggle();
            this.$('#checkoutflow').toggle();
        },
        signupAction: function(e) {
            e.preventDefault();
            var self    = this,
                form    = $(e.currentTarget),
                valid   = true;

            $('.required:input', form).each(function(){
                if (_.isEmpty($(this).val())){
                    valid = false;
                    $(this).addClass('notvalid');
                } else {
                    $(this).removeClass('notvalid');
                }
            });

            if (!valid) {
                showMessage('Missing required fields', true);
                $('.notvalid:input:first', form).focus();
                return false;
            }

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                dataType: 'jsonp'
            });
        },
        renderAddress: function(customer) {
            var form = $('form#checkout-signup');
            this.switchCheckoutLock(true).buildAddressPreview(form);
            form.hide();

        },
        processFormErrors: function(errors){
            var self = this;
            _.each(errors, function(error, name){
                self.form.find('#'+name).addClass('notvalid');
            });
        },
        buildAddressPreview: function(form){
            var preview = this.$el.find('div#cart-address-preview');
            var cartpreview = $('#cart-address-preview');
            if(cartpreview.length){
            //if (preview.length){
                var formData = form.serializeArray(),
                    jsonData = {};
                _.each(formData, function(elem){
                    jsonData[elem.name] = elem.value;
                });
                preview.html(this.templates.addressPreview(jsonData));
                $('#cart-address-preview').html(this.templates.addressPreview(jsonData));
            }
            return this;
        },
        buildShipperForm: function(response){
            var self = this;
            this.switchCheckoutLock(true).buildAddressPreview($('form#checkout-user-address'));
            if (_.has(response, 'shippers')) {
                var form = $(_.template(ShippersFormTmpl, response));
                form.on('submit', this.submitShipper).appendTo(this.$el);
                self.shipperXHRCount = response.shippers.length;
                _.each(response.shippers, function(shipper){
                    if (shipper.name === 'pickup') {
                        self.shipperXHRCount--;
                        form.find('ul#'+shipper.name+'-methods').html(self.templates.shipperMethods({
                            services: [{
                                type: shipper.title || shipper.name,
                                price: 0
                            }],
                            name: shipper.name
                        }));
                        return false;
                    }
                    if (shipper.name != 'markup') {
                        $.ajax({
                            url: '/plugin/'+shipper.name+'/run/calculate/',
                            data: {cartId: this.cartId},
                            dataType: 'json',
                            success: function(response){
                                form.find('ul#'+shipper.name+'-methods').html(self.templates.shipperMethods({
                                    services: response,
                                    name: shipper.name
                                }));
                            },
                            error: function(){
                                form.find('ul#'+shipper.name+'-methods').html(shipper.name+' service in currently unreachable.')
                            }
                        }).done(function(){
                            self.shipperXHRCount--;
                            if (self.shipperXHRCount === 0){
                                var form = $('#shipper-select');
                                if (form.find('input[name=shipper]').length){
                                    $('#shipper-select input:submit').fadeIn();
                                } else {
                                    $('#shipper-select input:submit').remove();
                                }
                            }
                        });
                    }
                    });
            } else {
               showMessage('Something went wrong. Please try again later', true);
            }
        },
        submitShipper: function(e){
            e.preventDefault();
            var form = $(e.currentTarget);

            if (!form.find('input[name=shipper]:checked').size()){
                showMessage('Select shipping method', true);
                return false;
            }
            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                success: CartCheckout.renderPaymentZone
            });
        },
        renderPaymentZone: function(html){
            var selectedShippingMethod = $("form#shipper-select input[type='radio']:checked").val();
            var selectedShippingName = $("form#shipper-select input[type='radio']:checked").parent().find('.shipping-method-title').html();
            var shippingMethodRegex=new RegExp("::.*");
            var shippingMethod = selectedShippingMethod.replace(shippingMethodRegex, '');
            var shippingType = $('#shipping-type-selected');
            if(shippingType.length) {
                shippingType.replaceWith('<div id="shipping-type-selected"><span class="checkout-right-title">Shipping method: '+shippingMethod+'</span><p>'+selectedShippingName+'</p></div>');
            }
            $('form#shipper-select').remove();
            var pz = $('#payment-zone');
            if (!pz){
                pz = $('<div id="payment-zone"></div>').insertAfter(this);
            }
            pz.html(html);
            refreshCartSummary();
        },
        switchCheckoutLock: function(lock){
            lock = !!lock;

            if (lock) {
                $('#checkout-user-address').hide();
                $('.product-qty').attr('disabled', 'disabled');
                $('.remove-item').hide();
                $('#payment-zone').show();
                $('#checkout-widget-address-preview').slideDown();
            } else {
                $('#checkout-user-address').show();
                $('.product-qty').removeAttr('disabled');
                $('.remove-item').show();
                $('#shipper-select').remove();
                $('#payment-zone').empty().hide();
                $('#checkout-widget-address-preview').slideUp().find('div.cart-address-preview').empty();
            }

            return this;
        }
    });

    return AppView;
});