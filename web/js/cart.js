$(function() {
    $(document).on('click', '.shipping-address-more', function() {
        if ($('#shipping-address-list .adr-shipping:visible:last').is(':last-child')) {
            return false;
        }
        var allVisible = $('#shipping-address-list').children('.adr-shipping').length;
        var currentIndex = $('#shipping-address-list').children('.adr-shipping:visible:last').index();
        var nextIndex = currentIndex + 4;
        if(allVisible == currentIndex+1){
            $('.shipping-address-more').hide();
        }
        $('#shipping-address-list .adr-shipping').hide();
        $('#shipping-address-list .adr-shipping:lt(' + nextIndex + ')').show();
    });

    $(document).on('change', 'input.product-qty', function() {
        var self = this;
		var sid = $(this).data('sid');
        var sidsQuantity = $('.toastercart-item-qty').length;
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
                    if(response.responseText.minqty === false){
                        window.location.reload();
                    }
                    refreshPrice(sid, sidsQuantity);
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
        var sidsQuantity = $('.toastercart-item-qty').length;
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
                    if(response.responseText.minqty === false){
                        window.location.reload();
                    }
                    if(response.responseText.sidQuantity != sidsQuantity-1){
                        window.location.reload();
                    }else{
	                    refreshCartSummary();
                    }
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

function refreshPrice(sid, sidsQuantity) {
    return $.post($('#website_url').val()+'plugin/cart/run/cartcontent/', {sid: sid}, function(response) {
        var rowsQuantity = 0;
        $.each(response.responseText, function(sid){
            rowsQuantity++
        });
        if(rowsQuantity == sidsQuantity){
            $.each(response.responseText, function(sid){
                $('span[data-sidprice=' + sid + ']').replaceWith(this.price);
                $('span[data-sidweight=' + sid + ']').replaceWith(this.weight);
            });
        }else{
            window.location.reload();
        }
    }, 'json');
}