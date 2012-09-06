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
        processFormErrors: function(errors){
            var self = this;
            _.each(errors, function(error, name){
                self.form.find('#'+name).addClass('notvalid');
            });
        },
        buildAddressPreview: function(form){
            var preview = this.$el.find('div.cart-address-preview');
            if (preview.length){
                var formData = form.serializeArray(),
                    jsonData = {};
                _.each(formData, function(elem){
                    jsonData[elem.name] = elem.value;
                });
                preview.html(_.template(
                   '<%- firstname %> <%- lastname %>, <%- country %><br />' +
                   '<%- city %> <%- $("select#state").find("option[value="+state+"]").attr("label") %> <%- zip %><br/>' +
                   '<%- phone && phone+"," %> <%- mobile %>',
                    jsonData
                ));
                console.log(JSON.stringify(jsonData));
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
                            name:  shipper.title || shipper.name
                        }));
                        return false;
                    }
                    $.ajax({
                        url: '/plugin/'+shipper.name+'/run/calculate/',
                        data: {cartId: this.cartId},
                        dataType: 'json',
                        success: function(response){
                            form.find('ul#'+shipper.name+'-methods').html(self.templates.shipperMethods({
                                services: response,
                                name:  shipper.title || shipper.name
                            }));
                        },
                        error: function(){
                            form.find('ul#'+shipper.name+'-methods').html(shipper.name+' service in currently unavailable.')
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
                });
            } else {
               showMessage('Something went wrong. Please try again later', true);
            }
        },
        submitShipper: function(e){
            e.preventDefault();
            var form = $(e.currentTarget);
            console.log(form.find('input:radio[name=shipper]:selected'));
            if (!form.find('input[name=shipper]:checked')){
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
            $('form#shipper-select').remove();
            $('#payment-zone').html(html);
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