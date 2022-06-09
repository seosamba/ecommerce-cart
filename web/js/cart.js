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
		var sid = $(self).data('sid');
		var nocurrency = $('.toastercart-item-price').data('nocurrency');
        var sidsQuantity = $('.toastercart-item-qty').length;
        var qty = parseInt($(self).val());
        if (isNaN(qty)){
            $(self).addClass('notvalid').val('').focus();
            return false;
        }
        $(self).val(qty);

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
                    if(response.responseText.minAmount === false){
                        window.location.reload();
                    }
                    if (response.responseText.sidQuantity === 0) {
                        window.location.reload();
                    }
                    if (typeof response.responseText.contentChanged !== 'undefined') {
                        showMessage(response.responseText.message, true);
                        window.location.reload();
                    }
                    refreshPrice(sid, sidsQuantity, nocurrency);
                } else {
                    showMessage(response.responseText.message, true);
                    $(self).val(response.responseText.qty);
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

                    if (typeof response.responseText.contentChanged !== 'undefined') {
                        showMessage(response.responseText.message, true);
                        window.location.reload();
                    }

	                rmLink.parents('tr').remove();
                    if(response.responseText.minqty === false){
                        window.location.reload();
                    }
                    if(response.responseText.minAmount === false){
                        window.location.reload();
                    }
                    if (response.responseText.sidQuantity == 0) {
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
	var cartSummary = $('#cart-summary'),
	    subtotalWithoutTax = $('#subtotal-without-tax-val'),
        subtotalWithoutTaxParam = 0,
        cartSummaryMS = $('#cart-summary-magic-space');

	if(subtotalWithoutTax.length) {
        subtotalWithoutTaxParam = 1;
    }

    if(cartSummary.length) {
        $.post($('#website_url').val()+'plugin/cart/run/summary/', {subtotalWithoutTax : subtotalWithoutTaxParam}, function(response) {
			cartSummary.replaceWith(response.responseText);
        }, 'json');
    }
    if(cartSummaryMS.length) {
        $.post($('#website_url').val()+'plugin/cart/run/summary/type/ms', function(response) {
            cartSummaryMS.replaceWith(response.responseText);
        }, 'json');
    }

    return true;
}

function refreshPrice(sid, sidsQuantity, nocurrency) {
    return $.post($('#website_url').val()+'plugin/cart/run/cartcontent/', {sid: sid, nocurrency: nocurrency}, function(response) {
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
