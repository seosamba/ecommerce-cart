/**
 * @author Pavel Kovalyov <pavlo.kovalyov@gmail.com>
 */
define([ 'backbone',
    'i18n!../../../nls/'+$('input[name=system-language]').val()+'_ln',
    'text!./templates/pickup-list.html'
], function(Backbone,i18n, PickupListTemplate){

    var AppView = Backbone.View.extend({
        el: $('#checkout-widget'),
        pickupLocationHolder: $('#pickup-location-grid'),
        events: {
            'click p.checkout-button a[data-role=button]': 'checkoutAction',
            'submit form.toaster-checkout': 'validateForm',
            'click a.back-button': 'backAction',
            'click li.pickup-address-row': 'checkedPickupLocation'
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
            if(this.$el.find('#pickup-locations').length > 0){
                this.initMap();
            }

            refreshCartSummary();
        },
        initMap: function () {
            var geocoder = new google.maps.Geocoder();
            var myOptions = this.initOptionsMap();
            this.map = new google.maps.Map(document.getElementById('pickup-locations'), myOptions);

        },
        submitForm: function(e) {
            e.preventDefault();

            var self = this,
                form = $(e.currentTarget);

            if (!this.validateForm(e)) {
                showMessage(_.isUndefined(i18n['Missing required fields'])?'Missing required fields':i18n['Missing required fields'], true);
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
                showMessage(_.isUndefined(i18n['Missing required fields'])?'Missing required fields':i18n['Missing required fields'], true);
                $('.notvalid:input:first', form).focus();
            }

            return isValid;
        },
        checkoutAction: function(e) {
            e.preventDefault();
            var target = $(e.currentTarget).data('targetid');
            if (target && $(target).size()){
                $(target).show();
                if(this.$el.find('#pickup-locations').length > 0){
                    this.mapBounds = [];
                    var currentMarkers = this.mapMarkers;
                    if(!_.isEmpty(currentMarkers)){
                        this.clearPickupLocationMap(currentMarkers);
                    }
                    this.mapMarkers = [];
                    this.getPickupLocations();
                    google.maps.event.trigger(this.map, 'resize');
                }
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
        },
        initOptionsMap: function() {
            return {
               zoom: 8,
               center: new google.maps.LatLng(48, 2),
               mapTypeControlOptions: {
                    style: google.maps.MapTypeControlStyle.DROPDOWN_MENU
               },
               scaleControl: false,
               disableDefaultUI: true,
               zoomControl: true,
               scrollwheel: false,
               mapTypeId: google.maps.MapTypeId.ROADMAP
            }
        },
        addMarkers: function(marker){
            var name = marker.name;
            var imageName = 'https://www.google.com/intl/en_us/mapfiles/ms/micons/red-dot.png';

            // The place where loc contains geocoded coordinates
            var latLng    = new google.maps.LatLng(parseFloat(marker.lat), parseFloat(marker.lng));

            if(!_.isNull(marker.imgName)){
                var imageName = $('#website_url').val()+'media/'+'/pickup-logos/small/'+marker.imgName;
            }

            var newMarker = new google.maps.Marker({
                map: this.map,
                title: marker.name,
                position: latLng,
                icon: imageName
            });
            newMarker.set("id", marker.id);

            var infoWindow = new google.maps.InfoWindow({
                content: '<span>'+name+'</span><p>'+marker.address1+'</p>'
            });

            google.maps.event.addListener(newMarker, 'click', function() {
                infoWindow.open(this.map, this);
            });

            this.mapMarkers.push(newMarker);

            if(this.mapBounds.length === 0){
                this.mapBounds = new google.maps.LatLngBounds();
            }else{
                this.mapBounds = this.mapBounds.extend(latLng);
            }
            this.map.fitBounds(this.mapBounds);
        },
        getPickupLocations: function(city){
            var self = this;
            $.post($('#website_url').val()+'plugin/cart/run/getPickupLocations/',{city:city}, function(response){
                self.pickupLocationHolder.empty();
                if(response.error === 0){
                    $.each(response.responseText, function(value, marker){
                        if(!_.isNull(marker.name) || !_.isNull(marker.address1)){
                            self.addMarkers(marker);
                            console.log(marker);
                            self.pickupLocationHolder.append(_.template(PickupListTemplate, marker));
                        }

                    });
                }
            },'json');
        },
        clearPickupLocationMap: function(currentMarkers){
            for (var i = 0; i < currentMarkers.length; i++) {
                currentMarkers[i].setMap(null);
            }
        },
        checkedPickupLocation: function(e){
            e.preventDefault();
            var currentPickupLocationId = $(e.currentTarget).data('pickup-row-id');
            var currentMarker = _.filter(this.mapMarkers, function(marker){
                return parseInt(marker.get('id')) === currentPickupLocationId;
            });
            google.maps.event.trigger(currentMarker[0], 'click');
            console.log(currentMarker);
            //infowindow.open(this.map,marker);
        }



    });

    return AppView;
});