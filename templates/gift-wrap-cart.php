<div class="gift-wrapping gift-wrapping-cart" style="clear: both; padding-top: .5em;">
    <div id="gift-wrapping-cart-container"  data-action="<?php echo esc_url(wc_get_cart_url()); ?>">
        <?php echo $button; ?>
        <p class="gift-wrapping-cart-info">
            <?php echo str_replace( array( '{checkbox}', '{price}' ), array( '', $price_text ), wp_kses_post( $product_gift_wrap_message ) ); ?>
        </p>
    </div>
</div>