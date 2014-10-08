<p class="gift-wrapping" style="clear:both; padding-top: .5em;">
	<label><?php echo str_replace( array( '{checkbox}', '{price}' ), array( $checkbox, $price_text ), wp_kses_post( $product_gift_wrap_message ) ); ?></label>
</p>