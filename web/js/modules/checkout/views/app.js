/**
 * @author Pavel Kovalyov <pavlo.kovalyov@gmail.com>
 */
define([ 'backbone',
    'i18n!../../../nls/'+$('input[name=system-language]').val()+'_ln',
    'text!./templates/pickup-list.html',
    'text!./templates/pickup-info-window.html',
    'text!./templates/pickup-result.html'
], function(Backbone,i18n, PickupListTemplate, PickupInfoWindowTemplate, PickupResultTemplate){

    var AppView = Backbone.View.extend({
        el: $('#checkout-widget'),
        pickupLocationHolder: $('#pickup-location-grid'),
        events: {
            'click p.checkout-button a[data-role=button]': 'checkoutAction',
            'submit form.toaster-checkout': 'validateForm',
            'click a.back-button': 'backAction',
            'click li.pickup-address-row': 'checkedPickupLocation',
            'click a.apply-pickup':'applyPickupPrice'
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
                    this.pickupLocations = [];
                    this.infoWindowsData = [];
                    this.getPickupLocations();
                    if($('#pickup-map-locations').hasClass('hidden')){
                        $('#pickup-map-locations').toggleClass('hidden');
                    }
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
            var imageName = 'https://www.google.com/intl/en_us/mapfiles/ms/micons/red-dot.png';

            // The place where loc contains geocoded coordinates
            var latLng    = new google.maps.LatLng(parseFloat(marker.lat), parseFloat(marker.lng));

            if(!_.isNull(marker.imgName)){
                var imageName = $('#website_url').val()+'media/'+'/pickup-logos/small/'+marker.imgName;
            }

            var infoWindow = new google.maps.InfoWindow({
                content: _.template(PickupInfoWindowTemplate, marker)
            });
            this.infoWindowsData.push(infoWindow);
            var newMarker = new google.maps.Marker({
                map: this.map,
                title: marker.name,
                position: latLng,
                icon: imageName,
                infoWindow:this.infoWindowsData
            });
            newMarker.set("id", marker.id);
            newMarker.set("price", marker.price);

            google.maps.event.addListener(this.map, 'click', function() {
                infoWindow.close();
            });

            google.maps.event.addListener(newMarker, 'click', function() {
                for (var i=0;i<this.infoWindow.length;i++) {
                    this.infoWindow[i].close();
                }
                //calculate shipping tax
                var currentMap = this;

                $.post($('#website_url').val()+'plugin/cart/run/pickupLocationTax/', {locationId:currentMap.id, price:currentMap.price}, function(response){
                    infoWindow.setContent(_.template(PickupInfoWindowTemplate, response.responseText));
                    infoWindow.open(currentMap.map, currentMap);
                }, 'json');
                //infoWindow.open(this.map, this);
            });
            google.maps.event.addListener(infoWindow,'open',function(){
                infoWindow.close();
            });

            this.mapBounds.push(latLng);
            this.mapMarkers.push(newMarker);
        },
        getPickupLocations: function(){
            var self = this;
            $.post($('#website_url').val()+'plugin/cart/run/getPickupLocations/', function(response){
                self.pickupLocationHolder.empty();
                if(response.error === 0){
                    $.each(response.responseText, function(value, marker){
                        self.pickupLocations[marker.id] = marker;
                        if(!_.isNull(marker.name) || !_.isNull(marker.address1)){
                            self.addMarkers(marker);
                             console.log(marker);
                        }
                    });
                    var latlngbounds = new google.maps.LatLngBounds();
                    _.each(self.mapBounds, function(marker){
                        latlngbounds.extend(marker);
                    });
                    self.map.setCenter(latlngbounds.getCenter(), self.map.fitBounds(latlngbounds));
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
        },
        applyPickupPrice: function(e){
            var pickupId = $(e.currentTarget).data('pickup-id');
            if(typeof  this.pickupLocations[pickupId] !== 'undefined'){
                var locationData = this.pickupLocations[pickupId];
                console.log(locationData);
                $.post($('#website_url').val()+'plugin/cart/run/pickupLocationTax/', {locationId:locationData.id, price:locationData.price}, function(response){
                    $('#pickup-map-locations').toggleClass('hidden');
                    $('#pickup-result').append(_.template(PickupResultTemplate, response.responseText));
                    $('#pickup-with-price-result').show();
                    $('#pickup-address-result').toggleClass('hidden');
                    $('#pickupLocationId').val(locationData.id);
                }, 'json');

            }
        }



    });

    return AppView;
});