<?php
/**
 * Plugin Name: WooCommerce Product Fallbacks
 * Plugin URI: http://www.woothemes.com/products/woocommerce-product-fallbacks/
 * Description: Automatically display another product in place of an out-of-stock product.
 * Version: 1.0.0
 * Author: WooThemes
 * Author URI: http://woothemes.com/
 * Developer: Frankie Jarrett
 * Developer URI: http://frankiejarrett.com/
 * Depends: WooCommerce
 * Text Domain: woocommerce-product-fallbacks
 * Domain Path: /languages
 *
 * Copyright: © 2009-2015 WooThemes.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin Class
 *
 * Automatically display another product in place of an out-of-stock product.
 *
 * @version 1.0.0
 * @package WooCommerce
 * @author  Frankie Jarrett
 */
class WC_Product_Fallbacks {

	/**
	 * Hold class instance
	 *
	 * @access public
	 * @static
	 *
	 * @var WC_Product_Fallbacks
	 */
	public static $instance;

	/**
	 * Plugin version number
	 *
	 * @const string
	 */
	const VERSION = '1.0.0';

	/**
	 * Post meta key for storing fallbacks
	 *
	 * @const string
	 */
	const META_KEY = '_fallback_ids';

	/**
	 * Class constructor
	 *
	 * @access private
	 */
	private function __construct() {
		if ( ! $this->woocommerce_exists() ) {
			return;
		}

		define( 'WC_PRODUCT_FALLBACKS_PLUGIN', plugin_basename( __FILE__ ) );
		define( 'WC_PRODUCT_FALLBACKS_DIR', plugin_dir_path( __FILE__ ) );
		define( 'WC_PRODUCT_FALLBACKS_URL', plugins_url( '/', __FILE__ ) );
		define( 'WC_PRODUCT_FALLBACKS_INC_DIR', WC_PRODUCT_FALLBACKS_DIR . 'includes/' );

		// Add custom product option
		add_action( 'woocommerce_product_options_related', array( $this, 'product_options_related' ) );

		// Save custom product option as post meta
		add_action( 'woocommerce_process_product_meta', array( $this, 'process_product_meta' ) );

		// Remove fallbacks from queries
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );

		// Redirect to fallbacks
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );

		// Replace posts with their fallback
		add_action( 'the_post', array( $this, 'the_post' ) );
	}

	/**
	 * Return an active instance of this class
	 *
	 * @access public
	 * @since 1.0.0
	 * @static
	 *
	 * @return WC_Product_Fallbacks
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Returns true if WooCommerce exists
	 *
	 * Looks at the active list of plugins on the site to
	 * determine if WooCommerce is installed and activated.
	 *
	 * @access private
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function woocommerce_exists() {
		return in_array( 'woocommerce/woocommerce.php', (array) apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
	}

	/**
	 * Add custom product option field
	 *
	 * @access public
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function product_options_related() {
		global $post;

		$product_ids = array_filter( array_map( 'absint', (array) get_post_meta( $post->ID, self::META_KEY, true ) ) );
		$json_ids    = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( is_object( $product ) ) {
				$json_ids[ $product_id ] = wp_kses_post( html_entity_decode( $product->get_formatted_name() ) );
			}
		}
		?>
		<div class="options_group">
			<p class="form-field">
				<label for="fallback_ids"><?php _e( 'Fallbacks', 'woocommerce-product-fallbacks' ) ?></label>
				<input type="hidden" class="wc-product-search" style="width: 50%;" id="fallback_ids" name="fallback_ids" data-placeholder="<?php _e( 'Search for a product&hellip;', 'woocommerce' ) ?>" data-action="woocommerce_json_search_products" data-multiple="true" data-selected="<?php echo esc_attr( json_encode( $json_ids ) ) ?>" value="<?php echo esc_attr( implode( ',', array_keys( $json_ids ) ) ) ?>" /> <img class="help_tip" data-tip='<?php esc_attr_e( "Fallbacks are products that take the place of the currently viewed product when it is out-of-stock. You can add several fallbacks in order of priority in case they are also out-of-stock.", 'woocommerce-product-fallbacks' ) ?>' src="<?php echo esc_url( WC()->plugin_url() . '/assets/images/help.png' ) ?>" height="16" width="16" />
			</p>
		</div>
		<?php
	}

	/**
	 * Save custom product option as post meta
	 *
	 * @access public
	 * @since 1.0.0
	 * @param int $post_id
	 *
	 * @return void
	 */
	public function process_product_meta( $post_id ) {
		$fallbacks = isset( $_POST['fallback_ids'] ) ? array_filter( array_map( 'absint', explode( ',', $_POST['fallback_ids'] ) ) ) : array();

		update_post_meta( $post_id, self::META_KEY, $fallbacks );
	}

	/**
	 * Filter products being used as a fallback out of query results
	 *
	 * @access public
	 * @since 1.0.0
	 * @param array $query
	 *
	 * @return void
	 */
	public function pre_get_posts( $query ) {
		if (
			is_admin()
			||
			! empty( $query->is_single )
		) {
			return;
		}

		// @TODO: Decide how to handle duplicates in results

		// $query->set( 'post__not_in' => 157 );
	}

	/**
	 * Redirect out-of-stock product URLs to their fallback
	 *
	 * If an out-of-stock product does not have a fallback
	 * specified, no redirection will occur.
	 *
	 * If the first fallback is also out-of-stock, subsequent
	 * fallbacks in the order will be tried. If none of them
	 * are in-stock, no redirection will occur.
	 *
	 * @access public
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function template_redirect() {
		if ( is_admin() || ! is_singular( 'product' ) ) {
			return;
		}

		global $post;

		if ( 'instock' === get_post_meta( $post->ID, '_stock_status', true ) ) {
			return;
		}

		$fallback = self::get_fallback( $post->ID );

		if ( empty( $fallback ) ) {
			return;
		}

		$location = get_permalink( $fallback );

		wp_safe_redirect( $location, 302 );

		exit;
	}

	/**
	 * Filter product posts with their fallbacks
	 *
	 * When a product is out-of-stock, and a fallback exists,
	 * this filter will replace the values of the original
	 * WP_Post object with values from the fallback WP_Post
	 * object.
	 *
	 * The fallback product data becomes a veneer, covering
	 * the original product data so that everywhere the
	 * original product was featured, the fallback will be
	 * there instead.
	 *
	 * @access public
	 * @since 1.0.0
	 * @param WP_Post $post
	 *
	 * @return void
	 */
	public function the_post( $post ) {
		if ( is_admin() ) {
			return;
		}

		if ( 'instock' === get_post_meta( $post->ID, '_stock_status', true ) ) {
			return;
		}

		$fallback = self::get_fallback( $post->ID );

		if ( empty( $fallback ) ) {
			return;
		}

		$_post = get_post( $fallback );

		foreach ( $post as $key => $value ) {
			$post->$key = $_post->$key;
		}
	}

	/**
	 * Get a product's fallback ID
	 *
	 * Fallbacks are tried in the order they are saved in
	 * the product options under "Linked Products".
	 *
	 * If the first fallback is out-of-stock, subsequent
	 * fallbacks in the order will be tried. If none of them
	 * are in-stock, no fallback will be returned.
	 *
	 * @access public
	 * @since 1.0.0
	 * @static
	 * @param int $post_id
	 *
	 * @return int|bool  Post ID on success, false on failure
	 */
	public static function get_fallback( $post_id ) {
		$fallbacks = get_post_meta( $post_id, self::META_KEY, true );

		if ( empty( $fallbacks[0] ) ) {
			return false;
		}

		$out_of_stock = self::get_products_out_of_stock();

		foreach ( $fallbacks as $fallback ) {
			if ( in_array( $fallback, $out_of_stock ) ) {
				continue;
			}

			$_fallback = $fallback;

			break;
		}

		return ! empty( $_fallback ) ? absint( $_fallback ) : false;
	}

	/**
	 * Get an array of all product IDs that are out-of-stock
	 *
	 * The results of this method are useful in scenarios where
	 * you want to check if multuple products are out-of-stock
	 * using a loop.
	 *
	 * This way only one post meta query is required to compare
	 * all the IDs against each product in your loop instead of
	 * needing to make a new post meta query on each cycle.
	 *
	 * @access public
	 * @since 1.0.0
	 * @static
	 *
	 * @return array
	 */
	public static function get_products_out_of_stock() {
		global $wpdb;

		$results = $wpdb->get_col( "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_value = 'outofstock'" );

		if ( ! empty( $results ) ) {
			$results = array_map( 'absint', $results );
		}

		return (array) $results;
	}

}

/**
 * Instantiate the plugin instance
 *
 * @global WC_Product_Fallbacks
 */
$GLOBALS['wc_product_fallbacks'] = WC_Product_Fallbacks::get_instance();
