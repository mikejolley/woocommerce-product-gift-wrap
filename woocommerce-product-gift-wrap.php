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
		$this->gift_wrap_cart_enabled    = get_option( 'product_gift_wrap_cart_enabled' ) == 'yes' ? true : false;
		$this->gift_wrap_cart_button     = get_option( 'product_gift_wrap_cart_button' ) == 'yes' ? true : false;
		$this->gift_wrap_cart_product_id = get_option( 'product_gift_wrap_cart_product' );
		$this->gift_wrap_cost            = get_option( 'product_gift_wrap_cost', 0 );
		$this->product_gift_wrap_message = get_option( 'product_gift_wrap_message' );

		if ( ! $this->product_gift_wrap_message ) {
			$this->product_gift_wrap_message = $default_message;
		}

		$products = $this->get_all_woocommerce_products();

		add_option( 'product_gift_wrap_enabled', 'no' );
		add_option( 'product_gift_wrap_cart_enabled', 'no' );
		add_option( 'product_gift_wrap_cart_button', 'no' );
		add_option( 'product_gift_wrap_cart_product', $products );
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
				'name' 		=> __( 'Gift Wrapping whole Cart?', 'woocommerce-product-gift-wrap' ),
				'desc' 		=> __( 'Enable this to allow customer to order all cart items wrapped (this will automatically enable gift wrapping by default. Please consider to adjust the gift wrap message accordingly. It will also add a "Wrap all cart items as gift" button to the cart).', 'woocommerce-product-gift-wrap' ),
				'id' 		=> 'product_gift_wrap_cart_enabled',
				'type' 		=> 'checkbox',
				'desc_tip'  => true
			),
            array(
				'name' 		=> __( 'Show Wrap all Items in Cart Button?', 'woocommerce-product-gift-wrap' ),
				'desc' 		=> __( 'Enable this to show a button in the cart, that allows the customer to put all items in the cart in a gift wrap.', 'woocommerce-product-gift-wrap' ),
				'id' 		=> 'product_gift_wrap_cart_button',
				'type' 		=> 'checkbox',
			),
			array(
				'name' 		=> __( 'Product Gift Wrapping', 'woocommerce-product-gift-wrap' ),
				'desc' 		=> __( 'Please choose your gift product (this must be a standard WooCommerce product. Please be aware, that with this option the gift wrap cost will be taken from this product once globally for the whole cart).', 'woocommerce-product-gift-wrap' ),
				'id' 		=> 'product_gift_wrap_cart_product',
				'type' 		=> 'select',
				'desc_tip'  => true,
				'options'   => $this->get_all_woocommerce_products(),
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
		add_action( 'woocommerce_before_cart_totals', array( $this, 'gift_cart_button_html' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_gift_cart_script' ), 10 );

		// Filters for cart actions
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 10, 1 );
		add_action( 'woocommerce_cart_actions', array( $this, 'wrap_all_cart_items_as_gift' ), 10, 1);
		add_action( 'woocommerce_add_order_item_meta', array( $this, 'add_order_item_meta' ), 10, 2 );

		// Listen to item removed from cart
		add_action('woocommerce_remove_cart_item', array($this, 'remove_item_from_cart'), 10, 2);

		// Write Panels
		add_action( 'woocommerce_product_options_pricing', array( $this, 'write_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'write_panel_save' ) );

		// Admin
		add_action( 'woocommerce_settings_general_options_end', array( $this, 'admin_settings' ) );
		add_action( 'woocommerce_update_options_general', array( $this, 'save_admin_settings' ) );
	}

	/**
	 * Get all WooCommerce products
	 *
	 * @access public
	 * @return mixed $products
	 */
	public function get_all_woocommerce_products() {
		$args = array(
			'post_type' => 'product',
			'posts_per_page' => -1,
		);

		$products = get_posts($args);

		$product_list = array();

		foreach ($products as $product) {
			$product_list[$product->ID] = $product->post_title;
		}

		return $product_list;
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
	 * Show wrap all cart items as Gift Button on the frontend
	 *
	 * @access public
	 * @return void
	 */
	public function gift_cart_button_html() {
		if (! is_cart() ) {
            return;
        }
		if ($this->gift_wrap_cart_enabled == 'yes' && $this->gift_wrap_cart_button == 'yes' && !$this->is_product_in_cart($this->gift_wrap_cart_product_id)) {
			$price = $this->gift_wrap_cost;
			$price_text = $price > 0 ? woocommerce_price( $price ) : __( 'free', 'woocommerce-product-gift-wrap' );
			$button   = '<button class="button" id="gift_wrap_cart" name="gift_wrap_cart">' . __( 'All cart items wrapped as Gift', 'woocommerce-product-gift-wrap' ) . '</button>';

			woocommerce_get_template( 'gift-wrap-cart.php', array(
				'product_gift_wrap_message' => $this->product_gift_wrap_message,
				'button'                    => $button,
				'price_text'                => $price_text
			), 'woocommerce-product-gift-wrap', untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/templates/' );
		}
	}

	/**
	 * Add gift cart wrap script
     *
     * INFO: since I couldn't implement the WP fragments successfully with our WC shop theme, the script.js just reloads the page on successful ajax request
	 *
	 * @access public
	 * @return void
	 */
    public function add_gift_cart_script() {
	    if (! is_cart() ) {
		    return;
	    }
	    wp_enqueue_script( 'ajax-script', plugins_url('/assets/js/script.js', __FILE__), array('jquery'), '1.0', true );
	    wp_localize_script( 'ajax-script', 'cart_ajax', array( 'productId' => $this->gift_wrap_cart_product_id ) );
    }

	/**
	 * Set all cart items as gift post
	 *
	 * @access public
	 * @return void
	 */
	public function wrap_all_cart_items_as_gift() {
		if (isset($_POST['wrap_all_as_gift']) && $_POST['wrap_all_as_gift'] === '1') {
			$cart = WC()->cart->get_cart();
			foreach ($cart as $cart_item_key => $cart_item) {
				if ($this->gift_wrap_cart_product_id != $cart_item['product_id'] && (!isset($cart_item['gift_wrap']) || $cart_item['gift_wrap'] == false)) {
					$cart_item['gift_wrap'] = true;
					WC()->cart->cart_contents[$cart_item_key] = $cart_item;
				}
			}

			if (!$this->is_product_in_cart($this->gift_wrap_cart_product_id)) {
				$this->add_gift_product_to_cart();
			}
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

		if ( (!empty( $_POST['gift_wrap'] ) && $is_wrappable == 'yes') || ($this->gift_wrap_cart_enabled && $this->is_product_in_cart($this->gift_wrap_cart_product_id))) {
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

			if ($this->gift_wrap_cart_enabled == 'yes' && !$this->is_product_in_cart($this->gift_wrap_cart_product_id)) {
				$this->add_gift_product_to_cart();
			}

			if ($this->gift_wrap_cart_enabled != 'yes') {
				$cart_item['data']->adjust_price( $cost );
			}
		}

		return $cart_item;
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

			if ($this->gift_wrap_cart_enabled == 'yes' && !$this->is_product_in_cart($this->gift_wrap_cart_product_id)) {
				$this->add_gift_product_to_cart();
			}

			if ($this->gift_wrap_cart_enabled != 'yes') {
				$cart_item['data']->adjust_price( $cost );
			}
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
		if ( ! empty( $cart_item['gift_wrap'] ) ) {
			$item_data[] = array(
				'name'    => __( 'Gift Wrapped', 'woocommerce-product-gift-wrap' ),
				'value'   => __( 'Yes', 'woocommerce-product-gift-wrap' ),
				'display' => __( 'Yes', 'woocommerce-product-gift-wrap' )
			);
		}

		return $item_data;
	}

	/**
	 * Remove gift wrap meta from all products in cart
	 *
	 * @access public
	 * @return void
	 */
	public function remove_gift_meta_from_cart_items() {
		$cart = WC()->cart->get_cart();

		foreach ($cart as $cart_item_key => $cart_item) {
			if (! empty( $cart_item['gift_wrap'] )) {
				$cart_item['gift_wrap'] = false;
				WC()->cart->cart_contents[$cart_item_key] = $cart_item;
			}
		}
	}

	/**
	 * Check if product with ID is in cart
	 *
	 * @access public
	 * @param int $product_id
	 * @return boolean
	 */
	public function is_product_in_cart($product_id) {
		$cart = WC()->cart->get_cart();
		$product_in_cart = false;

		foreach ($cart as $cart_item) {
			if ($cart_item['product_id'] == $product_id) {
				$product_in_cart = true;
				break;
			}
		}

		return $product_in_cart;
	}

	/**
	 * Add gift product to cart
	 *
	 * @access public
	 * @return void
	 */
	public function add_gift_product_to_cart() {
		WC()->cart->add_to_cart($this->gift_wrap_cart_product_id, 1);
	}

	/**
	 * Listen to cart product removal and remove all gift meta information from other products, if gift product was removed
	 *
	 * @access public
	 * @param string $cart_item_key
	 * @param mixed $cart
	 * @return void
	 */
	public function remove_item_from_cart($cart_item_key, $cart) {
		if ($this->gift_wrap_cart_enabled == 'yes') {
			$cart_items = $cart->get_cart();
			$gift_wrap_removed = false;

			foreach ($cart_items as $cart_item) {
				if ($cart_item && isset($cart_item['product_id'])) {
					if ($cart_item['product_id'] == $this->gift_wrap_cart_product_id) {
						$gift_wrap_removed = true;
					}
				}
			}

			if ($gift_wrap_removed) {
				WC()->cart->calculate_totals();
				$this->remove_gift_meta_from_cart_items();
				wc_add_notice(__( 'Your products won\'t be wrapped as gift anymore.', 'woocommerce-product-gift-wrap' ), 'info');
			}
		}
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

		if ($this->gift_wrap_cart_enabled != 'yes') {

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

		wc_enqueue_js( "
			jQuery('input#product_gift_wrap_cart_enabled').change(function() {

				if (jQuery(this).is(':checked') && !jQuery('#product_gift_wrap_enabled').is(':checked')) {
					jQuery('#product_gift_wrap_enabled').attr('checked', true);
				}
				if (jQuery(this).is(':checked')) {
					jQuery('#product_gift_wrap_cart_product').closest('tr').slideDown();
					jQuery('#product_gift_wrap_cart_button').closest('tr').slideDown();
					jQuery('#product_gift_wrap_cost').closest('tr').slideUp();
				} else {
					jQuery('#product_gift_wrap_cart_product').closest('tr').slideUp();
					jQuery('#product_gift_wrap_cart_button').closest('tr').slideUp();
					jQuery('#product_gift_wrap_cost').closest('tr').slideDown();
				}

			}).change();
		" );
	}

	/**
	 * save_admin_settings function.
	 *
	 * @access public
	 * @return void
	 */
	public function save_admin_settings() {
		global $post;

        // TODO: check if this is needed
		$this->gift_wrap_cart_product_id = get_post_meta( $post->ID, 'product_gift_wrap_cart_product', true );

		woocommerce_update_options( $this->settings );
	}
}

new WC_Product_Gift_Wrap();
