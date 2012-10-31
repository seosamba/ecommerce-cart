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
                'click a.[data-role=backbutton]': 'backAction'
            }

            return $.browser.msie ? events : _.extend(events, nonIEEvents);
        },
        websiteUrl: $('#website_url').val(),
        checkoutUrl: $('#website_url').val() + 'plugin/cart/run/checkout/',
        initialize: function(){
            $('div.spinner').fadeOut().remove();
            this.$el.fadeIn();

            $('body').on('click', '#checkout-widget-preview a', _.bind(this.editAction, this));

            if ($.fn.addressChain){
                $.fn.addressChain.options.url = this.websiteUrl + 'api/store/geo/type/state';
            }

            this.$el.find('form.address-form').addressChain();
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
                beforeSend: function(){ form.find('[type="submit"]').attr('disabled', 'disabled').hide(); },
                complete: function(){ form.find('[type="submit"]').removeAttr('disabled').show(); },
                success: function(response){
                    self.$el.html(response);
                    self.$el.find('form.address-form').addressChain();
                    self.updateBuyerSummary();
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

            $.get(this.checkoutUrl, {step: $(e.currentTarget).data('step')}, function(response){
                self.$el.html(response);
                self.$el.find('form.address-form').addressChain();
                self.updateBuyerSummary();
            });

            return false;
        },
        toggleCheckoutLock: function(lock){
            lock = !!lock;

            if (lock) {
                $('.toastercart-item-qty').attr('disabled', 'disabled');
                $('.remove-item').hide();
                $('#checkout-widget-address-preview').slideDown();
            } else {
                $('.toastercart-item-qty').removeAttr('disabled');
                $('.remove-item').show();
            }

            return this;
        },
        updateBuyerSummary: function(){
            var widget = $('#checkout-widget-preview');
            if (widget.length) {
                $.post('/plugin/cart/run/buyersummary/', function(response) {
                    widget.replaceWith(response.responseText);
                }, 'json');
            }
        }
    });

    return AppView;
});