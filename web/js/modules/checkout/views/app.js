/**
 * @author Pavel Kovalyov <pavlo.kovalyov@gmail.com>
 */
define([ 'backbone' ], function( Backbone ){
    var AppView = Backbone.View.extend({
        el: $('#checkout-widget'),
        events: {
            'submit .checkout-forms form.toaster-checkout': 'submitForm',
            'click a#checkout-action': 'toggleCheckoutStart',
            'click a#pickup-action': function(e){
                e.preventDefault();
                this.$('.checkout-button').hide()
                this.$('#checkout-pickup').parent().show();
            },
            'click a#shipping-action': function(e){
                e.preventDefault();
                this.$('.checkout-button').hide();
                this.$('#checkout-user-address').parent().show();
            },
            'click input#edit-cart-btn': function(){
                this.switchCheckoutLock(false);
            }
        },
        templates: {
            error: '',
            addressPreview: $('#shippingAddressPreviewTemplate').size() && _.template($('#shippingAddressPreviewTemplate').html()),
            shippersForm: $('#ShippersFormTmpl').size() && _.template($('#ShippersFormTmpl').html()),
            shipperMethods: $('#ShipperMethodsTmpl').size() && _.template($('#ShipperMethodsTmpl').html())
        },
        websiteUrl: $('#website_url').val(),
        initialize: function(){
            $('.checkout-button').show();

            if ($('#checkout-widget-address-preview').size()){
                $('#checkout-widget-address-preview').on('click', '#edit-cart-btn', this.editAddress.bind(this));
            }

            if ($.fn.addressChain){
                $.fn.addressChain.options.url = this.websiteUrl + 'api/store/geo/type/state';
            }

            this.$el.find('form.address-form').addressChain();
        },
        submitForm: function(e) {
            e.preventDefault();

//            $('#checkout').attr("disabled", 'disabled');
            var self    = this,
                form    = $(e.currentTarget),
                valid   = true;

            form.find('.notvalid').removeClass('notvalid');

            $('.required:input', form).each(function(){
                if (_.isEmpty($(this).val())){
                    valid = false;
                    $(this).addClass('notvalid');
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
                dataType: 'html',
                success: function(response){
                    switch (form[0].id){
                        case 'checkout-user-address':
                            self.addressFormCache = self.$el.html();
                            self.$el.empty();
                            self.buildAddressPreview(form)
                                .buildShipperForm($.parseJSON(response));
                            break;
                        case 'checkout-pickup':
                            self.renderPaymentZone(response);
                            break;
                        default:
                            self.$el.html(response);
                            console.log(self.$el.find('form.address-form').addressChain());
                            break;
                    }
                },
                error: function(xhr, status){
                    var errors = $.parseJSON(xhr.responseText);
                    if (errors !== null){
                        _.each(errors, function(error, element){
                            form.find('#'+element).addClass('notvalid');
                        });
                        form.find('.notvalid:first').focus();
                    }
                }
            });
        },
        toggleCheckoutStart: function() {
            this.$('.checkout-button').hide()
            this.$('.checkout-forms').show();
        },
        processFormErrors: function(errors){
            var self = this;
            _.each(errors, function(error, name){
                self.form.find('#'+name).addClass('notvalid');
            });
        },
        buildAddressPreview: function(form){
            var addressWidget = $('#checkout-widget-address-preview');

            if (addressWidget && _.isFunction(this.templates.addressPreview)){
                var formData = form.serializeArray(),
                    jsonData = {
                        state: null
                    };
                _.each(formData, function(elem){
                    jsonData[elem.name] = elem.value;
                });

                if (!_.isEmpty(jsonData)){
                    $('#cart-address-preview', addressWidget).html(this.templates.addressPreview(jsonData));
                }
            }

            return this;
        },
        buildShipperForm: function(response){
            var self = this;
            this.switchCheckoutLock(true);
            if (_.has(response, 'shippers')) {
                var form = $(this.templates.shippersForm(response));
                form.on('submit', this.submitShipper.bind(this)).appendTo(this.$el);
                self.shipperXHRCount = response.shippers.length;
                _.each(response.shippers, function(shipper){
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
                });
            } else {
               showMessage('Something went wrong. Please try again later', true);
            }
        },
        submitShipper: function(e){
            e.preventDefault();
            var self = this,
                form = $(e.currentTarget);

            if (!form.find('input[name=shipper]:checked').size()){
                showMessage('Please, select shipping method', true);
                return false;
            }

            var formData = form.serialize();
            if ($('#shipping-type-selected').size()){
                var shipper = form.find('[name=shipper]:checked');
                $('p', '#shipping-type-selected').html(shipper.closest('ul').data('name') + ': '+ shipper.next('span.shipping-method-title').text());
                $('#shipping-type-selected').show();
            }

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: formData,
                success: self.renderPaymentZone.bind(this),
                error: function(){
                    window.console && console.log(arguments);
                }
            });
        },
        renderPaymentZone: function(html){
            console.log(this);
//            var selectedShippingMethod = $("form#shipper-select input[type='radio']:checked").val();
//            var selectedShippingName = $("form#shipper-select input[type='radio']:checked").parent().find('.shipping-method-title').html();
//            var shippingMethodRegex=new RegExp("::.*");
//            var shippingMethod = selectedShippingMethod.replace(shippingMethodRegex, '');
//            var shippingType = $('#shipping-type-selected');
//            if(shippingType.length) {
//                shippingType.replaceWith('<div id="shipping-type-selected"><span class="checkout-right-title">Shipping method: '+shippingMethod+'</span><p>'+selectedShippingName+'</p></div>');
//            }
//            $('form#shipper-select').remove();
            this.switchCheckoutLock(true);
            this.$el.empty();

            var pz = $('#payment-zone');
            if (!pz){
                pz = $('<div id="payment-zone"></div>').insertAfter(this.el);
            }
            pz.html(html);
            refreshCartSummary();
        },
        editAddress: function(){
            if (_.isUndefined(this.addressFormCache)){
                window.location.reload();
            } else {
                this.switchCheckoutLock(false)
                    .$el.html(this.addressFormCache);
            }
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