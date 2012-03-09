$(function() {
	//$('#btn-checkout').button();

	$(document).on('blur', '.product-qty', function() {
		var sid = $(this).data('sid');
		var qty = parseInt($(this).val());
        $(this).val(qty);
		$.ajax({
			url      : '/plugin/cart/run/cart',
			type     : 'put',
			dataType : 'json',
			data     : {
				sid: sid,
				qty: qty
			},
			beforeSend : function() {showSpinner();},
			success : function(response) {
                    hideSpinner();
					refreshPrice(sid);
					refreshCartSummary();
            },
            error: function(xhr, errorStatus) {
                showMessage(errorStatus, true);
            }
		})
	}).on('click', '.remove-item', function() {
		var sid    = $(this).data('sid');
		var rmLink = $(this);
		$.ajax({
			url      : '/plugin/cart/run/cart',
			type     : 'delete',
			dataType : 'json',
			data     : {
				sid: sid
			},
			beforeSend : function() {showSpinner();},
			success : function(response) {
                if (!response.error) {
	                hideSpinner();
	                rmLink.parents('tr').remove();
	                refreshCartSummary();
                } else {
                    hideSpinner();
                    showMessage(response.responseText, true);
                }
            },
            error: function(xhr, errorStatus) {
                showMessage(errorStatus, true);
            }
		})
	}).on('submit', 'form.toaster-checkout', function(e) {
            e.preventDefault();
		var valid = true;
		var requiredFields = $('.required:input', this);

        $.each(requiredFields, function() {
            var element = $(this);
			element.removeClass('notvalid');
			if(element.val() == '') {
				valid = false;
				element.addClass('notvalid');
			}
		});

		if(!valid) {
			return false;
		}
		var form = $(this);

        $.ajax({
			url        : form.attr('action'),
			type       : 'post',
			dataType   : 'json',
			data       : form.serialize(),
			beforeSend : function() {showSpinner();},
			success    : function(response) {
                hideSpinner();
                if (response.error){
                    showMessage(response.responseText, true);
                    return false;
                }

                fireCallback(response.responseText);
				refreshCartSummary();
			}
		});
		return false;
	}).on('click', 'input', function() {
		$(this).removeClass('notvalid');
	}).on('click', '#edit-cart-btn', function(){
       switchCheckoutLock(false);
    });

});

function refreshCartSummary() {
	var cartSummary = $('#cart-summary');
    if(cartSummary.length) {
        $.post('/plugin/cart/run/summary/', function(response) {
			cartSummary.replaceWith(response.responseText);
        }, 'json');
    }
}

function refreshPrice(sid) {
    $.post('/plugin/cart/run/cartcontent/', {sid: sid}, function(response) {
        $('span[data-sidprice=' + sid + ']').replaceWith(response.responseText.price);
	    $('span[data-sidweight=' + sid + ']').replaceWith(response.responseText.weight);
    }, 'json');
}

function switchCheckoutLock(lock){
    lock = !!lock;

    if (lock) {
        $('#checkout-form').hide();
        $('#post-checkout-form').show();
        $('.product-qty').attr('disabled', 'disabled');
        $('.remove-item').hide();
        $('#payment-zone').show();
    } else {
        $('#checkout-form').show();
        $('#post-checkout-form').hide();
        $('.product-qty').removeAttr('disabled');
        $('.remove-item').show();
        $('#payment-zone').empty().hide();
    }
}

function fireCallback(responseText) {
    if (responseText.hasOwnProperty('callback')){
        var callback = responseText.callback;
        callback = window[callback];
        if (typeof callback === 'function' ){
            callback.call(this, responseText.data);
        } else {
            console.error('Callback "'+responseText.callback+'" found but not function defined');
        }
    }
}

function renderPaymentZone(html){
    switchCheckoutLock(true);
    $('#payment-zone').html(html);
    refreshCartSummary();
}

function showShippingDialog(data) {
    var $el = $('<div></div>', {id : 'shipping-multiselect'});

    $.each(data, function(index, shipper){
        if (!shipper.hasOwnProperty('service') || !shipper.hasOwnProperty('rates')){
            return false;
        }
        var $ul = $('<ul></ul>').data('service', shipper.service);

        $.each(shipper.rates, function (index, rate){
            var item = '<label style="cursor: pointer">' +
                '<input type="radio" name="shipping" value="'+index+'"/><span>'+rate.type+'</span>'+
                '<span class="price">'+rate.price+'</span></label>';
            $('<li></li>').html(item).appendTo($ul);
        });
        $ul.appendTo($el);
    });

    $el.appendTo('body').dialog({
        modal: true,
        resizable: false,
        buttons : {
            'Apply': applyUserChoise
        }
    });
}

function applyUserChoise() {
    var radio = $('#shipping-multiselect input:radio[name=shipping]:checked');
    var data = JSON.stringify({
        service: radio.closest('ul').data('service'),
        index: radio.val()
    });
    $.ajax({
        url: '/plugin/shopping/run/checkout/',
        type: 'PUT',
        dataType: 'json',
        data: data,
        success: function(response){
            console.log(response);
            fireCallback(response.responseText);
            $('#shipping-multiselect').dialog('destroy').remove();
        }
    });
}