jQuery(document).ready(function () {

    const productId = cart_ajax?.productId;

    // disable quantity for gift product
    const $giftProduct = jQuery('a[data-product_id="' + productId + '"]');
    if ($giftProduct.length) {
        $giftProduct.closest('.cart_item').find('input.qty').attr('disabled', true);
    }

    // submit wrap all cart items as gift
    jQuery('button#gift_wrap_cart').click(function(event) {
        event.preventDefault();

        const $form = jQuery(this).closest('form');
        const formAction = $form.attr('action');
        const data = $form.serialize();

        jQuery.ajax({
            type: 'POST',
            url: formAction,
            data: data,
            success: function() {
                // reload to make sure cart is updated with all themes
                location.reload();
            },
            error: function(xhr, status, error) {
                console.warn('Add gift wrap to whole cart failed with: ', error);
            },
        });
    });

});
