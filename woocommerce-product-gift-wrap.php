<?php
/*
Plugin Name: WooCommerce Product Gift Wrap
Plugin URI: https://github.com/mikejolley/woocommerce-product-gift-wrap
Description: Add an option to your products to enable gift wrapping. Optionally charge a fee.
Version: 1.1.0
Author: Mike Jolley
Author URI: http://mikejolley.com
Requires at least: 3.5
Tested up to: 4.0
Text Domain: woocommerce-product-gift-wrap
Domain Path: /languages/

	Copyright: © 2014 Mike Jolley.
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/**
 * Localisation
 */
load_plugin_textdomain( 'woocommerce-product-gift-wrap', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

/**
 * WC_Product_Gift_wrap class.
 */
class WC_Product_Gift_Wrap {

	/**
	 * Hook us in :)
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$default_message                 = '{checkbox} '. sprintf( __( 'Gift wrap this item for %s?', 'woocommerce-product-gift-wrap' ), '{price}' );
		$this->gift_wrap_enabled         = get_option( 'product_gift_wrap_enabled' ) == 'yes' ? true : false;
		$this->gift_wrap_cost            = get_option( 'product_gift_wrap_cost', 0 );
		$this->product_gift_wrap_message = get_option( 'product_gift_wrap_message' );

		if ( ! $this->product_gift_wrap_message ) {
			$this->product_gift_wrap_message = $default_message;
		}

		add_option( 'product_gift_wrap_enabled', 'no' );
		add_option( 'product_gift_wrap_cost', '0' );
		add_option( 'product_gift_wrap_message', $default_message );

		// Init settings
		$this->settings = array(
			array(
				'name' 		=> __( 'Gift Wrapping Enabled by Default?', 'woocommerce-product-gift-wrap' ),
				'desc' 		=> __( 'Enable this to allow gift wrapping for products by default.', 'woocommerce-product-gift-wrap' ),
				'id' 		=> 'product_gift_wrap_enabled',
				'type' 		=> 'checkbox',
			),
			array(
				'name' 		=> __( 'Default Gift Wrap Cost', 'woocommerce-product-gift-wrap' ),
				'desc' 		=> __( 'The cost of gift wrap unless overridden per-product.', 'woocommerce-product-gift-wrap' ),
				'id' 		=> 'product_gift_wrap_cost',
				'type' 		=> 'text',
				'desc_tip'  => true
			),
			array(
				'name' 		=> __( 'Gift Wrap Message', 'woocommerce-product-gift-wrap' ),
				'id' 		=> 'product_gift_wrap_message',
				'desc' 		=> __( 'Note: <code>{checkbox}</code> will be replaced with a checkbox and <code>{price}</code> will be replaced with the gift wrap cost.', 'woocommerce-product-gift-wrap' ),
				'type' 		=> 'text',
				'desc_tip'  => __( 'The checkbox and label shown to the user on the frontend.', 'woocommerce-product-gift-wrap' )
			),
		);

		// Display on the front end
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'gift_option_html' ), 10 );

		// Filters for cart actions
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 10, 1 );
		add_action( 'woocommerce_add_order_item_meta', array( $this, 'add_order_item_meta' ), 10, 2 );

		// Write Panels
		add_action( 'woocommerce_product_options_pricing', array( $this, 'write_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'write_panel_save' ) );

		// Admin
		add_action( 'woocommerce_settings_general_options_end', array( $this, 'admin_settings' ) );
		add_action( 'woocommerce_update_options_general', array( $this, 'save_admin_settings' ) );
	}

	/**
	 * Show the Gift Checkbox on the frontend
	 *
	 * @access public
	 * @return void
	 */
	public function gift_option_html() {
		global $post;

		$is_wrappable = get_post_meta( $post->ID, '_is_gift_wrappable', true );

		if ( $is_wrappable == '' && $this->gift_wrap_enabled ) {
			$is_wrappable = 'yes';
		}

		if ( $is_wrappable == 'yes' ) {

			$current_value = ! empty( $_REQUEST['gift_wrap'] ) ? 1 : 0;

			$cost = get_post_meta( $post->ID, '_gift_wrap_cost', true );

			if ( $cost == '' ) {
				$cost = $this->gift_wrap_cost;
			}

			$price_text = $cost > 0 ? woocommerce_price( $cost ) : __( 'free', 'woocommerce-product-gift-wrap' );
			$checkbox   = '<input type="checkbox" name="gift_wrap" value="yes" ' . checked( $current_value, 1, false ) . ' />';

			woocommerce_get_template( 'gift-wrap.php', array(
				'product_gift_wrap_message' => $this->product_gift_wrap_message,
				'checkbox'                  => $checkbox,
				'price_text'                => $price_text
			), 'woocommerce-product-gift-wrap', untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/templates/' );
		}
	}

	/**
	 * When added to cart, save any gift data
	 *
	 * @access public
	 * @param mixed $cart_item_meta
	 * @param mixed $product_id
	 * @return void
	 */
	public function add_cart_item_data( $cart_item_meta, $product_id ) {
		$is_wrappable = get_post_meta( $product_id, '_is_gift_wrappable', true );

		if ( $is_wrappable == '' && $this->gift_wrap_enabled ) {
			$is_wrappable = 'yes';
		}

		if ( ! empty( $_POST['gift_wrap'] ) && $is_wrappable == 'yes' ) {
			$cart_item_meta['gift_wrap'] = true;
		}

		return $cart_item_meta;
	}

	/**
	 * Get the gift data from the session on page load
	 *
	 * @access public
	 * @param mixed $cart_item
	 * @param mixed $values
	 * @return void
	 */
	public function get_cart_item_from_session( $cart_item, $values ) {

		if ( ! empty( $values['gift_wrap'] ) ) {
			$cart_item['gift_wrap'] = true;

			$cost = get_post_meta( $cart_item['data']->id, '_gift_wrap_cost', true );

			if ( $cost == '' ) {
				$cost = $this->gift_wrap_cost;
			}

			$cart_item['data']->adjust_price( $cost );
		}

		return $cart_item;
	}

	/**
	 * Display gift data if present in the cart
	 *
	 * @access public
	 * @param mixed $other_data
	 * @param mixed $cart_item
	 * @return void
	 */
	public function get_item_data( $item_data, $cart_item ) {
		if ( ! empty( $cart_item['gift_wrap'] ) )
			$item_data[] = array(
				'name'    => __( 'Gift Wrapped', 'woocommerce-product-gift-wrap' ),
				'value'   => __( 'Yes', 'woocommerce-product-gift-wrap' ),
				'display' => __( 'Yes', 'woocommerce-product-gift-wrap' )
			);

		return $item_data;
	}

	/**
	 * Adjust price after adding to cart
	 *
	 * @access public
	 * @param mixed $cart_item
	 * @return void
	 */
	public function add_cart_item( $cart_item ) {
		if ( ! empty( $cart_item['gift_wrap'] ) ) {

			$cost = get_post_meta( $cart_item['data']->id, '_gift_wrap_cost', true );

			if ( $cost == '' ) {
				$cost = $this->gift_wrap_cost;
			}

			$cart_item['data']->adjust_price( $cost );
		}

		return $cart_item;
	}

	/**
	 * After ordering, add the data to the order line items.
	 *
	 * @access public
	 * @param mixed $item_id
	 * @param mixed $values
	 * @return void
	 */
	public function add_order_item_meta( $item_id, $cart_item ) {
		if ( ! empty( $cart_item['gift_wrap'] ) ) {
			woocommerce_add_order_item_meta( $item_id, __( 'Gift Wrapped', 'woocommerce-product-gift-wrap' ), __( 'Yes', 'woocommerce-product-gift-wrap' ) );
		}
	}

	/**
	 * write_panel function.
	 *
	 * @access public
	 * @return void
	 */
	public function write_panel() {
		global $post;

		echo '</div><div class="options_group show_if_simple show_if_variable">';

		$is_wrappable = get_post_meta( $post->ID, '_is_gift_wrappable', true );

		if ( $is_wrappable == '' && $this->gift_wrap_enabled ) {
			$is_wrappable = 'yes';
		}

		woocommerce_wp_checkbox( array(
				'id'            => '_is_gift_wrappable',
				'wrapper_class' => '',
				'value'         => $is_wrappable,
				'label'         => __( 'Gift Wrappable', 'woocommerce-product-gift-wrap' ),
				'description'   => __( 'Enable this option if the customer can choose gift wrapping.', 'woocommerce-product-gift-wrap' ),
			) );

		woocommerce_wp_text_input( array(
				'id'          => '_gift_wrap_cost',
				'label'       => __( 'Gift Wrap Cost', 'woocommerce-product-gift-wrap' ),
				'placeholder' => $this->gift_wrap_cost,
				'desc_tip'    => true,
				'description' => __( 'Override the default cost by inputting a cost here.', 'woocommerce-product-gift-wrap' ),
			) );

		wc_enqueue_js( "
			jQuery('input#_is_gift_wrappable').change(function(){

				jQuery('._gift_wrap_cost_field').hide();

				if ( jQuery('#_is_gift_wrappable').is(':checked') ) {
					jQuery('._gift_wrap_cost_field').show();
				}

			}).change();
		" );
	}

	/**
	 * write_panel_save function.
	 *
	 * @access public
	 * @param mixed $post_id
	 * @return void
	 */
	public function write_panel_save( $post_id ) {
		$_is_gift_wrappable = ! empty( $_POST['_is_gift_wrappable'] ) ? 'yes' : 'no';
		$_gift_wrap_cost   = ! empty( $_POST['_gift_wrap_cost'] ) ? woocommerce_clean( $_POST['_gift_wrap_cost'] ) : '';

		update_post_meta( $post_id, '_is_gift_wrappable', $_is_gift_wrappable );
		update_post_meta( $post_id, '_gift_wrap_cost', $_gift_wrap_cost );
	}

	/**
	 * admin_settings function.
	 *
	 * @access public
	 * @return void
	 */
	public function admin_settings() {
		woocommerce_admin_fields( $this->settings );
	}

	/**
	 * save_admin_settings function.
	 *
	 * @access public
	 * @return void
	 */
	public function save_admin_settings() {
		woocommerce_update_options( $this->settings );
	}
}

new WC_Product_Gift_Wrap();
