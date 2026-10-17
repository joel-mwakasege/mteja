<?php
/**
 * Plugin Name:       Mteja
 * Plugin URI:        https://mteja.io
 * Description:       Autonomous AI Customer Support & Dedicated Fullscreen Operations Desk for WordPress.
 * Version:           1.0.0
 * Author:            Mteja
 * Author URI:        https://mteja.io
 * License:           GPLv2 or later
 * Text Domain:       mteja
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package Mteja
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Plugin Constants
define( 'MTEJA_VERSION', '1.0.0' );
define( 'MTEJA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MTEJA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include Core Classes
require_once MTEJA_PLUGIN_DIR . 'includes/class-mteja-installer.php';
require_once MTEJA_PLUGIN_DIR . 'includes/class-mteja-router.php';
require_once MTEJA_PLUGIN_DIR . 'includes/class-mteja-patterns.php';
require_once MTEJA_PLUGIN_DIR . 'includes/class-mteja-widget.php';
require_once MTEJA_PLUGIN_DIR . 'includes/class-mteja-rest-controller.php';
require_once MTEJA_PLUGIN_DIR . 'includes/class-mteja-rag.php';

// Activation & Deactivation Hooks
register_activation_hook( __FILE__, array( 'Mteja_Installer', 'install' ) );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

// Initialize Components
function mteja_bootstrap_app() {
	static $initialized = false;
	if ( $initialized ) {
		return;
	}
	$initialized = true;

	// Ensure DB tables exist
	if ( ! get_option( 'mteja_default_workspace_id' ) ) {
		Mteja_Installer::install();
	}

	Mteja_Router::init();
	Mteja_Patterns::init();
	Mteja_Widget::init();
	Mteja_Rest_Controller::init();
}

if ( did_action( 'plugins_loaded' ) ) {
	mteja_bootstrap_app();
} else {
	add_action( 'plugins_loaded', 'mteja_bootstrap_app' );
}

// Admin Menu
add_action( 'admin_menu', function() {
	$slug       = Mteja_Router::get_portal_slug();
	$portal_url = home_url( '/' . $slug );

	// Main Menu
	add_menu_page(
		__( 'Mteja Support', 'mteja' ),
		__( 'Mteja Desk', 'mteja' ),
		'edit_posts',
		'mteja',
		'mteja_render_admin_launcher',
		'dashicons-format-chat',
		25
	);

	// Submenu: Open Operations Desk directly
	add_submenu_page(
		'mteja',
		__( 'Open Operations Desk', 'mteja' ),
		__( 'Launch Desk ↗', 'mteja' ),
		'edit_posts',
		$portal_url
	);
} );

/**
 * Render lightweight WP-Admin launcher directing users to the standalone Operations Desk.
 */
function mteja_render_admin_launcher() {
	$slug       = Mteja_Router::get_portal_slug();
	$portal_url = home_url( '/' . $slug );
	$is_owner   = current_user_can( 'manage_options' );
	?>
	<div class="wrap" style="max-width: 720px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #26251E; padding-top: 16px;">
		
		<div style="background: #FFFFFF; border: 1px solid #E2E2DE; border-radius: 8px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
			<div style="display: flex; align-items: center; gap: 14px; margin-bottom: 20px;">
				<span style="background: #26251E; color: #F7F7F4; border-radius: 8px; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 700;">M</span>
				<div>
					<h1 style="font-size: 22px; font-weight: 600; margin: 0; color: #26251E;">Mteja Operations Desk</h1>
					<p style="font-size: 13px; color: #666; margin: 4px 0 0 0;">Autonomous Customer Support &bull; Supabase pgvector RAG</p>
				</div>
			</div>

			<div style="background: #F7F7F4; border: 1px solid #E2E2DE; border-radius: 6px; padding: 18px 20px; margin-bottom: 24px;">
				<h3 style="margin: 0 0 6px 0; font-size: 14px; font-weight: 600; color: #26251E;">Fullscreen Standalone Workspace</h3>
				<p style="margin: 0 0 14px 0; font-size: 13px; color: #555; line-height: 1.5;">
					All support ticket queues, live customer telemetry, knowledge ingestion, and <?php echo $is_owner ? '<strong>Platform &amp; Supabase Database Settings</strong>' : 'support operations'; ?> are hosted exclusively in your dedicated fullscreen Operations Desk.
				</p>
				<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; background: #FFFFFF; border: 1px solid #E2E2DE; border-radius: 6px; padding: 10px 14px;">
					<code style="font-size: 13px; color: #26251E;"><?php echo esc_html( $portal_url ); ?></code>
					<a href="<?php echo esc_url( $portal_url ); ?>" target="_blank" class="button button-primary" style="background: #26251E; border-color: #26251E; color: #FFFFFF; font-weight: 600; padding: 0 16px; height: 34px; line-height: 32px;">
						Launch Operations Desk ↗
					</a>
				</div>
			</div>

			<?php if ( $is_owner ) : ?>
				<div style="border-left: 3px solid #34785C; padding-left: 14px; font-size: 12.5px; color: #444; line-height: 1.5;">
					<strong>Owner Access Notice:</strong> As the site administrator, you can configure your <strong>Supabase Server Build APIs</strong>, <strong>PostgreSQL Direct Connection</strong>, and <strong>FreeLLMAPI Unified Key</strong> securely by clicking the <strong>Settings</strong> icon in the Operations Desk navigation rail.
				</div>
			<?php else : ?>
				<div style="border-left: 3px solid #C08532; padding-left: 14px; font-size: 12.5px; color: #555; line-height: 1.5;">
					<strong>Agent Access:</strong> You are logged in with support agent privileges. Platform database configuration is restricted to the workspace owner.
				</div>
			<?php endif; ?>
		</div>

	</div>
	<?php
}

