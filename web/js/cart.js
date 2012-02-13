$(function() {
	//$('#btn-checkout').button();

	$(document).on('blur', '.product-qty', function() {
		var sid = $(this).data('sid');
		var qty = $(this).val();
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
					refreshCart();
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
	                refreshCart();
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
	}).on('submit', '#shipping-user-address', function() {
		var valid = true;
		var requiredFields = ['#first-name', '#email', '#shipping-address1', '#country', '#state', '#zip-code'];

		$.each(requiredFields, function(key, field) {
			var element = $(field);
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
			form.attr('action')
		$.ajax({
			url        : form.attr('action'),
			type       : 'post',
			dataType   : 'json',
			data       : form.serialize(),
			beforeSend : function() {showSpinner();},
			success    : function(response) {
				hideSpinner();
				$('#payment-zone').slideDown();
                showMessage(response.responseText, response.error);
			}
		});
		return false;
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

function refreshCart() {
	var cart = $('#toaster-cart-holder');
    if(cart.length) {
        $.post('/plugin/cart/run/cartcontent/', function(response) {
	        cart.replaceWith(response.responseText);
        }, 'json');
    }
}