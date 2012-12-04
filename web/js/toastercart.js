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
        $.post($('#website_url').val()+'plugin/cart/run/cart/', {
            pid     : pid,
            options : $('div[data-productid=' + pid + '] *').serialize(),
            qty     : qty
        }, function() {
            window.location.href = $('#website_url').val()+'plugin/cart/run/checkout';
        })
    })
});