$(function() {
	$(document).on('change', '.product-qty', function() {
        var self = this;
		var sid = $(this).data('sid');
		var qty = parseInt($(this).val());
        if (isNaN(qty)){
            $(this).addClass('notvalid').val('').focus();
            return false;
        }
        $(this).val(qty);

		$.ajax({
			url      : $('#website_url').val()+'plugin/cart/run/cart',
			type     : qty <= 0 ? 'delete' : 'put',
			dataType : 'json',
			data     : {
				sid: sid,
				qty: qty
			},
			beforeSend : function() {showSpinner();},
			success : function(response) {
                hideSpinner();
                if (!response.error){
                    var newQty = parseInt(response.responseText.qty);
                    if (newQty > 0){
                        if (qty !== newQty ){
                            showMessage(response.responseText.msg, 1);
                            $(self).val(newQty);
                        }
                    }
                    refreshPrice(sid);
                }
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
			url      : $('#website_url').val()+'plugin/cart/run/cart',
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
	}).on('blur', 'input.required', function() {
        if (this.value){
            $(this).removeClass('notvalid');
        } else {
            $(this).addClass('notvalid');
        }
	});
});

function refreshCartSummary() {
	var cartSummary = $('#cart-summary');
    if(cartSummary.length) {
        return $.post($('#website_url').val()+'plugin/cart/run/summary/', function(response) {
			cartSummary.replaceWith(response.responseText);
        }, 'json');
    }
}

function refreshPrice(sid) {
    return $.post($('#website_url').val()+'plugin/cart/run/cartcontent/', {sid: sid}, function(response) {
        $('span[data-sidprice=' + sid + ']').replaceWith(response.responseText.price);
	    $('span[data-sidweight=' + sid + ']').replaceWith(response.responseText.weight);
    }, 'json');
}