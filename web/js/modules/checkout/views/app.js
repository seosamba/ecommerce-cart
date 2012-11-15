/**
 * @author Pavel Kovalyov <pavlo.kovalyov@gmail.com>
 */
define([ 'backbone' ], function( Backbone ){

    var AppView = Backbone.View.extend({
        el: $('#checkout-widget'),
        events: function(){
            var events = {
                'click p.checkout-button a[data-role=button]': 'checkoutAction'
            }
            var nonIEEvents = {
                'submit form.toaster-checkout': 'submitForm',
                'click a.back-button': 'backAction'
            }

            return $.browser.msie ? events : _.extend(events, nonIEEvents);
        },
        initialize: function(){
            this.websiteUrl = $('#website_url').val();
            this.checkoutUrl = this.websiteUrl + 'plugin/cart/run/checkout/';

            var self = this;
            $('div.spinner').hide();
            this.$el.fadeIn();

            if (!$.browser.msie) {
                $('body').on('click', 'a.checkout-edit', _.bind(this.editAction, this));
                $('body').on('click', 'a.checkout-edit[data-step=shipping]', function(){
                    self.toggleCheckoutLock(false);
                });
            }


            if ($.fn.addressChain){
                $.fn.addressChain.options.url = this.websiteUrl + 'api/store/geo/type/state';
            }

            this.$el.find('form.address-form').addressChain();

            refreshCartSummary();
        },

        submitForm: function(e) {
            e.preventDefault();

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

            this.toggleCheckoutLock(true);

            $.ajax({
                url: this.checkoutUrl,
                type: 'POST',
                data: form.serialize(),
                dataType: 'html',
                beforeSend: function(){
                    self.$el.hide();
                    $('div.spinner').show();
                    form.find('[type="submit"]').attr('disabled', 'disabled');
                },
                complete: function(){
                    $('div.spinner').hide();
                    form.find('[type="submit"]').removeAttr('disabled');
                    self.$el.show();
                },
                success: function(response){
                    self.$el.html(response);
                    self.$el.find('form.address-form').addressChain();
                    self.updateBuyerSummary();
                    refreshCartSummary();
                },
                error: function(xhr, status){
                }
            });
        },
        checkoutAction: function(e) {
            e.preventDefault();
            var target = $(e.currentTarget).data('targetid');
            if (target && $(target).size()){
                $(target).show();
                $(e.currentTarget).closest('p.checkout-button').hide();
            }
        },
        backAction: function(e) {
            e.preventDefault();
            this.$el.find('p.checkout-button').show();
            this.$el.children().not('p.checkout-button').hide();
        },
        editAction: function(e) {
            e.preventDefault();
            var self = this;

            self.$el.hide();
            $('div.spinner').show();

            $.get(this.checkoutUrl, {step: $(e.currentTarget).data('step')}, function(response){
                $('div.spinner').hide();
                self.$el.html(response).show();
                self.$el.find('form.address-form').addressChain();
                self.updateBuyerSummary();
            });

            return false;
        },
        toggleCheckoutLock: function(lock){
            this.lock = !!lock;

            if (this.lock) {
                $('.toastercart-item-qty').attr('disabled', 'disabled');
                $('.remove-item').hide();
            } else {
                $('.toastercart-item-qty').removeAttr('disabled');
                $('.remove-item').show();
            }

            return this;
        },
        updateBuyerSummary: function(){
            var widget = $('#checkout-widget-preview');
            if (widget.length) {
                $.post($('#website_url').val() + '/plugin/cart/run/buyersummary/', function(response) {
                    widget.replaceWith(response.responseText);
                }, 'json');
            }

            return this;
        }
    });

    return AppView;
});