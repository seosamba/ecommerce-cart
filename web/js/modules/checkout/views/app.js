/**
 * @author Pavel Kovalyov <pavlo.kovalyov@gmail.com>
 */
define([ 'backbone' ], function( Backbone ){
    var AppView = Backbone.View.extend({
        el: $('#checkout-widget'),
        events: {
            'submit form.toaster-checkout': 'submitForm',
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
            this.spinner = this.$el.find('div.spinner').hide();

            if ($('#checkout-widget-preview').size()){
                $('#checkout-widget-preview').on('click', '#edit-cart-btn', this.editAddress.bind(this));
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
                beforeSend: function(){ form.find('[type="submit"]').attr('disabled', 'disabled').hide(); },
                complete: function(){ form.find('[type="submit"]').removeAttr('disabled').show(); },
                success: function(response){
                    switch (form[0].id){
                        case 'checkout-user-address':
//                            self.addressFormCache = self.$el.html();
                            self.$el.empty();
                            self.buildAddressPreview(form)
                                .buildShipperForm($.parseJSON(response));
                            break;
                        case 'checkout-pickup':
                            $('.preview-content', '#checkout-shipping-selected').text('Free pickup');
                            $('.checkout-widget-title', '#checkout-address-preview').text('Pickup information');
                            $('#checkout-shipping-selected').show();
                            self.buildAddressPreview(form)
                                .renderPaymentZone(response);
                            break;
                        case 'checkout-signup':
                            $('span.fullname', '#checkout-user-info').text(form[0].firstname.value +' '+ form[0].lastname.value);
                            $('span.email', '#checkout-user-info').text(form[0].email.value);
                            $('#checkout-user-info:hidden').show();
                        default:
                            self.$el.html(response);
                            self.$el.find('form.address-form').addressChain();
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
            var addressWidget = $('#checkout-address-preview');

            if (addressWidget && _.isFunction(this.templates.addressPreview)){
                var formData = form.serializeArray(),
                    jsonData = {
                        firstname: null, lastname: null, company: null, email: null, address1: null, address2: null,
                        city: null, state: null, zip: null, country: null, phone: null, mobile: null
                    };
                _.each(formData, function(elem){
                    if (elem.name == 'state'){
                        jsonData[elem.name] = form.find("select[name=state] option[value="+elem.value+"]").attr("label");
                    } else {
                        jsonData[elem.name] = elem.value;
                    }
                });
                if (!_.isEmpty(jsonData)){
                    $('div.preview-content', addressWidget).html(this.templates.addressPreview(jsonData));
                }
                addressWidget.show();
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
            if ($('#checkout-shipping-selected').size()){
                var shipper = form.find('[name=shipper]:checked');
                $('div.preview-content', '#checkout-shipping-selected').html(shipper.closest('ul').data('name') + ': '+ shipper.next('span.shipping-method-title').text());
                $('#checkout-shipping-selected').show();
            }

            form.find('[type=submit]').attr('disabled', 'disabled').hide();

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
                $('#edit-cart-btn', '#checkout-widget-preview').show();
                $('#checkout-user-address').hide();
                $('.product-qty').attr('disabled', 'disabled');
                $('.remove-item').hide();
                $('#payment-zone').show();
                $('#checkout-widget-address-preview').slideDown();
            } else {
                $('#edit-cart-btn', '#checkout-widget-preview').hide();
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