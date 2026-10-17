<?php
/**
 * Mteja Database & Schema Installer
 *
 * @package Mteja
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mteja_Installer {

	/**
	 * Run schema migrations and initialize plugin settings.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// 1. Workspaces Table
		$table_workspaces = $wpdb->prefix . 'mteja_workspaces';
		$sql_workspaces   = "CREATE TABLE $table_workspaces (
			id VARCHAR(36) NOT NULL,
			name VARCHAR(255) NOT NULL DEFAULT 'Default Workspace',
			avatar_url VARCHAR(500) DEFAULT '',
			system_prompt TEXT NOT NULL,
			similarity_threshold FLOAT NOT NULL DEFAULT 0.70,
			operating_hours LONGTEXT DEFAULT NULL,
			allowed_domains TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql_workspaces );

		// 2. Conversations Table
		$table_conversations = $wpdb->prefix . 'mteja_conversations';
		$sql_conversations   = "CREATE TABLE $table_conversations (
			id VARCHAR(36) NOT NULL,
			workspace_id VARCHAR(36) NOT NULL,
			visitor_token VARCHAR(128) NOT NULL,
			status VARCHAR(32) NOT NULL DEFAULT 'ai_handling',
			assigned_agent_id BIGINT(20) UNSIGNED DEFAULT NULL,
			visitor_email VARCHAR(191) DEFAULT NULL,
			customer_user_id BIGINT(20) UNSIGNED DEFAULT NULL,
			geo_country VARCHAR(64) DEFAULT NULL,
			geo_city VARCHAR(128) DEFAULT NULL,
			rating TINYINT(1) DEFAULT NULL,
			rating_comment TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY workspace_status (workspace_id, status),
			KEY visitor_token (visitor_token)
		) $charset_collate;";
		dbDelta( $sql_conversations );

		// 3. Messages Table
		$table_messages = $wpdb->prefix . 'mteja_messages';
		$sql_messages   = "CREATE TABLE $table_messages (
			id VARCHAR(36) NOT NULL,
			conversation_id VARCHAR(36) NOT NULL,
			sender_type VARCHAR(20) NOT NULL,
			sender_id VARCHAR(128) DEFAULT NULL,
			content LONGTEXT NOT NULL,
			attachments LONGTEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY conv_created (conversation_id, created_at)
		) $charset_collate;";
		dbDelta( $sql_messages );

		// 4. Knowledge Base Table
		$table_knowledge = $wpdb->prefix . 'mteja_knowledge';
		$sql_knowledge   = "CREATE TABLE $table_knowledge (
			id VARCHAR(36) NOT NULL,
			workspace_id VARCHAR(36) NOT NULL,
			attachment_id BIGINT(20) UNSIGNED DEFAULT NULL,
			file_name VARCHAR(255) NOT NULL,
			content_hash CHAR(64) NOT NULL,
			status VARCHAR(32) NOT NULL DEFAULT 'pending',
			chunk_count INT(11) NOT NULL DEFAULT 0,
			vector_count INT(11) NOT NULL DEFAULT 0,
			file_size BIGINT(20) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY workspace_status (workspace_id, status)
		) $charset_collate;";
		dbDelta( $sql_knowledge );

		// 5. Canned Snippets Table
		$table_snippets = $wpdb->prefix . 'mteja_snippets';
		$sql_snippets   = "CREATE TABLE $table_snippets (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			workspace_id VARCHAR(36) NOT NULL,
			shortcut VARCHAR(64) NOT NULL,
			content TEXT NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY workspace_shortcut (workspace_id, shortcut)
		) $charset_collate;";
		dbDelta( $sql_snippets );

		// Default Options
		add_option( 'mteja_portal_slug', 'mteja' );
		add_option( 'mteja_auto_inject_widget', '1' );
		add_option( 'mteja_offline_email_fallback', '1' );

		// Seed Default Workspace if none exists
		$existing_workspace = $wpdb->get_var( "SELECT id FROM $table_workspaces LIMIT 1" );
		if ( ! $existing_workspace ) {
			$default_id = wp_generate_uuid4();
			$wpdb->insert(
				$table_workspaces,
				array(
					'id'                   => $default_id,
					'name'                 => get_bloginfo( 'name' ) . ' Support Agent',
					'system_prompt'        => "You are Mteja, an autonomous customer support agent for " . get_bloginfo( 'name' ) . ". Answer inquiries politely using the uploaded knowledge base. If unsure or off-topic, decline gracefully or offer to connect with a human specialist.",
					'similarity_threshold' => 0.70,
					'operating_hours'      => wp_json_encode( array( 'enabled' => true, 'timezone' => 'UTC' ) ),
					'allowed_domains'      => home_url(),
				)
			);
			update_option( 'mteja_default_workspace_id', $default_id );
		}

		// Non-hard flush rewrite rules
		flush_rewrite_rules( false );
	}
}
