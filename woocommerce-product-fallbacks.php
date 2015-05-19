<?php
/**
 * Plugin Name: WooCommerce Product Fallbacks
 * Plugin URI: http://woothemes.com/products/woocommerce-product-fallbacks/
 * Description: Automatically display other products in place of an out-of-stock product.
 * Version: 0.1.0
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
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin Class
 *
 * Automatically display other products in place of an out-of-stock product.
 *
 * @version 0.1.0
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
	const VERSION = '0.1.0';

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
	 * @since 0.1.0
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
	 * Check if WooCommerce exists
	 *
	 * @access private
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	private function woocommerce_exists() {
		return in_array( 'woocommerce/woocommerce.php', (array) apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
	}

	/**
	 *
	 *
	 * @access public
	 * @since 0.1.0
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
				<label for="fallback_ids"><?php _e( 'Fallbacks', 'woocommerce' ) ?></label>
				<input type="hidden" class="wc-product-search" style="width: 50%;" id="fallback_ids" name="fallback_ids" data-placeholder="<?php _e( 'Search for a product&hellip;', 'woocommerce' ) ?>" data-action="woocommerce_json_search_products" data-multiple="true" data-selected="<?php echo esc_attr( json_encode( $json_ids ) ) ?>" value="<?php echo esc_attr( implode( ',', array_keys( $json_ids ) ) ) ?>" /> <img class="help_tip" data-tip='<?php esc_attr_e( "Fallbacks are products that take the place of the currently viewed product when it is out of stock. You can add several fallbacks in order of priority in case they are also out of stock.", 'woocommerce' ) ?>' src="<?php echo esc_url( WC()->plugin_url() . '/assets/images/help.png' ) ?>" height="16" width="16" />
			</p>
		</div>
		<?php
	}

	/**
	 *
	 *
	 * @access public
	 * @since 0.1.0
	 * @param int $post_id
	 *
	 * @return void
	 */
	public function process_product_meta( $post_id ) {
		$fallbacks = isset( $_POST['fallback_ids'] ) ? array_filter( array_map( 'absint', explode( ',', $_POST['fallback_ids'] ) ) ) : array();

		update_post_meta( $post_id, self::META_KEY, array_map( 'absint', $fallback_ids ) );
	}

	/**
	 *
	 *
	 * @access public
	 * @since 0.1.0
	 * @param array $query
	 *
	 * @return void
	 */
	public function pre_get_posts( $query ) {
		if (
			is_admin()
			||
			! isset( $query->query['post_type'] )
			||
			'product' !== $query->query['post_type']
		) {
			return;
		}

		$query->set( 'post__not_in', array( 157 ) );
	}

	/**
	 *
	 *
	 * @access public
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function template_redirect() {
		global $post;

		$fallbacks = isset( $post->ID ) ? get_post_meta( $post->ID, self::META_KEY, true ) : array();

		if ( empty( $fallbacks[0] ) ) {
			return;
		}

		$location = get_permalink( $fallbacks[0] );

		wp_safe_redirect( $location, 302 );

		exit;
	}

	/**
	 *
	 *
	 * @access public
	 * @since 0.1.0
	 * @param WP_Post $post
	 *
	 * @return void
	 */
	public function the_post( $post ) {
		$fallbacks = get_post_meta( $post->ID, self::META_KEY, true );

		if ( empty( $fallbacks[0] ) ) {
			return;
		}

		$_post = get_post( $fallbacks[0] );

		foreach ( $post as $key => $value ) {
			$post->$key = $_post->$key;
		}
	}

}

/**
 * Instantiate the plugin instance
 *
 * @global WC_Product_Fallbacks
 */
$GLOBALS['wc_product_fallbacks'] = WC_Product_Fallbacks::get_instance();
