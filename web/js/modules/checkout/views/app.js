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
        events: {
            'click p.checkout-button a[data-role=button]': 'checkoutAction',
            'submit form.toaster-checkout': 'validateForm',
            'click a.back-button': 'backAction',
            'click li.pickup-address-row': 'checkedPickupLocation',
            'click a.apply-pickup':'applyPickupPrice',
            'click #user-info-pickup': 'searchPickupLocations',
            'keypress #user-info-location':'searchPickup',
            'change #location-list-pickup ': 'searchByPickupLocation',
            'click .open-marker-popup': 'openMarkerPopup'
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
            if ($('#payment-zone').data('throttle') === 1) {
                let throttleMessage = $('#payment-zone').data('throttle-message');
                if (_.isEmpty(throttleMessage)) {
                    showMessage(_.isUndefined(i18n['Due to unprecedented orders volume, and in order to maintain quality of service, our online shop is open for a limited amount of time every day. We are no longer accepting orders today, please try to come back earlier tomorrow to place your order. We apologize for the inconvenience.']) ? 'Due to unprecedented orders volume, and in order to maintain quality of service, our online shop is open for a limited amount of time every day. We are no longer accepting orders today, please try to come back earlier tomorrow to place your order. We apologize for the inconvenience.' : i18n['Due to unprecedented orders volume, and in order to maintain quality of service, our online shop is open for a limited amount of time every day. We are no longer accepting orders today, please try to come back earlier tomorrow to place your order. We apologize for the inconvenience.'], true);
                } else {
                    showMessage(throttleMessage, true);
                }
            }
        },
        initMap: function () {
            var myOptions = this.initOptionsMap();
            this.directionsDisplay = new google.maps.DirectionsRenderer();
            this.map = new google.maps.Map(document.getElementById('pickup-locations'), myOptions);
            this.directionsDisplay.setMap(this.map);
            this.directionsService = new google.maps.DirectionsService();

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
                $(e.currentTarget).closest('p.checkout-button').hide();
            }
        },
        backAction: function(e) {
            if (!$(e.currentTarget).data('shippers-back')) {
                e.preventDefault();
                this.$el.find('p.checkout-button').show();
                this.$el.children().not('p.checkout-button').hide();
            }
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
        searchPickup: function(e){
            if (e.keyCode === 13){
                this.searchPickupLocations(e);
            }
        },
        searchByPickupLocation: function(e){
            this.mapBounds = [];
            var currentMarkers = this.mapMarkers,
                pickupLocationId = $(e.currentTarget).val();
            if(pickupLocationId == '-1'){
                return false;
            }
            if(!_.isEmpty(currentMarkers)){
                this.clearPickupLocationMap(currentMarkers);
            }
            this.mapMarkers = [];
            this.pickupLocations = [];
            this.infoWindowsData = [];
            this.getPickupLocations(pickupLocationId, true);
        },
        searchPickupLocations: function(e){
            if(this.$el.find('#pickup-locations').length > 0){
                var locationAddress = $.trim($('#user-info-location').val());
                if(locationAddress === ''){
                    showMessage(_.isUndefined(i18n['Please enter location'])?'Please enter location':i18n['Please enter location'], true);
                    return false;
                }

                locationAddress = $('#search-pickup-country').find(':selected').data('original-country-name') + ' ' +locationAddress;

                this.mapBounds = [];
                var currentMarkers = this.mapMarkers;
                if(!_.isEmpty(currentMarkers)){
                    this.clearPickupLocationMap(currentMarkers);
                }
                this.mapMarkers = [];
                this.pickupLocations = [];
                this.infoWindowsData = [];
                this.getPickupLocations(locationAddress);
            }
        },
        initOptionsMap: function() {
            return {
               zoom: 18,
               center: new google.maps.LatLng(48, 2),
               mapTypeControlOptions: {
                    style: google.maps.MapTypeControlStyle.DROPDOWN_MENU
               },
               scaleControl: false,
               disableDefaultUI: true,
               zoomControl: true,
               scrollwheel: false,
               mapTypeId: google.maps.MapTypeId.ROADMAP,
               streetViewControl: true,
               panControl: true
            }
        },
        addMarkers: function(marker, userLocation, withoutSearch){
            //default image
            var imageName = 'https://www.google.com/intl/en_us/mapfiles/ms/micons/red-dot.png';
            //user location image
            var userLocationImageName = 'https://www.google.com/intl/en_us/mapfiles/ms/micons/green-dot.png';

            var latLng    = new google.maps.LatLng(parseFloat(marker.lat), parseFloat(marker.lng));

            if(!_.isNull(marker.imgName)){
                imageName = $('#website_url').val()+'media/'+'/pickup-logos/small/'+marker.imgName;
            }

            marker.i18n = i18n;
            if(typeof marker.userLocation !== 'undefined'){
                if(withoutSearch){
                    return false;
                }
                imageName = userLocationImageName;
                var infoWindow = new google.maps.InfoWindow({
                    content: ''
                });
            }else{
                var infoWindow = new google.maps.InfoWindow({
                    content: _.template(PickupInfoWindowTemplate, marker)
                });
            }

            //infoWindows data
            this.infoWindowsData.push(infoWindow);
            var newMarker = new google.maps.Marker({
                map: this.map,
                title: marker.name,
                position: latLng,
                icon: imageName,
                infoWindow:this.infoWindowsData,
                directionsService:this.directionsService,
                directionsDisplay:this.directionsDisplay,
                userLocation:userLocation
            });
            if(typeof marker.userLocation === 'undefined'){
                newMarker.set("id", marker.id);
                newMarker.set("price", marker.price);

                google.maps.event.addListener(this.map, 'click', function() {
                    infoWindow.close();
                });

                google.maps.event.addListener(newMarker, 'click', function() {
                    //map route
                    //display only with search by pickup locations
                    if(!withoutSearch){
                        var end = new google.maps.LatLng(parseFloat(this.position.lat()), parseFloat(this.position.lng()));
                        var start = this.userLocation;

                        var request = {
                            origin:start,
                            destination:end,
                            travelMode: google.maps.TravelMode.DRIVING
                        };

                        var directionDisplay = this.directionsDisplay;
                        this.directionsService.route(request, function(response, status) {
                        if (status == google.maps.DirectionsStatus.OK) {
                            directionDisplay.setDirections(response);
                            directionDisplay.setOptions( { suppressMarkers: true } );
                            }
                        });
                    }
                    //remove all opened info windows
                    for (var i=0;i<this.infoWindow.length;i++) {
                        this.infoWindow[i].close();
                    }
                    //calculate shipping tax
                    var currentMap = this;

                    $.post($('#website_url').val()+'plugin/cart/run/pickupLocationTax/', {locationId:currentMap.id, price:currentMap.price}, function(response){
                        response.responseText.i18n = i18n;
                        infoWindow.setContent(_.template(PickupInfoWindowTemplate, response.responseText));
                        infoWindow.open(currentMap.map, currentMap);
                    }, 'json');
                    //infoWindow.open(this.map, this);
                });
                google.maps.event.addListener(infoWindow,'open',function(){
                    infoWindow.close();
                });
            }

            this.mapBounds.push(latLng);
            this.mapMarkers.push(newMarker);
            window.location.Markers = this.mapMarkers;
        },
        openMarkerPopup: function(e) {
            e.preventDefault();

            var locationlink = $(e.currentTarget),
                locationlinkMarkerId = locationlink.data('pickup-id');

            if(locationlinkMarkerId != '') {
                var markers = window.location.Markers;
                $.each(markers, function (key, marker) {
                    if(locationlinkMarkerId == marker.get('id')) {
                        google.maps.event.trigger(marker, 'click');
                    }
                });
            }
        },
        getPickupLocations: function(locationAddress, withoutSearch){
            var self = this,
                withoutSearch = withoutSearch || false;
            $.post($('#website_url').val()+'plugin/cart/run/getPickupLocations/', {locationAddress:locationAddress, withoutSearch:withoutSearch}, function(response){
                if(response.error === 0){
                    var userLocation = new google.maps.LatLng(parseFloat(response.responseText.userLocation.lat), parseFloat(response.responseText.userLocation.lng));
                    $.each(response.responseText.result, function(value, marker){
                        self.pickupLocations[marker.id] = marker;
                        if(!_.isNull(marker.name) || !_.isNull(marker.address1)){
                             self.addMarkers(marker, userLocation, withoutSearch);
                        }
                    });
                    var latlngbounds = new google.maps.LatLngBounds();
                    _.each(self.mapBounds, function(marker){
                        latlngbounds.extend(marker);
                    });
                    if($('#pickup-map-locations').hasClass('hidden')){
                        $('#pickup-map-locations').toggleClass('hidden');
                    }
                    google.maps.event.trigger(self.map, 'resize');
                    if (self.mapBounds.length > 1) {
                        self.map.setCenter(latlngbounds.getCenter(), self.map.fitBounds(latlngbounds));
                    } else {
                        self.map.setCenter(latlngbounds.getCenter());
                    }

                    $('#pickup-locations-links').empty();

                    var links = '';
                    if(typeof response.responseText.locationsLinks !== 'undefined' && response.responseText.locationsLinks.length) {
                        _.each(response.responseText.locationsLinks, function(location){
                            links += '<li><a href="javascript:;" class="open-marker-popup large" data-pickup-id="'+ location.id +'">' + location.name + '</a></li>';
                        });

                        $('#pickup-locations-links').html(links);
                    }
                }else{
                    showMessage(_.isUndefined(i18n['No locations found'])?'No locations found':i18n['No locations found'], true);
                }
            },'json');
        },
        clearPickupLocationMap: function(currentMarkers){
            for (var i = 0; i < currentMarkers.length; i++) {
                currentMarkers[i].setMap(null);
            }
            this.directionsDisplay.setDirections({routes:[]});
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
                $.post($('#website_url').val()+'plugin/cart/run/pickupLocationTax/', {locationId:locationData.id, price:locationData.price}, function(response){
                    $('#pickup-map-locations,#pickup-address-result,#initial-pickup-info').toggle();
                    response.responseText.i18n = i18n;
                    $('#pickup-result').append(_.template(PickupResultTemplate, response.responseText));
                    $('#pickup-with-price-result').show();
                    $('#pickupLocationId').val(locationData.id);
                }, 'json');
            }
        }

    });

    return AppView;
});
