<div class="gift-wrapping gift-wrapping-cart" style="clear: both; padding-top: .5em;">
    <form id="gift-wrapping-cart-form" method="post" action="<?php echo esc_url(wc_get_cart_url()); ?>">
        <input type="hidden" name="wrap_all_as_gift" value="1">
        <?php echo $button; ?>
        <p class="gift-wrapping-cart-info">
            <?php echo str_replace( array( '{checkbox}', '{price}' ), array( '', $price_text ), wp_kses_post( $product_gift_wrap_message ) ); ?>
        </p>
    </form>
</div>