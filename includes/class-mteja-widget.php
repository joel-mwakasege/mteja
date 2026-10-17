<?php
/**
 * Mteja Frontend Widget Auto-Injector
 *
 * @package Mteja
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mteja_Widget {

	/**
	 * Hook widget injection into wp_footer.
	 */
	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'render_widget_script' ), 100 );
	}

	/**
	 * Output widget loader script in the footer if conditions match.
	 */
	public static function render_widget_script() {
		// Don't render inside admin
		if ( is_admin() ) {
			return;
		}

		// Check if auto-injection option is enabled
		$enabled = get_option( 'mteja_auto_inject_widget', '1' );
		if ( '1' !== (string) $enabled ) {
			return;
		}

		// Don't render inside the portal desk itself
		if ( get_query_var( 'mteja_portal' ) ) {
			return;
		}

		// Exclude sensitive WooCommerce checkout/cart pages if WooCommerce is present
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return;
		}

		$workspace_id = get_option( 'mteja_default_workspace_id', '' );
		$slug         = Mteja_Router::get_portal_slug();
		$embed_url    = home_url( '/' . $slug . '/widget-embed' );
		$loader_url   = plugins_url( 'assets/widget/widget.js', dirname( __FILE__ ) );
		?>
		<!-- Mteja Autonomous Support Widget Loader -->
		<script 
			src="<?php echo esc_url( $loader_url ); ?>" 
			id="mteja-widget-loader"
			data-embed-url="<?php echo esc_url( $embed_url ); ?>"
			data-workspace-id="<?php echo esc_attr( $workspace_id ); ?>"
			async 
			defer>
		</script>
		<?php
	}
}
