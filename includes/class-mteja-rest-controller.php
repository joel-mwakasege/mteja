<?php
/**
 * Mteja REST API Controller
 *
 * @package Mteja
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mteja_Rest_Controller {

	const NAMESPACE = 'mteja/v1';

	/**
	 * Register REST routes.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register API endpoints.
	 */
	public static function register_routes() {
		// 1. Auth Endpoint (for custom /login Gutenberg pattern)
		register_rest_route(
			self::NAMESPACE,
			'/auth/login',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_login' ),
				'permission_callback' => '__return_true',
			)
		);

		// 2. Workspaces
		register_rest_route(
			self::NAMESPACE,
			'/workspaces',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_workspace' ),
				'permission_callback' => array( __CLASS__, 'check_agent_permission' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/workspaces/(?P<id>[a-zA-Z0-9-]+)',
			array(
				'methods'             => 'PUT',
				'callback'            => array( __CLASS__, 'update_workspace' ),
				'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
			)
		);

		// 3. Conversations (Inbox Queue)
		register_rest_route(
			self::NAMESPACE,
			'/conversations',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_conversations' ),
				'permission_callback' => array( __CLASS__, 'check_agent_permission' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/conversations/(?P<id>[a-zA-Z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_conversation_detail' ),
				'permission_callback' => array( __CLASS__, 'check_agent_permission' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/conversations/(?P<id>[a-zA-Z0-9-]+)/takeover',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'takeover_conversation' ),
				'permission_callback' => array( __CLASS__, 'check_agent_permission' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/conversations/(?P<id>[a-zA-Z0-9-]+)/resolve',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'resolve_conversation' ),
				'permission_callback' => array( __CLASS__, 'check_agent_permission' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/conversations/(?P<id>[a-zA-Z0-9-]+)/reopen',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'reopen_conversation' ),
				'permission_callback' => array( __CLASS__, 'check_agent_permission' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/conversations/(?P<id>[a-zA-Z0-9-]+)/handover-ai',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handover_to_ai' ),
				'permission_callback' => array( __CLASS__, 'check_agent_permission' ),
			)
		);

		// 4. Messages Stream
		register_rest_route(
			self::NAMESPACE,
			'/conversations/(?P<id>[a-zA-Z0-9-]+)/messages',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_messages' ),
					'permission_callback' => array( __CLASS__, 'check_agent_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'send_message' ),
					'permission_callback' => array( __CLASS__, 'check_agent_permission' ),
				),
			)
		);

		// 5. Context Telemetry (WooCommerce + Geo)
		register_rest_route(
			self::NAMESPACE,
			'/conversations/(?P<id>[a-zA-Z0-9-]+)/context',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_conversation_context' ),
				'permission_callback' => array( __CLASS__, 'check_agent_permission' ),
			)
		);

		// 6. Knowledge Base Documents
		register_rest_route(
			self::NAMESPACE,
			'/knowledge',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_knowledge_docs' ),
					'permission_callback' => array( __CLASS__, 'check_agent_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'upload_knowledge_doc' ),
					'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/knowledge/(?P<id>[a-zA-Z0-9-]+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'delete_knowledge_doc' ),
				'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
			)
		);

		// 7. Canned Snippets
		register_rest_route(
			self::NAMESPACE,
			'/snippets',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_snippets' ),
					'permission_callback' => array( __CLASS__, 'check_agent_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create_snippet' ),
					'permission_callback' => array( __CLASS__, 'check_agent_permission' ),
				),
			)
		);

		// 8. Public Visitor Chat Endpoints (Used by widget)
		register_rest_route(
			self::NAMESPACE,
			'/visitor/session',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_visitor_session' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/visitor/messages',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_visitor_messages' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'send_visitor_message' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// 9. Supabase Connection Test
		register_rest_route(
			self::NAMESPACE,
			'/supabase/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'test_supabase_connection' ),
				'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
			)
		);

		// 10. Agent Studio RAG Playground
		register_rest_route(
			self::NAMESPACE,
			'/studio/test-rag',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'test_studio_rag' ),
				'permission_callback' => array( __CLASS__, 'check_agent_permission' ),
			)
		);

		// 10b. Knowledge Base Auto-Generated FAQs
		register_rest_route(
			self::NAMESPACE,
			'/kb/faqs',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_kb_faqs' ),
				'permission_callback' => array( __CLASS__, 'check_agent_permission' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/kb/faqs/generate',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'regenerate_kb_faqs' ),
				'permission_callback' => array( __CLASS__, 'check_agent_permission' ),
			)
		);

		// 11. Platform Settings (Owner Only)
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_platform_settings' ),
					'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'update_platform_settings' ),
					'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
				),
			)
		);
	}

	/**
	 * Get Platform Settings (Owner Only).
	 */
	public static function get_platform_settings() {
		$supabase_url  = Mteja_RAG::get_supabase_url();
		$supabase_pub  = Mteja_RAG::get_supabase_publishable_key();
		$supabase_sec  = Mteja_RAG::get_supabase_secret_key();
		$supabase_jwks = Mteja_RAG::get_supabase_jwks_url();

		$db_host = Mteja_RAG::get_supabase_db_host();
		$db_port = Mteja_RAG::get_supabase_db_port();
		$db_name = Mteja_RAG::get_supabase_db_name();
		$db_user = Mteja_RAG::get_supabase_db_user();
		$db_pass = Mteja_RAG::get_supabase_db_password();

		$freellm_key        = Mteja_RAG::get_freellm_api_key();
		$freellm_base_url   = Mteja_RAG::get_freellm_base_url();
		$freellm_chat_model = Mteja_RAG::get_freellm_chat_model();
		$freellm_emb_model  = Mteja_RAG::get_freellm_embedding_model();

		return rest_ensure_response(
			array(
				'supabase_url'             => $supabase_url,
				'supabase_publishable_key' => $supabase_pub,
				'supabase_secret_key'      => $supabase_sec,
				'supabase_jwks_url'        => $supabase_jwks,
				'supabase_db_host'         => $db_host,
				'supabase_db_port'         => $db_port,
				'supabase_db_name'         => $db_name,
				'supabase_db_user'         => $db_user,
				'supabase_db_password'     => $db_pass,
				'freellm_api_key'          => $freellm_key,
				'freellm_base_url'         => $freellm_base_url,
				'freellm_chat_model'       => $freellm_chat_model,
				'freellm_embedding_model'  => $freellm_emb_model,
				'openai_api_key'           => $freellm_key,
				'portal_slug'              => Mteja_Router::get_portal_slug(),
				'auto_inject_widget'       => ( '1' === get_option( 'mteja_auto_inject_widget', '1' ) ),
				'realtime_ws_url'          => get_option( 'mteja_realtime_ws_url', '' ),
				'sql_migration_script'     => Mteja_RAG::get_sql_migration_script(),
				'connection_string'        => Mteja_RAG::get_postgres_connection_string( false ),
				'connection_string_masked' => Mteja_RAG::get_postgres_connection_string( true ),
			)
		);
	}

	/**
	 * Update Platform Settings (Owner Only).
	 */
	public static function update_platform_settings( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		if ( isset( $params['supabase_url'] ) ) {
			update_option( 'mteja_supabase_url', esc_url_raw( trim( $params['supabase_url'] ) ) );
		}
		if ( isset( $params['supabase_publishable_key'] ) ) {
			update_option( 'mteja_supabase_publishable_key', sanitize_text_field( trim( $params['supabase_publishable_key'] ) ) );
		}
		if ( isset( $params['supabase_secret_key'] ) && '' !== trim( $params['supabase_secret_key'] ) ) {
			update_option( 'mteja_supabase_secret_key', sanitize_text_field( trim( $params['supabase_secret_key'] ) ) );
		}
		if ( isset( $params['supabase_jwks_url'] ) ) {
			update_option( 'mteja_supabase_jwks_url', esc_url_raw( trim( $params['supabase_jwks_url'] ) ) );
		}

		if ( isset( $params['supabase_db_host'] ) ) {
			update_option( 'mteja_supabase_db_host', sanitize_text_field( trim( $params['supabase_db_host'] ) ) );
		}
		if ( isset( $params['supabase_db_port'] ) ) {
			$port = absint( $params['supabase_db_port'] );
			update_option( 'mteja_supabase_db_port', $port ? (string) $port : '5432' );
		}
		if ( isset( $params['supabase_db_name'] ) ) {
			update_option( 'mteja_supabase_db_name', sanitize_text_field( trim( $params['supabase_db_name'] ) ) );
		}
		if ( isset( $params['supabase_db_user'] ) ) {
			update_option( 'mteja_supabase_db_user', sanitize_text_field( trim( $params['supabase_db_user'] ) ) );
		}
		if ( isset( $params['supabase_db_password'] ) && '' !== trim( $params['supabase_db_password'] ) ) {
			update_option( 'mteja_supabase_db_password', trim( $params['supabase_db_password'] ) );
		}

		if ( isset( $params['freellm_api_key'] ) ) {
			$k = sanitize_text_field( trim( $params['freellm_api_key'] ) );
			update_option( 'mteja_freellm_api_key', $k );
			update_option( 'mteja_openai_api_key', $k );
		} elseif ( isset( $params['openai_api_key'] ) && '' !== trim( $params['openai_api_key'] ) ) {
			$k = sanitize_text_field( trim( $params['openai_api_key'] ) );
			update_option( 'mteja_freellm_api_key', $k );
			update_option( 'mteja_openai_api_key', $k );
		}

		if ( isset( $params['freellm_base_url'] ) ) {
			update_option( 'mteja_freellm_base_url', esc_url_raw( trim( $params['freellm_base_url'] ) ) );
		}
		if ( isset( $params['freellm_chat_model'] ) ) {
			update_option( 'mteja_freellm_chat_model', sanitize_text_field( trim( $params['freellm_chat_model'] ) ) );
		}
		if ( isset( $params['freellm_embedding_model'] ) ) {
			update_option( 'mteja_freellm_embedding_model', sanitize_text_field( trim( $params['freellm_embedding_model'] ) ) );
		}

		if ( isset( $params['portal_slug'] ) ) {
			$slug = sanitize_title( $params['portal_slug'] );
			if ( ! empty( $slug ) ) {
				update_option( 'mteja_portal_slug', $slug );
			}
		}

		if ( isset( $params['auto_inject_widget'] ) ) {
			$auto = $params['auto_inject_widget'] ? '1' : '0';
			update_option( 'mteja_auto_inject_widget', $auto );
		}

		if ( isset( $params['realtime_ws_url'] ) ) {
			update_option( 'mteja_realtime_ws_url', sanitize_text_field( trim( $params['realtime_ws_url'] ) ) );
		}

		flush_rewrite_rules( false );

		return self::get_platform_settings();
	}

	/**
	 * Test Supabase & RAG connection.
	 */
	public static function test_supabase_connection() {
		return rest_ensure_response( Mteja_RAG::test_connection() );
	}

	/**
	 * Check if request is authenticated as support agent.
	 */
	public static function check_agent_permission() {
		return current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Check if request has admin rights.
	 */
	public static function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle async login from the Gutenberg login card.
	 */
	public static function handle_login( $request ) {
		$creds = array(
			'user_login'    => sanitize_user( $request->get_param( 'username' ) ),
			'user_password' => $request->get_param( 'password' ),
			'remember'      => true,
		);

		$user = wp_signon( $creds, false );

		if ( is_wp_error( $user ) ) {
			return new WP_Error(
				'auth_failed',
				$user->get_error_message(),
				array( 'status' => 401 )
			);
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		$slug = Mteja_Router::get_portal_slug();
		return rest_ensure_response(
			array(
				'success'     => true,
				'redirect_to' => home_url( '/' . $slug ),
				'user'        => array(
					'id'          => $user->ID,
					'displayName' => $user->display_name,
					'email'       => $user->user_email,
				),
			)
		);
	}

	/**
	 * Get workspace data array directly.
	 *
	 * @return array
	 */
	public static function get_workspace_data() {
		global $wpdb;
		$table = $wpdb->prefix . 'mteja_workspaces';
		$row   = $wpdb->get_row( "SELECT * FROM $table LIMIT 1", ARRAY_A );
		if ( is_array( $row ) ) {
			$row['offline_email'] = (bool) get_option( 'mteja_offline_email_fallback', 1 );
			return $row;
		}
		return array();
	}

	/**
	 * Get default workspace config.
	 */
	public static function get_workspace() {
		global $wpdb;
		$table = $wpdb->prefix . 'mteja_workspaces';
		$row   = $wpdb->get_row( "SELECT * FROM $table LIMIT 1", ARRAY_A );

		if ( ! $row ) {
			return new WP_Error( 'not_found', 'Workspace not found', array( 'status' => 404 ) );
		}

		$row['similarity_threshold'] = (float) $row['similarity_threshold'];
		$row['operating_hours']      = json_decode( $row['operating_hours'], true );
		$row['offline_email']        = (bool) get_option( 'mteja_offline_email_fallback', 1 );

		return rest_ensure_response( $row );
	}

	/**
	 * Update workspace config.
	 */
	public static function update_workspace( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'mteja_workspaces';
		$id    = sanitize_text_field( $request['id'] );

		$data = array();
		if ( $request->has_param( 'name' ) ) {
			$data['name'] = sanitize_text_field( $request->get_param( 'name' ) );
		}
		if ( $request->has_param( 'system_prompt' ) ) {
			$data['system_prompt'] = sanitize_textarea_field( $request->get_param( 'system_prompt' ) );
		}
		if ( $request->has_param( 'similarity_threshold' ) ) {
			$data['similarity_threshold'] = (float) $request->get_param( 'similarity_threshold' );
		}
		if ( $request->has_param( 'allowed_domains' ) ) {
			$data['allowed_domains'] = sanitize_textarea_field( $request->get_param( 'allowed_domains' ) );
		}
		if ( $request->has_param( 'offline_email' ) ) {
			update_option( 'mteja_offline_email_fallback', $request->get_param( 'offline_email' ) ? 1 : 0 );
		}

		if ( ! empty( $data ) ) {
			$wpdb->update( $table, $data, array( 'id' => $id ) );
		}

		return self::get_workspace();
	}

	/**
	 * Get conversations list for queue feed.
	 */
	public static function get_conversations( $request ) {
		global $wpdb;
		$table_conv = $wpdb->prefix . 'mteja_conversations';
		$table_msg  = $wpdb->prefix . 'mteja_messages';

		$filter = sanitize_text_field( $request->get_param( 'filter' ) );
		$search = sanitize_text_field( $request->get_param( 'search' ) );

		$where = 'WHERE 1=1';
		if ( 'escalations' === $filter || 'waiting' === $filter ) {
			$where .= " AND status IN ('human_waiting', 'human_active')";
		} elseif ( 'resolved' === $filter ) {
			$where .= " AND status = 'resolved'";
		}

		if ( ! empty( $search ) ) {
			$where .= $wpdb->prepare( " AND (visitor_email LIKE %s OR visitor_token LIKE %s)", "%$search%", "%$search%" );
		}

		$query = "SELECT c.*, 
			(SELECT content FROM $table_msg WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
			(SELECT created_at FROM $table_msg WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_at
			FROM $table_conv c
			$where
			ORDER BY updated_at DESC
			LIMIT 50";

		$results = $wpdb->get_results( $query, ARRAY_A );
		return rest_ensure_response( is_array( $results ) ? $results : array() );
	}

	/**
	 * Get messages for a specific conversation.
	 */
	public static function get_messages( $request ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'mteja_messages';
		$conv_id = sanitize_text_field( $request['id'] );

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE conversation_id = %s ORDER BY created_at ASC",
				$conv_id
			),
			ARRAY_A
		);

		return rest_ensure_response( $messages );
	}

	/**
	 * Send an agent reply or internal whisper note.
	 */
	public static function send_message( $request ) {
		global $wpdb;
		$table_msg  = $wpdb->prefix . 'mteja_messages';
		$table_conv = $wpdb->prefix . 'mteja_conversations';

		$conv_id     = sanitize_text_field( $request['id'] );
		$content     = sanitize_textarea_field( $request->get_param( 'content' ) );
		$sender_type = sanitize_text_field( $request->get_param( 'sender_type' ) ); // 'agent' or 'whisper'
		if ( ! in_array( $sender_type, array( 'agent', 'whisper' ), true ) ) {
			$sender_type = 'agent';
		}

		// Check for /aisupport command in agent message
		$is_ai_command = false;
		if ( 'agent' === $sender_type && preg_match( '/^\s*\/(aisupport|ai|handover)\b/i', $content ) ) {
			$is_ai_command = true;
			$remaining     = trim( preg_replace( '/^\s*\/(aisupport|ai|handover)\b/i', '', $content ) );
			if ( empty( $remaining ) ) {
				$content = 'I am transferring this chat back to our autonomous AI assistant. Feel free to ask any other questions!';
			} else {
				$content = $remaining;
			}
		}

		$msg_id = wp_generate_uuid4();
		$wpdb->insert(
			$table_msg,
			array(
				'id'              => $msg_id,
				'conversation_id' => $conv_id,
				'sender_type'     => $sender_type,
				'sender_id'       => (string) get_current_user_id(),
				'content'         => $content,
				'created_at'      => current_time( 'mysql' ),
			)
		);

		// Touch conversation updated_at and update status
		if ( $is_ai_command ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $table_conv SET status = 'ai_handling', assigned_agent_id = NULL, updated_at = %s WHERE id = %s",
					current_time( 'mysql' ),
					$conv_id
				)
			);
			// Also insert an internal whisper note
			$wpdb->insert(
				$table_msg,
				array(
					'id'              => wp_generate_uuid4(),
					'conversation_id' => $conv_id,
					'sender_type'     => 'whisper',
					'content'         => 'Agent used /aisupport command. Chat returned to Autonomous AI.',
					'created_at'      => current_time( 'mysql' ),
				)
			);
		} elseif ( 'agent' === $sender_type ) {
			$wpdb->update(
				$table_conv,
				array(
					'status'            => 'human_active',
					'assigned_agent_id' => get_current_user_id(),
					'updated_at'        => current_time( 'mysql' ),
				),
				array( 'id' => $conv_id )
			);
		} else {
			$wpdb->update(
				$table_conv,
				array( 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $conv_id )
			);
		}

		return rest_ensure_response(
			array(
				'id'              => $msg_id,
				'conversation_id' => $conv_id,
				'sender_type'     => $sender_type,
				'content'         => $content,
				'created_at'      => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Get single conversation detail.
	 */
	public static function get_conversation_detail( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'mteja_conversations';
		$id    = sanitize_text_field( $request['id'] );

		$conv = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %s", $id ), ARRAY_A );
		if ( ! $conv ) {
			return new WP_Error( 'not_found', 'Conversation not found', array( 'status' => 404 ) );
		}

		return rest_ensure_response( $conv );
	}

	/**
	 * Takeover a conversation by human agent.
	 */
	public static function takeover_conversation( $request ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'mteja_conversations';
		$conv_id = sanitize_text_field( $request['id'] );

		$wpdb->update(
			$table,
			array(
				'status'            => 'human_active',
				'assigned_agent_id' => get_current_user_id(),
				'updated_at'        => current_time( 'mysql' ),
			),
			array( 'id' => $conv_id )
		);

		return rest_ensure_response( array( 'success' => true, 'status' => 'human_active' ) );
	}

	/**
	 * Resolve a conversation.
	 */
	public static function resolve_conversation( $request ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'mteja_conversations';
		$conv_id = sanitize_text_field( $request['id'] );

		$wpdb->update(
			$table,
			array(
				'status'     => 'resolved',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $conv_id )
		);

		return rest_ensure_response( array( 'success' => true, 'status' => 'resolved' ) );
	}

	/**
	 * Reopen a conversation.
	 */
	public static function reopen_conversation( $request ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'mteja_conversations';
		$conv_id = sanitize_text_field( $request['id'] );

		$conv = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %s", $conv_id ) );
		if ( ! $conv ) {
			return new WP_Error( 'not_found', 'Conversation not found', array( 'status' => 404 ) );
		}

		// Reopening always returns conversation to autonomous AI handling to repeat the support circle
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $table SET status = 'ai_handling', assigned_agent_id = NULL, updated_at = %s WHERE id = %s",
				current_time( 'mysql' ),
				$conv_id
			)
		);

		return rest_ensure_response( array( 'success' => true, 'status' => 'ai_handling' ) );
	}

	/**
	 * Handover conversation back to autonomous AI.
	 */
	public static function handover_to_ai( $request ) {
		global $wpdb;
		$table_conv = $wpdb->prefix . 'mteja_conversations';
		$table_msg  = $wpdb->prefix . 'mteja_messages';
		$conv_id    = sanitize_text_field( $request['id'] );

		$conv = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_conv WHERE id = %s", $conv_id ) );
		if ( ! $conv ) {
			return new WP_Error( 'not_found', 'Conversation not found', array( 'status' => 404 ) );
		}

		// Set status to ai_handling and unassign agent
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $table_conv SET status = 'ai_handling', assigned_agent_id = NULL, updated_at = %s WHERE id = %s",
				current_time( 'mysql' ),
				$conv_id
			)
		);

		// Record an internal whisper note indicating the handover
		$wpdb->insert(
			$table_msg,
			array(
				'id'              => wp_generate_uuid4(),
				'conversation_id' => $conv_id,
				'sender_type'     => 'whisper',
				'content'         => 'Operator transferred chat back to Autonomous AI Support.',
				'created_at'      => current_time( 'mysql' ),
			)
		);

		// Check if the latest message was from the visitor.
		// If so, trigger immediate AI RAG response so the visitor gets an answer!
		$latest_msg = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_msg WHERE conversation_id = %s ORDER BY created_at DESC LIMIT 1",
				$conv_id
			)
		);

		if ( $latest_msg && 'visitor' === $latest_msg->sender_type && Mteja_RAG::is_configured() ) {
			$ws_data       = self::get_workspace_data();
			$ws_id         = ! empty( $conv->workspace_id ) ? $conv->workspace_id : ( isset( $ws_data['id'] ) ? $ws_data['id'] : '' );
			$threshold     = isset( $ws_data['similarity_threshold'] ) ? (float) $ws_data['similarity_threshold'] : 0.70;
			$system_prompt = isset( $ws_data['system_prompt'] ) ? $ws_data['system_prompt'] : '';

			$chunks = Mteja_RAG::search_chunks( $latest_msg->content, $ws_id, $threshold, 4 );
			if ( empty( $chunks ) ) {
				$chunks = Mteja_RAG::search_chunks( $latest_msg->content, $ws_id, 0.35, 3 );
			}

			$history = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT sender_type, content FROM $table_msg WHERE conversation_id = %s AND sender_type IN ('visitor', 'ai', 'agent') ORDER BY created_at ASC LIMIT 10",
					$conv_id
				),
				ARRAY_A
			);

			$ai_reply = Mteja_RAG::generate_chat_completion( $history, $system_prompt, $chunks );
			if ( ! is_wp_error( $ai_reply ) && ! empty( $ai_reply ) ) {
				$clean_reply = trim( str_replace( '[ESCALATE_TO_HUMAN]', '', $ai_reply ) );
				$wpdb->insert(
					$table_msg,
					array(
						'id'              => wp_generate_uuid4(),
						'conversation_id' => $conv_id,
						'sender_type'     => 'ai',
						'content'         => $clean_reply,
						'created_at'      => current_time( 'mysql' ),
					)
				);
			}
		}

		return rest_ensure_response( array( 'success' => true, 'status' => 'ai_handling' ) );
	}

	/**
	 * Get conversation context (WooCommerce order history or Visitor lead info).
	 */
	public static function get_conversation_context( $request ) {
		global $wpdb;
		$table_conv = $wpdb->prefix . 'mteja_conversations';
		$conv_id    = sanitize_text_field( $request['id'] );

		$conv = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_conv WHERE id = %s", $conv_id ),
			ARRAY_A
		);

		$context = array(
			'customer'          => array(
				'name'    => $conv['visitor_email'] ? strtok( $conv['visitor_email'], '@' ) : 'Visitor #' . substr( $conv['visitor_token'], 0, 6 ),
				'email'   => $conv['visitor_email'] ? $conv['visitor_email'] : 'Not captured yet',
				'geo'     => ( ! empty( $conv['geo_city'] ) || ! empty( $conv['geo_country'] ) ) ? trim( ( $conv['geo_city'] ? $conv['geo_city'] . ', ' : '' ) . ( $conv['geo_country'] ? $conv['geo_country'] : '' ) ) : 'Location unknown',
				'status'  => $conv['status'],
				'started' => $conv['created_at'],
			),
			'hasWooCommerce'    => class_exists( 'WooCommerce' ),
			'wooCommerceOrders' => array(),
		);

		// If WooCommerce is active, query customer orders
		if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_orders' ) ) {
			$customer_email = $conv['visitor_email'];
			if ( $customer_email ) {
				$orders = wc_get_orders(
					array(
						'billing_email' => $customer_email,
						'limit'         => 3,
						'orderby'       => 'date',
						'order'         => 'DESC',
					)
				);
				foreach ( $orders as $order ) {
					$context['wooCommerceOrders'][] = array(
						'id'       => $order->get_id(),
						'total'    => $order->get_formatted_order_total(),
						'status'   => $order->get_status(),
						'date'     => $order->get_date_created()->date( 'M j, Y' ),
						'item_count' => $order->get_item_count(),
					);
				}
			}
		}

		return rest_ensure_response( $context );
	}

	/**
	 * Get Knowledge Base documents list.
	 */
	public static function get_knowledge_docs() {
		global $wpdb;
		$table = $wpdb->prefix . 'mteja_knowledge';
		$docs  = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A );
		return rest_ensure_response( is_array( $docs ) ? $docs : array() );
	}

	/**
	 * Upload Knowledge Base Document.
	 */
	/**
	 * Upload Knowledge Base Document.
	 */
	public static function upload_knowledge_doc( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'mteja_knowledge';

		$raw_files = $request->get_file_params();
		if ( empty( $raw_files ) ) {
			return new WP_Error( 'no_file', 'No file was provided', array( 'status' => 400 ) );
		}

		$normalized_files = array();

		// Handle files[] array format
		if ( ! empty( $raw_files['files'] ) && is_array( $raw_files['files']['name'] ) ) {
			foreach ( $raw_files['files']['name'] as $idx => $fname ) {
				if ( ! empty( $raw_files['files']['tmp_name'][ $idx ] ) ) {
					$normalized_files[] = array(
						'name'     => $fname,
						'tmp_name' => $raw_files['files']['tmp_name'][ $idx ],
						'size'     => isset( $raw_files['files']['size'][ $idx ] ) ? (int) $raw_files['files']['size'][ $idx ] : 0,
					);
				}
			}
		} elseif ( ! empty( $raw_files['file'] ) && is_array( $raw_files['file']['name'] ) ) {
			// Handle file[] array format
			foreach ( $raw_files['file']['name'] as $idx => $fname ) {
				if ( ! empty( $raw_files['file']['tmp_name'][ $idx ] ) ) {
					$normalized_files[] = array(
						'name'     => $fname,
						'tmp_name' => $raw_files['file']['tmp_name'][ $idx ],
						'size'     => isset( $raw_files['file']['size'][ $idx ] ) ? (int) $raw_files['file']['size'][ $idx ] : 0,
					);
				}
			}
		} elseif ( ! empty( $raw_files['file'] ) && is_string( $raw_files['file']['name'] ) ) {
			// Handle single file under 'file'
			$normalized_files[] = array(
				'name'     => $raw_files['file']['name'],
				'tmp_name' => $raw_files['file']['tmp_name'],
				'size'     => isset( $raw_files['file']['size'] ) ? (int) $raw_files['file']['size'] : 0,
			);
		} else {
			// Handle file_0, file_1, or any other keys
			foreach ( $raw_files as $f ) {
				if ( is_array( $f ) && ! empty( $f['tmp_name'] ) && is_string( $f['tmp_name'] ) ) {
					$normalized_files[] = array(
						'name'     => isset( $f['name'] ) ? $f['name'] : 'document',
						'tmp_name' => $f['tmp_name'],
						'size'     => isset( $f['size'] ) ? (int) $f['size'] : 0,
					);
				}
			}
		}

		if ( empty( $normalized_files ) ) {
			return new WP_Error( 'no_file', 'No valid uploaded files were found.', array( 'status' => 400 ) );
		}

		$ws_id   = get_option( 'mteja_default_workspace_id', '' );
		$results = array();

		foreach ( $normalized_files as $file ) {
			$name     = sanitize_file_name( $file['name'] );
			$tmp_path = $file['tmp_name'];

			if ( ! file_exists( $tmp_path ) || ! is_readable( $tmp_path ) ) {
				$results[] = array(
					'success'   => false,
					'file_name' => $name,
					'error'     => 'Uploaded file could not be read.',
				);
				continue;
			}

			$hash   = hash_file( 'sha256', $tmp_path );
			$doc_id = wp_generate_uuid4();

			// 1. Extract plain text from uploaded file
			$raw_text = Mteja_RAG::extract_text_from_file( $tmp_path, $name );
			if ( empty( trim( $raw_text ) ) ) {
				$results[] = array(
					'success'   => false,
					'file_name' => $name,
					'error'     => 'Could not extract readable text from file.',
				);
				continue;
			}

			// 2. Break into semantic chunks
			$chunks       = Mteja_RAG::chunk_text( $raw_text );
			$chunk_count  = count( $chunks );
			$vector_count = 0;
			$status       = 'pending';

			// 3. If Supabase & FreeLLMAPI are configured, generate vector embeddings and write to Supabase
			if ( Mteja_RAG::is_configured() && $chunk_count > 0 ) {
				$insert_res = Mteja_RAG::insert_chunks( $ws_id, $doc_id, $chunks );
				if ( ! is_wp_error( $insert_res ) && true === $insert_res ) {
					$status       = 'synced';
					$vector_count = $chunk_count;
				} else {
					$status = 'failed';
				}
			}

			$wpdb->insert(
				$table,
				array(
					'id'           => $doc_id,
					'workspace_id' => $ws_id,
					'file_name'    => $name,
					'content_hash' => $hash,
					'status'       => $status,
					'chunk_count'  => $chunk_count,
					'vector_count' => $vector_count,
					'file_size'    => isset( $file['size'] ) ? (int) $file['size'] : 0,
					'created_at'   => current_time( 'mysql' ),
				)
			);

			$results[] = array(
				'success'      => true,
				'id'           => $doc_id,
				'file_name'    => $name,
				'status'       => $status,
				'chunk_count'  => $chunk_count,
				'vector_count' => $vector_count,
				'file_size'    => isset( $file['size'] ) ? (int) $file['size'] : 0,
			);
		}

		if ( 1 === count( $results ) ) {
			$res_out              = $results[0];
			$res_out['documents'] = $results;
			return rest_ensure_response( $res_out );
		}

		return rest_ensure_response(
			array(
				'success'   => true,
				'documents' => $results,
				'count'     => count( $results ),
			)
		);
	}

	/**
	 * Delete Knowledge Base Document.
	 */
	public static function delete_knowledge_doc( $request ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'mteja_knowledge';
		$doc_id = sanitize_text_field( $request['id'] );

		// Cascade delete document chunks from Supabase pgvector
		Mteja_RAG::delete_document_chunks( $doc_id );

		$wpdb->delete( $table, array( 'id' => $doc_id ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Get Canned Snippets.
	 */
	public static function get_snippets() {
		global $wpdb;
		$table    = $wpdb->prefix . 'mteja_snippets';
		$snippets = $wpdb->get_results( "SELECT * FROM $table ORDER BY shortcut ASC", ARRAY_A );
		return rest_ensure_response( is_array( $snippets ) ? $snippets : array() );
	}

	/**
	 * Create Canned Snippet.
	 */
	public static function create_snippet( $request ) {
		global $wpdb;
		$table    = $wpdb->prefix . 'mteja_snippets';
		$shortcut = sanitize_text_field( $request->get_param( 'shortcut' ) );
		$content  = sanitize_textarea_field( $request->get_param( 'content' ) );

		$wpdb->insert(
			$table,
			array(
				'workspace_id' => get_option( 'mteja_default_workspace_id', '' ),
				'shortcut'     => $shortcut,
				'content'      => $content,
				'created_at'   => current_time( 'mysql' ),
			)
		);

		return rest_ensure_response( array( 'success' => true, 'shortcut' => $shortcut ) );
	}

	/**
	 * Public visitor session initialization.
	 */
	public static function create_visitor_session( $request ) {
		global $wpdb;
		$table_conv = $wpdb->prefix . 'mteja_conversations';
		$token      = sanitize_text_field( $request->get_param( 'visitor_token' ) );
		$force_new  = (bool) $request->get_param( 'force_new' );

		if ( ! $token ) {
			$token = wp_generate_uuid4();
		}

		if ( ! $force_new ) {
			$existing = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM $table_conv WHERE visitor_token = %s ORDER BY created_at DESC LIMIT 1", $token ),
				ARRAY_A
			);

			if ( $existing ) {
				return rest_ensure_response( $existing );
			}
		}

		$conv_id = wp_generate_uuid4();
		$wpdb->insert(
			$table_conv,
			array(
				'id'            => $conv_id,
				'workspace_id'  => get_option( 'mteja_default_workspace_id', '' ),
				'visitor_token' => $token,
				'status'        => 'ai_handling',
				'geo_country'   => 'Tanzania',
				'geo_city'      => 'Dar es Salaam',
				'created_at'    => current_time( 'mysql' ),
			)
		);

		return rest_ensure_response(
			array(
				'id'            => $conv_id,
				'visitor_token' => $token,
				'status'        => 'ai_handling',
			)
		);
	}

	/**
	 * Public visitor messages retrieval.
	 */
	public static function get_visitor_messages( $request ) {
		global $wpdb;
		$table_msg = $wpdb->prefix . 'mteja_messages';
		$conv_id   = sanitize_text_field( $request->get_param( 'conversation_id' ) );

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_msg WHERE conversation_id = %s AND sender_type != 'whisper' ORDER BY created_at ASC",
				$conv_id
			),
			ARRAY_A
		);

		return rest_ensure_response( is_array( $messages ) ? $messages : array() );
	}

	/**
	 * Public visitor sends message.
	 * Executes autonomous pgvector RAG response and manages human operator handover.
	 */
	public static function send_visitor_message( $request ) {
		global $wpdb;
		$table_msg  = $wpdb->prefix . 'mteja_messages';
		$table_conv = $wpdb->prefix . 'mteja_conversations';

		$conv_id = sanitize_text_field( $request->get_param( 'conversation_id' ) );
		$content = sanitize_textarea_field( $request->get_param( 'content' ) );

		if ( empty( $conv_id ) || empty( $content ) ) {
			return new WP_Error( 'invalid_data', 'Conversation ID and message content are required.', array( 'status' => 400 ) );
		}

		$conv = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_conv WHERE id = %s", $conv_id ) );
		if ( ! $conv ) {
			return new WP_Error( 'not_found', 'Conversation not found.', array( 'status' => 404 ) );
		}

		// Check if visitor message contains an email address and save if conversation has none
		if ( empty( $conv->visitor_email ) ) {
			if ( preg_match( '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $content, $matches ) ) {
				$captured_email = sanitize_email( $matches[0] );
				if ( is_email( $captured_email ) ) {
					$wpdb->update(
						$table_conv,
						array( 'visitor_email' => $captured_email ),
						array( 'id' => $conv_id )
					);
				}
			}
		}

		// 1. Insert the visitor message
		$msg_id = wp_generate_uuid4();
		$wpdb->insert(
			$table_msg,
			array(
				'id'              => $msg_id,
				'conversation_id' => $conv_id,
				'sender_type'     => 'visitor',
				'content'         => $content,
				'created_at'      => current_time( 'mysql' ),
			)
		);

		// Check if conversation should return to autonomous AI handling:
		// 1. Explicit command /aisupport or /ai anywhere in message
		// 2. If currently resolved -> ALWAYS reopen to AI handling and clear agent assignment
		// 3. If currently human_waiting and NO agent is assigned -> AI should process the user message so visitor is not left stranded
		// 4. Or if conversation was in human_active and agent has been idle for > 15 minutes
		$trimmed_content     = trim( $content );
		$is_ai_command       = ( stripos( $trimmed_content, '/aisupport' ) !== false || stripos( $trimmed_content, '/ai' ) !== false );
		$should_return_to_ai = false;

		if ( $is_ai_command || 'resolved' === $conv->status ) {
			$should_return_to_ai = true;
		} elseif ( 'human_waiting' === $conv->status && empty( $conv->assigned_agent_id ) ) {
			$should_return_to_ai = true;
		} elseif ( in_array( $conv->status, array( 'human_active', 'human_waiting' ), true ) && ! empty( $conv->assigned_agent_id ) ) {
			$last_agent_msg = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT created_at FROM $table_msg WHERE conversation_id = %s AND sender_type = 'agent' ORDER BY created_at DESC LIMIT 1",
					$conv_id
				)
			);
			if ( ! $last_agent_msg || ( strtotime( current_time( 'mysql' ) ) - strtotime( $last_agent_msg->created_at ) ) > 600 ) {
				$should_return_to_ai = true;
			}
		}

		if ( $should_return_to_ai ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $table_conv SET status = 'ai_handling', assigned_agent_id = NULL, updated_at = %s WHERE id = %s",
					current_time( 'mysql' ),
					$conv_id
				)
			);
			$conv->status            = 'ai_handling';
			$conv->assigned_agent_id = null;
		} else {
			$wpdb->update(
				$table_conv,
				array( 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $conv_id )
			);
		}

		// 2. Autonomous AI handling if conversation is in ai_handling status
		if ( 'ai_handling' === $conv->status ) {
			$ws_data       = self::get_workspace_data();
			$ws_id         = ! empty( $conv->workspace_id ) ? $conv->workspace_id : ( isset( $ws_data['id'] ) ? $ws_data['id'] : '' );
			$threshold     = isset( $ws_data['similarity_threshold'] ) ? (float) $ws_data['similarity_threshold'] : 0.70;
			$system_prompt = isset( $ws_data['system_prompt'] ) ? $ws_data['system_prompt'] : '';
			$known_email   = ! empty( $captured_email ) ? $captured_email : ( ! empty( $conv->visitor_email ) ? $conv->visitor_email : '' );

			if ( Mteja_RAG::is_configured() ) {
				// Retrieve recent history for conversational context
				$history = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT sender_type, content FROM $table_msg WHERE conversation_id = %s AND sender_type IN ('visitor', 'ai', 'agent') ORDER BY created_at ASC LIMIT 12",
						$conv_id
					),
					ARRAY_A
				);

				// Detect if the user's message is a meta-request on existing conversation
				// (e.g. "summarize", "summarize in common language", "simplify", "short version", "what do you mean?")
				$is_meta_summary = (bool) preg_match( '/\b(summarize|summary|simplify|simple terms|plain language|common language|in short|tl;?dr|explain simply|what do you mean)\b/i', $content );

				$chunks = array();
				if ( ! $is_meta_summary ) {
					// Multi-Turn Contextual Query Enrichment:
					// When a visitor asks a follow-up, combine with recent visitor queries so Supabase pgvector retrieves grounded context
					$search_query = $content;
					if ( ! empty( $history ) && count( $history ) > 1 ) {
						$is_followup = (
							str_word_count( $content ) <= 12 ||
							preg_match( '/^(so|what|how|why|which|can|could|tell|explain|recommend)/i', trim( $content ) )
						);
						if ( $is_followup ) {
							$prev_queries = array();
							foreach ( array_reverse( $history ) as $h_msg ) {
								if ( 'visitor' === $h_msg['sender_type'] && trim( $h_msg['content'] ) !== trim( $content ) ) {
									$prev_queries[] = trim( $h_msg['content'] );
									if ( count( $prev_queries ) >= 2 ) {
										break;
									}
								}
							}
							if ( ! empty( $prev_queries ) ) {
								$search_query = implode( ' ', array_reverse( $prev_queries ) ) . ' ' . $content;
							}
						}
					}

					// Query Supabase pgvector match_chunks with primary threshold
					$chunks = Mteja_RAG::search_chunks( $search_query, $ws_id, $threshold, 4 );

					// If no chunks match at primary threshold, try relaxed floor (0.35)
					if ( empty( $chunks ) ) {
						$chunks = Mteja_RAG::search_chunks( $search_query, $ws_id, 0.35, 3 );
					}

					// If combined query had no matches, fallback to current content alone
					if ( empty( $chunks ) && $search_query !== $content ) {
						$chunks = Mteja_RAG::search_chunks( $content, $ws_id, 0.35, 3 );
					}
				}

				$ai_reply = Mteja_RAG::generate_chat_completion( $history, $system_prompt, $chunks );

				if ( ! is_wp_error( $ai_reply ) && ! empty( $ai_reply ) ) {
					$has_escalate_tag = ( strpos( $ai_reply, '[ESCALATE_TO_HUMAN]' ) !== false );
					$clean_reply      = trim( str_replace( '[ESCALATE_TO_HUMAN]', '', $ai_reply ) );

					if ( $has_escalate_tag ) {
						// Visitor explicitly asked for human assistance or inquiry requires operator
						$wpdb->query(
							$wpdb->prepare(
								"UPDATE $table_conv SET status = 'human_waiting', assigned_agent_id = NULL, updated_at = %s WHERE id = %s",
								current_time( 'mysql' ),
								$conv_id
							)
						);
					}

					$wpdb->insert(
						$table_msg,
						array(
							'id'              => wp_generate_uuid4(),
							'conversation_id' => $conv_id,
							'sender_type'     => 'ai',
							'content'         => $clean_reply,
							'created_at'      => current_time( 'mysql' ),
						)
					);
				} else {
					// Temporary network or router delay: do NOT forcefully lock into human_waiting!
					// Keep ticket in ai_handling so visitor can seamlessly continue chatting.
					$retry_msg = "I'm reviewing your inquiry and our store documentation. Could you please share a bit more detail, or let me know what specific step or product you'd like help with?";
					$wpdb->insert(
						$table_msg,
						array(
							'id'              => wp_generate_uuid4(),
							'conversation_id' => $conv_id,
							'sender_type'     => 'ai',
							'content'         => $retry_msg,
							'created_at'      => current_time( 'mysql' ),
						)
					);
				}
			} else {
				// Supabase / FreeLLMAPI credentials not configured yet -> Graceful human routing
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE $table_conv SET status = 'human_waiting', assigned_agent_id = NULL, updated_at = %s WHERE id = %s",
						current_time( 'mysql' ),
						$conv_id
					)
				);
				$fallback_msg = ! empty( $known_email )
					? sprintf( "Thank you for reaching out! A support specialist has been notified. We have your email (%s) and will follow up shortly.", $known_email )
					: "Thank you for reaching out! A support specialist has been notified and will be with you shortly. Please share your email address so we can follow up if needed.";
				$wpdb->insert(
					$table_msg,
					array(
						'id'              => wp_generate_uuid4(),
						'conversation_id' => $conv_id,
						'sender_type'     => 'ai',
						'content'         => $fallback_msg,
						'created_at'      => current_time( 'mysql' ),
					)
				);
			}
		}

		// Return all current messages in the thread
		$all_messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_msg WHERE conversation_id = %s AND sender_type != 'whisper' ORDER BY created_at ASC",
				$conv_id
			),
			ARRAY_A
		);

		return rest_ensure_response( is_array( $all_messages ) ? $all_messages : array() );
	}

	/**
	 * Live Agent Studio RAG Playground Test.
	 */
	public static function test_studio_rag( $request ) {
		$query = sanitize_text_field( $request->get_param( 'query' ) );
		if ( empty( $query ) ) {
			return new WP_Error( 'missing_query', 'Query cannot be empty.', array( 'status' => 400 ) );
		}

		$ws_id     = get_option( 'mteja_default_workspace_id', '' );
		$ws        = self::get_workspace_data();
		
		// Allow live unsaved prompt and threshold overrides from the playground
		$threshold = $request->has_param( 'threshold' ) ? (float) $request->get_param( 'threshold' ) : ( isset( $ws['similarity_threshold'] ) ? (float) $ws['similarity_threshold'] : 0.70 );
		$prompt    = $request->has_param( 'system_prompt' ) ? sanitize_textarea_field( $request->get_param( 'system_prompt' ) ) : ( isset( $ws['system_prompt'] ) ? $ws['system_prompt'] : '' );

		if ( ! Mteja_RAG::is_configured() ) {
			return rest_ensure_response(
				array(
					'reply'            => '[Platform Notice]: Supabase & FreeLLMAPI credentials are not yet configured in Settings. Once configured, live pgvector search and grounded responses will execute here.',
					'threshold'        => $threshold,
					'chunks'           => array(),
					'similarity'       => 0,
					'passed_threshold' => false,
					'handoff'          => false,
				)
			);
		}

		// Search pgvector chunks
		$chunks  = Mteja_RAG::search_chunks( $query, $ws_id, $threshold, 3 );
		$top_sim = ( ! empty( $chunks ) && isset( $chunks[0]['similarity'] ) ) ? (float) $chunks[0]['similarity'] : 0;
		$passed  = ( $top_sim >= $threshold );

		// Support multi-turn history from the sandbox
		$history  = array();
		$raw_hist = $request->get_param( 'history' );
		if ( is_array( $raw_hist ) && ! empty( $raw_hist ) ) {
			foreach ( $raw_hist as $h ) {
				if ( ! empty( $h['content'] ) && ! empty( $h['sender_type'] ) ) {
					$history[] = array(
						'sender_type' => sanitize_text_field( $h['sender_type'] ),
						'content'     => sanitize_text_field( $h['content'] ),
					);
				}
			}
		}
		if ( empty( $history ) ) {
			$history = array( array( 'sender_type' => 'visitor', 'content' => $query ) );
		}

		$response = Mteja_RAG::generate_chat_completion( $history, $prompt, $chunks );

		if ( is_wp_error( $response ) ) {
			return rest_ensure_response(
				array(
					'reply'            => '[AI Engine Error]: ' . $response->get_error_message(),
					'threshold'        => $threshold,
					'chunks'           => $chunks,
					'similarity'       => $top_sim,
					'passed_threshold' => $passed,
					'handoff'          => true,
				)
			);
		}

		$has_escalate_tag = ( strpos( $response, '[ESCALATE_TO_HUMAN]' ) !== false );
		$clean_reply      = trim( str_replace( '[ESCALATE_TO_HUMAN]', '', $response ) );

		// Format chunk metadata for preview
		$formatted_chunks = array();
		foreach ( $chunks as $c ) {
			$formatted_chunks[] = array(
				'id'         => isset( $c['id'] ) ? $c['id'] : '',
				'content'    => isset( $c['content'] ) ? substr( $c['content'], 0, 300 ) . ( strlen( $c['content'] ) > 300 ? '...' : '' ) : '',
				'similarity' => isset( $c['similarity'] ) ? round( (float) $c['similarity'], 3 ) : 0,
			);
		}

		return rest_ensure_response(
			array(
				'reply'            => $clean_reply,
				'threshold'        => $threshold,
				'chunks'           => $formatted_chunks,
				'similarity'       => round( $top_sim, 3 ),
				'passed_threshold' => $passed,
				'handoff'          => $has_escalate_tag,
			)
		);
	}

	/**
	 * Get Auto-Generated FAQs.
	 */
	public static function get_kb_faqs( $request ) {
		$ws_id = get_option( 'mteja_default_workspace_id', 'default' );
		$faqs  = Mteja_RAG::generate_faqs( $ws_id, 6, false );
		return rest_ensure_response( array( 'faqs' => $faqs ) );
	}

	/**
	 * Regenerate Auto-Generated FAQs using LLM.
	 */
	public static function regenerate_kb_faqs( $request ) {
		$ws_id = get_option( 'mteja_default_workspace_id', 'default' );
		$faqs  = Mteja_RAG::generate_faqs( $ws_id, 6, true );
		return rest_ensure_response( array( 'faqs' => $faqs, 'message' => 'FAQs refreshed from knowledge base.' ) );
	}
}

