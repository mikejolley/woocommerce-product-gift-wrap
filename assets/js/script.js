jQuery(document).ready(function () {

    const productId = cart_ajax?.productId;

    // disable quantity for gift product
    const $giftProduct = jQuery('a[data-product_id="' + productId + '"]');
    if ($giftProduct.length) {
        $giftProduct.closest('.cart_item').find('input.qty').attr('disabled', true);
    }

    // submit wrap all cart items as gift
    jQuery(document).on('click', 'button#gift_wrap_cart', function(event) {
        event.preventDefault();

        jQuery('button#gift_wrap_cart').attr('disabled', true);

        const $container = jQuery(this).closest('#gift-wrapping-cart-container');
        const formAction = $container.attr('data-action');
        const data = {
            wrap_all_as_gift: 1
        };

        jQuery.ajax({
            type: 'POST',
            url: formAction,
            data: data,
            success: function() {
                // reload to make sure cart is updated with all themes
                window.location.reload();
                jQuery('button#gift_wrap_cart').removeAttr('disabled');
            },
            error: function(xhr, status, error) {
                console.warn('Add gift wrap to whole cart failed with: ', error);
            },
        });
    });

});
