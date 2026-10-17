<?php
/**
 * Mteja Dynamic Router & Fullscreen Portal Delivery
 *
 * @package Mteja
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mteja_Router {

	/**
	 * Register actions and rewrite rules.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_portal_request' ) );
	}

	/**
	 * Get current portal slug.
	 *
	 * @return string
	 */
	public static function get_portal_slug() {
		$slug = get_option( 'mteja_portal_slug', 'mteja' );
		return sanitize_title( $slug ? $slug : 'mteja' );
	}

	/**
	 * Add rewrite rules for the fullscreen desk and widget embed.
	 */
	public static function add_rewrite_rules() {
		$slug = self::get_portal_slug();

		add_rewrite_rule(
			'^' . preg_quote( $slug, '/' ) . '/widget-embed/?$',
			'index.php?mteja_portal=1&mteja_view=widget',
			'top'
		);

		add_rewrite_rule(
			'^' . preg_quote( $slug, '/' ) . '(/.*)?$',
			'index.php?mteja_portal=1&mteja_view=desk',
			'top'
		);
	}

	/**
	 * Add custom query variables.
	 *
	 * @param array $vars Registered query vars.
	 * @return array
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = 'mteja_portal';
		$vars[] = 'mteja_view';
		return $vars;
	}

	/**
	 * Handle incoming portal requests.
	 */
	public static function handle_portal_request() {
		$is_portal = get_query_var( 'mteja_portal' );
		if ( ! $is_portal ) {
			return;
		}

		$view = get_query_var( 'mteja_view', 'desk' );

		// Widget Embed iframe view (public visitor facing)
		if ( 'widget' === $view ) {
			self::render_widget_embed();
			exit;
		}

		// Operations Desk (Staff facing) - Enforce Authentication
		if ( ! is_user_logged_in() ) {
			$login_url = home_url( '/login' );
			$redirect  = home_url( '/' . self::get_portal_slug() );

			// Check if a /login page actually exists
			$login_page = get_page_by_path( 'login' );
			if ( $login_page && 'publish' === $login_page->post_status ) {
				wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( $redirect ), $login_url ) );
			} else {
				wp_safe_redirect( wp_login_url( $redirect ) );
			}
			exit;
		}

		// Check permission
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have sufficient permissions to access the Mteja Operations Desk.', 'mteja' ),
				esc_html__( 'Access Denied', 'mteja' ),
				array( 'response' => 403 )
			);
		}

		self::render_fullscreen_desk();
		exit;
	}

	/**
	 * Render the Fullscreen React Operations Desk SPA.
	 */
	public static function render_fullscreen_desk() {
		$current_user = wp_get_current_user();
		$workspace_id = get_option( 'mteja_default_workspace_id', '' );

		$bootstrap_data = array(
			'apiUrl'         => rest_url( 'mteja/v1/' ),
			'restNonce'      => wp_create_nonce( 'wp_rest' ),
			'portalSlug'     => self::get_portal_slug(),
			'siteUrl'        => home_url(),
			'siteName'       => get_bloginfo( 'name' ),
			'workspaceId'    => $workspace_id,
			'currentUser'    => array(
				'id'          => $current_user->ID,
				'displayName' => $current_user->display_name,
				'email'       => $current_user->user_email,
				'avatar'      => get_avatar_url( $current_user->ID, array( 'size' => 64 ) ),
				'role'        => ! empty( $current_user->roles ) ? $current_user->roles[0] : 'agent',
				'canManage'   => current_user_can( 'manage_options' ),
				'isOwner'     => current_user_can( 'manage_options' ),
			),
			'hasWooCommerce' => class_exists( 'WooCommerce' ),
			'realtimeWsUrl'  => get_option( 'mteja_realtime_ws_url', '' ),
		);

		// Prevent search engine indexing of internal desk
		header( 'X-Robots-Tag: noindex, nofollow', true );
		?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content">
	<title>Mteja Operations Desk &mdash; <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
	<script src="https://cdn.tailwindcss.com"></script>
	<script>
		tailwind.config = {
			theme: {
				extend: {
					colors: {
						canvas: "#FAF9F6",
						surface: "#FFFFFF",
						"surface-subtle": "#F7F7F5",
						"surface-muted": "#F2F1ED",
						border: "#E5E5E2",
						"border-subtle": "#EDEDEB",
						pitch: "#111111",
						ink: "#26251E",
						"charcoal-text": "#242424",
						muted: "#71717A",
						amber: {
							action: "#C08532"
						},
						forest: {
							action: "#34785C"
						}
					},
					fontFamily: {
						sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
						mono: ['JetBrains Mono', 'Menlo', 'monospace']
					},
					borderRadius: {
						DEFAULT: "0.25rem",
						sm: "0.25rem",
						md: "0.5rem"
					}
				}
			}
		};
	</script>
	<style>
		:root {
			--color-parchment: #FAF9F6;
			--color-bone: #F2F1ED;
			--color-linen: #E6E5E0;
			--color-stone: #E5E5E2;
			--color-ink: #26251E;
			--color-amber: #C08532;
			--color-forest: #34785C;
		}
		body {
			background-color: #FAF9F6;
			color: #111111;
			font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
			-webkit-font-smoothing: antialiased;
			touch-action: manipulation;
			-webkit-tap-highlight-color: transparent;
			margin: 0;
			padding: 0;
			overflow: hidden;
		}
		/* Tactile 160ms scale feedback */
		button:active, .interactive-btn:active {
			transform: scale(0.97);
			transition: transform 160ms cubic-bezier(0.23, 1, 0.32, 1);
		}
	</style>
	<script id="mteja-bootstrap" type="application/json">
		<?php echo wp_json_encode( $bootstrap_data ); ?>
	</script>
</head>
<body class="bg-canvas text-pitch antialiased">
	<div id="mteja-portal-root"></div>
	<script src="<?php echo esc_url( plugins_url( 'assets/portal/portal-app.js', dirname( __FILE__ ) ) ); ?>?v=<?php echo esc_attr( time() ); ?>"></script>
</body>
</html>
		<?php
	}

	/**
	 * Render the Lightweight Iframe Widget Embed.
	 */
	public static function render_widget_embed() {
		$workspace_id = get_option( 'mteja_default_workspace_id', '' );
		?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Mteja Support Chat</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
	<script src="https://cdn.tailwindcss.com"></script>
	<style>
		body {
			background: transparent;
			font-family: 'Inter', sans-serif;
			margin: 0;
			padding: 0;
			-webkit-font-smoothing: antialiased;
		}
	</style>
</head>
<body>
	<div id="mteja-widget-frame-root"></div>
	<script>
		window.__MTEJA_WIDGET_CONFIG__ = {
			apiUrl: "<?php echo esc_url( rest_url( 'mteja/v1/' ) ); ?>",
			workspaceId: "<?php echo esc_js( $workspace_id ); ?>",
			siteName: "<?php echo esc_js( get_bloginfo( 'name' ) ); ?>"
		};
	</script>
	<script src="<?php echo esc_url( plugins_url( 'assets/widget/widget-embed.js', dirname( __FILE__ ) ) ); ?>?v=<?php echo esc_attr( time() ); ?>"></script>
</body>
</html>
		<?php
	}
}
