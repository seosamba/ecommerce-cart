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
		var requiredFields = $('input.required,select.required', this);

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
                if (!response.error){
                    $('#payment-zone').html(response.responseText);
                    switchCheckoutLock(true);
                } else {
                    showMessage(response.responseText, response.error);
                }
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