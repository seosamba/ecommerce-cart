/**
 * @author Pavel Kovalyov <pavlo.kovalyov@gmail.com>
 */
define([ 'backbone' ], function( Backbone ){

    var AppView = Backbone.View.extend({
        el: $('#checkout-widget'),
        events: {
            'click p.checkout-button a[data-role=button]': 'checkoutAction',
            'submit form.toaster-checkout': 'validateForm',
            'click a.back-button': 'backAction'
        },
        initialize: function(){
            this.websiteUrl = $('#website_url').val();
            this.checkoutUrl = this.websiteUrl + 'plugin/cart/run/checkout/';

            var self = this;
            $('div.spinner').hide();
            this.$el.fadeIn();

            if ($.fn.addressChain){
                $.fn.addressChain.options.url = this.websiteUrl + 'api/store/geo/type/state';
            }

            this.$el.find('form.address-form').addressChain();

            refreshCartSummary();
        },
        submitForm: function(e) {
            e.preventDefault();

            var self = this,
                form = $(e.currentTarget);

            if (!this.validateForm(e)) {
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
        validateForm: function(e){
            var form = $(e.currentTarget),
                isValid   = true;

            form.find('.notvalid').removeClass('notvalid');

            $('.required:input', form).each(function(){
                if (this.type==="text" && _.isEmpty($(this).val())){
                    isValid = false;
                    $(this).addClass('notvalid');
                } else if (this.type === "checkbox" && !this.checked) {
                    isValid = false;
                    $(this).addClass('notvalid');
                }
            });

            if (!isValid) {
                showMessage('Missing required fields', true);
                $('.notvalid:input:first', form).focus();
            }

            return isValid;
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