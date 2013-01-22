/**
 * Just to make sure that add to cart handler is attached
 */
$(function() {
    $(document).on('click', 'a.tcart-add[data-pid]', function(e) {
        e.preventDefault();
        var pid  = $(this).data('pid');
        var qty = 1;
        if($('input[name="productquantity-' + pid + '"]').length > 0){
            qty = parseInt($('input[name="productquantity-' + pid + '"]').val());
            if(isNaN(qty)){
                qty = 1;
            } 
        }
        $.ajax({
            url: $('#website_url').val()+'plugin/cart/run/cart/',
            type: 'POST',
            dataType: 'json',
            data: {
                pid     : pid,
                options : $('div[data-productid=' + pid + '] *').serialize(),
                qty     : qty
            },
            success: function(response){
                if (!response.error){
                    window.location.href = $('#website_url').val()+'plugin/cart/run/checkout';
                } else {
                    showMessage(response.responseText.msg, 1);
                }
            }
        });
    })
});