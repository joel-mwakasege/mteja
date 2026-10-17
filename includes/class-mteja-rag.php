<?php
/**
 * Mteja Supabase & FreeLLMAPI Unified AI Engine RAG Integration
 *
 * Variable names strictly standard with Supabase Server Build APIs:
 * - SUPABASE_URL
 * - SUPABASE_PUBLISHABLE_KEY
 * - SUPABASE_SECRET_KEY
 * - SUPABASE_JWKS_URL
 *
 * Direct Postgres connection settings:
 * - SUPABASE_DB_HOST
 * - SUPABASE_DB_PORT
 * - SUPABASE_DB_NAME
 * - SUPABASE_DB_USER
 * - SUPABASE_DB_PASSWORD
 *
 * @package Mteja
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mteja_RAG {

	/**
	 * Retrieve a configuration variable by exact name.
	 * Checks constant, WordPress option, and environment variables in order.
	 *
	 * @param string $key Exact variable name.
	 * @return string
	 */
	/**
	 * Retrieve a configuration variable by exact name.
	 * Checks constant, WordPress option, environment variables, and fallback aliases in order.
	 *
	 * @param string $key Exact variable name.
	 * @return string
	 */
	public static function get_config( $key ) {
		// 1. Check PHP Constant (e.g. in wp-config.php)
		if ( defined( $key ) ) {
			return constant( $key );
		}

		// 2. Check WordPress Option
		$opt_key = 'mteja_' . strtolower( $key );
		$opt_val = get_option( $opt_key, '' );
		if ( ! empty( $opt_val ) ) {
			return $opt_val;
		}

		// 3. Check getenv / $_ENV
		$env_val = getenv( $key );
		if ( false !== $env_val && '' !== $env_val ) {
			return $env_val;
		}
		if ( isset( $_ENV[ $key ] ) && '' !== $_ENV[ $key ] ) {
			return $_ENV[ $key ];
		}

		// 4. Check Common Aliases for Postgres fields
		$aliases = array(
			'SUPABASE_DB_HOST'     => array( 'host', 'PGHOST', 'POSTGRES_HOST' ),
			'SUPABASE_DB_PORT'     => array( 'port', 'PGPORT', 'POSTGRES_PORT' ),
			'SUPABASE_DB_NAME'     => array( 'database', 'PGDATABASE', 'POSTGRES_DB' ),
			'SUPABASE_DB_USER'     => array( 'user', 'PGUSER', 'POSTGRES_USER' ),
			'SUPABASE_DB_PASSWORD' => array( 'password', 'PGPASSWORD', 'POSTGRES_PASSWORD' ),
		);

		if ( isset( $aliases[ $key ] ) ) {
			foreach ( $aliases[ $key ] as $alias ) {
				if ( defined( $alias ) ) {
					return constant( $alias );
				}
				$a_opt = get_option( 'mteja_' . strtolower( $alias ), '' );
				if ( ! empty( $a_opt ) ) {
					return $a_opt;
				}
				$a_env = getenv( $alias );
				if ( false !== $a_env && '' !== $a_env ) {
					return $a_env;
				}
				if ( isset( $_ENV[ $alias ] ) && '' !== $_ENV[ $alias ] ) {
					return $_ENV[ $alias ];
				}
			}
		}

		return '';
	}

	/**
	 * Get normalized Supabase REST URL.
	 *
	 * @return string
	 */
	public static function get_supabase_url() {
		return rtrim( self::get_config( 'SUPABASE_URL' ), '/' );
	}

	/**
	 * Get Supabase Secret (Service Role) Key.
	 *
	 * @return string
	 */
	public static function get_supabase_secret_key() {
		return self::get_config( 'SUPABASE_SECRET_KEY' );
	}

	/**
	 * Get Supabase Publishable (Anon) Key.
	 *
	 * @return string
	 */
	public static function get_supabase_publishable_key() {
		return self::get_config( 'SUPABASE_PUBLISHABLE_KEY' );
	}

	/**
	 * Get Supabase JWKS URL.
	 *
	 * @return string
	 */
	public static function get_supabase_jwks_url() {
		$jwks = self::get_config( 'SUPABASE_JWKS_URL' );
		if ( empty( $jwks ) ) {
			$base = self::get_supabase_url();
			if ( ! empty( $base ) ) {
				return $base . '/auth/v1/.well-known/jwks.json';
			}
		}
		return $jwks;
	}

	/**
	 * Get Direct PostgreSQL Database Host.
	 *
	 * @return string
	 */
	public static function get_supabase_db_host() {
		return self::get_config( 'SUPABASE_DB_HOST' );
	}

	/**
	 * Get Direct PostgreSQL Database Port.
	 *
	 * @return string
	 */
	public static function get_supabase_db_port() {
		$port = self::get_config( 'SUPABASE_DB_PORT' );
		return ! empty( $port ) ? $port : '5432';
	}

	/**
	 * Get Direct PostgreSQL Database Name.
	 *
	 * @return string
	 */
	public static function get_supabase_db_name() {
		$db = self::get_config( 'SUPABASE_DB_NAME' );
		return ! empty( $db ) ? $db : 'postgres';
	}

	/**
	 * Get Direct PostgreSQL Database User.
	 *
	 * @return string
	 */
	public static function get_supabase_db_user() {
		$user = self::get_config( 'SUPABASE_DB_USER' );
		return ! empty( $user ) ? $user : '';
	}

	/**
	 * Get Direct PostgreSQL Database Password.
	 *
	 * @return string
	 */
	public static function get_supabase_db_password() {
		return self::get_config( 'SUPABASE_DB_PASSWORD' );
	}

	/**
	 * Generate full PostgreSQL connection URI string.
	 * Format: postgresql://[user]:[password]@[host]:[port]/[database]
	 *
	 * @param bool $mask_password Whether to mask the password for display.
	 * @return string
	 */
	public static function get_postgres_connection_string( $mask_password = true ) {
		$host = self::get_supabase_db_host();
		$port = self::get_supabase_db_port();
		$user = self::get_supabase_db_user();
		$db   = self::get_supabase_db_name();
		$pass = self::get_supabase_db_password();

		if ( empty( $host ) || empty( $user ) ) {
			return '';
		}

		$pwd_display = $mask_password ? ( empty( $pass ) ? '[YOUR-PASSWORD]' : '••••••••' ) : rawurlencode( $pass );
		return sprintf( 'postgresql://%s:%s@%s:%s/%s', rawurlencode( $user ), $pwd_display, $host, $port, rawurlencode( $db ) );
	}

	/**
	 * Get FreeLLMAPI Unified API Key (or fallback to OpenAI key).
	 *
	 * @return string
	 */
	public static function get_freellm_api_key() {
		$key = self::get_config( 'FREELLM_API_KEY' );
		if ( ! empty( $key ) ) {
			return $key;
		}
		return self::get_config( 'OPENAI_API_KEY' );
	}

	/**
	 * Backward compatibility alias for OpenAI key.
	 *
	 * @return string
	 */
	public static function get_openai_api_key() {
		return self::get_freellm_api_key();
	}

	/**
	 * Get FreeLLMAPI Base URL.
	 * Defaults to http://localhost:3001/v1 or custom host from freellmapi.co dashboard.
	 *
	 * @return string
	 */
	public static function get_freellm_base_url() {
		$base = self::get_config( 'FREELLM_BASE_URL' );
		if ( empty( $base ) ) {
			$freellm_key = self::get_config( 'FREELLM_API_KEY' );
			if ( empty( $freellm_key ) && ! empty( self::get_config( 'OPENAI_API_KEY' ) ) ) {
				return 'https://api.openai.com/v1';
			}
			$base = 'http://localhost:3001/v1';
		}
		$base = rtrim( $base, '/' );

		// When running inside a Docker container, localhost/127.0.0.1 refers to the container itself.
		// Auto-resolve to host gateway or bridge so port 3001 reaches the host FreeLLMAPI instance.
		if ( preg_match( '#^(https?://)(localhost|127\.0\.0\.1)(:3001.*)$#i', $base, $matches ) ) {
			if ( file_exists( '/.dockerenv' ) || ! empty( getenv( 'WORDPRESS_DB_HOST' ) ) ) {
				$candidates = array(
					'10.203.129.224',
					'172.18.0.1',
					'172.17.0.1',
					'172.19.0.1',
					'host.docker.internal',
				);
				foreach ( $candidates as $candidate ) {
					$fp = @fsockopen( $candidate, 3001, $errno, $errstr, 0.15 );
					if ( $fp ) {
						fclose( $fp );
						return $matches[1] . $candidate . $matches[3];
					}
				}
			}
		}

		return $base;
	}

	/**
	 * Get FreeLLMAPI Chat Model.
	 * Default: "auto" (or auto:smart, auto:fast, or specific model name).
	 *
	 * @return string
	 */
	public static function get_freellm_chat_model() {
		$model = self::get_config( 'FREELLM_CHAT_MODEL' );
		return ! empty( $model ) ? $model : 'auto';
	}

	/**
	 * Get FreeLLMAPI Embedding Model.
	 * Default: "text-embedding-3-small" (or auto).
	 *
	 * @return string
	 */
	public static function get_freellm_embedding_model() {
		$model = self::get_config( 'FREELLM_EMBEDDING_MODEL' );
		return ! empty( $model ) ? $model : 'text-embedding-3-small';
	}

	/**
	 * Comprehensive connection test covering:
	 * 1. Supabase Server Build REST APIs
	 * 2. Supabase pgvector document_chunks table and match_chunks RPC
	 * 3. Direct PostgreSQL TCP Reachability
	 * 4. FreeLLMAPI Unified Router and Embeddings
	 *
	 * @return array
	 */
	public static function test_connection() {
		$results = array(
			'success'     => true,
			'messages'    => array(),
			'diagnostics' => array(
				'rest'      => false,
				'pgvector'  => false,
				'db_tcp'    => false,
				'freellm'   => false,
				'embedding' => false,
			),
		);

		$url        = self::get_supabase_url();
		$secret_key = self::get_supabase_secret_key();
		$pub_key    = self::get_supabase_publishable_key();
		$api_key    = ! empty( $secret_key ) ? $secret_key : $pub_key;
		$db_host    = self::get_supabase_db_host();
		$db_port    = (int) self::get_supabase_db_port();

		$llm_key    = self::get_freellm_api_key();
		$base_url   = self::get_freellm_base_url();
		$chat_model = self::get_freellm_chat_model();
		$emb_model  = self::get_freellm_embedding_model();

		// 1. Supabase REST API check
		if ( empty( $url ) ) {
			$results['success'] = false;
			$results['messages'][] = '❌ SUPABASE_URL is not set.';
		} elseif ( empty( $api_key ) ) {
			$results['success'] = false;
			$results['messages'][] = '❌ SUPABASE_SECRET_KEY (or SUPABASE_PUBLISHABLE_KEY) is not set.';
		} else {
			$resp = wp_remote_get(
				$url . '/rest/v1/',
				array(
					'headers' => array(
						'apikey'        => $api_key,
						'Authorization' => 'Bearer ' . $api_key,
					),
					'timeout' => 20,
				)
			);

			if ( is_wp_error( $resp ) ) {
				$results['success'] = false;
				$results['messages'][] = '❌ Supabase REST API connection error: ' . $resp->get_error_message();
			} else {
				$code = wp_remote_retrieve_response_code( $resp );
				if ( $code >= 200 && $code < 300 ) {
					$results['diagnostics']['rest'] = true;
					$results['messages'][] = '✅ Supabase Server Build REST API connection successful (' . esc_html( $url ) . ').';

					// Check document_chunks table (with auto-retry on cold-start timeout)
					$chunk_check = wp_remote_get(
						$url . '/rest/v1/document_chunks?select=id&limit=1',
						array(
							'headers' => array(
								'apikey'        => $api_key,
								'Authorization' => 'Bearer ' . $api_key,
							),
							'timeout' => 20,
						)
					);

					if ( is_wp_error( $chunk_check ) && strpos( $chunk_check->get_error_message(), 'timed out' ) !== false ) {
						// Retry once in case of cold-start latency
						$chunk_check = wp_remote_get(
							$url . '/rest/v1/document_chunks?select=id&limit=1',
							array(
								'headers' => array(
									'apikey'        => $api_key,
									'Authorization' => 'Bearer ' . $api_key,
								),
								'timeout' => 25,
							)
						);
					}

					if ( ! is_wp_error( $chunk_check ) ) {
						$chunk_code = wp_remote_retrieve_response_code( $chunk_check );
						$chunk_body = wp_remote_retrieve_body( $chunk_check );
						if ( 200 === $chunk_code ) {
							$results['diagnostics']['pgvector'] = true;
							$results['messages'][] = '✅ Supabase pgvector table "document_chunks" is ready.';
						} elseif ( 403 === $chunk_code || strpos( $chunk_body, '42501' ) !== false || strpos( $chunk_body, 'permission denied' ) !== false ) {
							$results['messages'][] = '⚠️ Table "document_chunks" exists in Supabase, but role "service_role" was denied access (Error 42501: permission denied). Execute the GRANT and RLS policies in the SQL script below in your Supabase SQL editor.';
						} else {
							$results['messages'][] = '⚠️ "document_chunks" table not detected yet (HTTP ' . $chunk_code . '). Run the SQL Migration script below in your Supabase SQL editor.';
						}
					} else {
						$results['messages'][] = '⚠️ Failed to query "document_chunks" table: ' . $chunk_check->get_error_message();
					}
				} else {
					$results['success'] = false;
					$results['messages'][] = '❌ Supabase REST returned HTTP ' . $code . ' (' . wp_remote_retrieve_body( $resp ) . '). Please check SUPABASE_SECRET_KEY.';
				}
			}
		}

		// 2. Direct PostgreSQL Network Reachability Check
		if ( ! empty( $db_host ) ) {
			$errno  = 0;
			$errstr = '';
			$fp = @fsockopen( $db_host, $db_port, $errno, $errstr, 4 );
			if ( $fp ) {
				fclose( $fp );
				$results['diagnostics']['db_tcp'] = true;
				$results['messages'][] = sprintf( '✅ Direct PostgreSQL database port (%s:%d) is reachable via TCP.', esc_html( $db_host ), $db_port );
			} else {
				$results['messages'][] = sprintf( '⚠️ Direct PostgreSQL host (%s:%d) TCP connection attempt returned: %s', esc_html( $db_host ), $db_port, esc_html( $errstr ) );
			}
		}

		// 3. FreeLLMAPI Unified AI Router Check
		if ( ! empty( $llm_key ) ) {
			// First test GET /models
			$models_resp = wp_remote_get(
				$base_url . '/models?available=true',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $llm_key,
					),
					'timeout' => 15,
				)
			);

			$router_verified = false;
			if ( ! is_wp_error( $models_resp ) && wp_remote_retrieve_response_code( $models_resp ) < 400 ) {
				$router_verified = true;
			} else {
				// Fallback ping via chat completions test
				$chat_resp = wp_remote_post(
					$base_url . '/chat/completions',
					array(
						'headers' => array(
							'Authorization' => 'Bearer ' . $llm_key,
							'Content-Type'  => 'application/json',
						),
						'body'    => wp_json_encode(
							array(
								'model'       => $chat_model,
								'messages'    => array( array( 'role' => 'user', 'content' => 'ping' ) ),
								'max_tokens'  => 5,
							)
						),
						'timeout' => 15,
					)
				);
				if ( ! is_wp_error( $chat_resp ) && wp_remote_retrieve_response_code( $chat_resp ) < 400 ) {
					$router_verified = true;
				}
			}

			if ( $router_verified ) {
				$results['diagnostics']['freellm'] = true;
				$results['messages'][] = sprintf( '✅ FreeLLMAPI Unified Router verified (%s, chat model: %s).', esc_html( $base_url ), esc_html( $chat_model ) );

				// Test vector embeddings endpoint
				$emb_resp = wp_remote_post(
					$base_url . '/embeddings',
					array(
						'headers' => array(
							'Authorization' => 'Bearer ' . $llm_key,
							'Content-Type'  => 'application/json',
						),
						'body'    => wp_json_encode(
							array(
								'model'      => $emb_model,
								'input'      => 'ping',
								'dimensions' => 1536,
							)
						),
						'timeout' => 15,
					)
				);

				if ( ! is_wp_error( $emb_resp ) && wp_remote_retrieve_response_code( $emb_resp ) === 200 ) {
					$results['diagnostics']['embedding'] = true;
					$results['messages'][] = sprintf( '✅ FreeLLMAPI vector embeddings verified (model: %s).', esc_html( $emb_model ) );
				} else {
					$emb_code = ! is_wp_error( $emb_resp ) ? wp_remote_retrieve_response_code( $emb_resp ) : $emb_resp->get_error_message();
					$results['messages'][] = sprintf( 'ℹ️ FreeLLMAPI embeddings test returned %s for model "%s".', esc_html( (string) $emb_code ), esc_html( $emb_model ) );
				}
			} else {
				$results['messages'][] = sprintf( '⚠️ FreeLLMAPI router unreachable at %s. Please verify your FREELLM_BASE_URL and FREELLM_API_KEY.', esc_html( $base_url ) );
			}
		} else {
			$results['messages'][] = 'ℹ️ FREELLM_API_KEY is not configured yet (needed for autonomous chat and vector embeddings).';
		}

		// Combined message
		$results['message'] = implode( "\n", $results['messages'] );
		return $results;
	}

	/**
	 * Returns the complete PostgreSQL / Supabase SQL schema migration script.
	 *
	 * @return string
	 */
	public static function get_sql_migration_script() {
		return <<<SQL
-- =========================================================================
-- Mteja AI Support Platform: Supabase pgvector Schema Migration
-- Run this in your Supabase Dashboard: SQL Editor -> New Query -> Run
-- =========================================================================

-- 1. Enable pgvector extension
CREATE EXTENSION IF NOT EXISTS vector;

-- 2. Create document_chunks table with 1536-dim embeddings for FreeLLMAPI / OpenAI text-embedding-3-small
CREATE TABLE IF NOT EXISTS public.document_chunks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID NOT NULL,
    document_id VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    embedding VECTOR(1536),
    metadata JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- 3. Create HNSW index for sub-50ms cosine similarity vector search
CREATE INDEX IF NOT EXISTS document_chunks_embedding_hnsw_idx
    ON public.document_chunks
    USING hnsw (embedding vector_cosine_ops)
    WITH (m = 16, ef_construction = 64);

-- 4. Create filtering index by workspace and document
CREATE INDEX IF NOT EXISTS document_chunks_workspace_doc_idx
    ON public.document_chunks (workspace_id, document_id);

-- 5. Stored Procedure for Cosine Match with Threshold & Workspace Scoping
CREATE OR REPLACE FUNCTION public.match_chunks(
    query_embedding VECTOR(1536),
    match_threshold FLOAT DEFAULT 0.70,
    match_count INT DEFAULT 4,
    filter_workspace_id UUID DEFAULT NULL
)
RETURNS TABLE (
    id UUID,
    document_id VARCHAR(255),
    content TEXT,
    similarity FLOAT,
    metadata JSONB
)
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
    RETURN QUERY
    SELECT
        dc.id,
        dc.document_id,
        dc.content,
        (1 - (dc.embedding <=> query_embedding))::FLOAT AS similarity,
        dc.metadata
    FROM public.document_chunks dc
    WHERE
        (filter_workspace_id IS NULL OR dc.workspace_id = filter_workspace_id)
        AND dc.embedding IS NOT NULL
        AND (1 - (dc.embedding <=> query_embedding)) >= match_threshold
    ORDER BY dc.embedding <=> query_embedding
    LIMIT match_count;
END;
$$;

-- 6. Essential Grants for Supabase Service Role and API Roles
-- (Resolves PostgREST 42501 "permission denied for table document_chunks")
GRANT USAGE ON SCHEMA public TO postgres, anon, authenticated, service_role;
GRANT ALL ON TABLE public.document_chunks TO postgres, service_role;
GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE public.document_chunks TO anon, authenticated;
GRANT EXECUTE ON FUNCTION public.match_chunks TO postgres, anon, authenticated, service_role;

-- 7. Row Level Security (RLS) Configuration
ALTER TABLE public.document_chunks ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Enable all access for service_role" ON public.document_chunks;
CREATE POLICY "Enable all access for service_role" ON public.document_chunks
    FOR ALL TO service_role USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Enable read access for anon and authenticated" ON public.document_chunks;
CREATE POLICY "Enable read access for anon and authenticated" ON public.document_chunks
    FOR SELECT TO anon, authenticated USING (true);
SQL;
	}

	/**
	 * Generate 1536-dimensional embedding using FreeLLMAPI / OpenAI-compatible endpoint.
	 *
	 * @param string|array $input Single string or array of strings.
	 * @return array|WP_Error
	 */
	public static function generate_embedding( $input ) {
		$api_key  = self::get_freellm_api_key();
		$base_url = self::get_freellm_base_url();
		$model    = self::get_freellm_embedding_model();

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_key', 'FREELLM_API_KEY is not configured.' );
		}

		$resp = wp_remote_post(
			$base_url . '/embeddings',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => $model,
						'input'      => $input,
						'dimensions' => 1536,
					)
				),
				'timeout' => 45,
			)
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = wp_remote_retrieve_response_code( $resp );
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( $code !== 200 || empty( $body['data'] ) ) {
			$msg = ! empty( $body['error']['message'] ) ? $body['error']['message'] : ( 'FreeLLMAPI embeddings failed with HTTP ' . $code );
			return new WP_Error( 'embedding_failed', $msg );
		}

		if ( is_array( $input ) ) {
			$embeddings = array();
			foreach ( $body['data'] as $item ) {
				$vec = $item['embedding'];
				if ( is_array( $vec ) && count( $vec ) > 1536 ) {
					$vec = array_slice( $vec, 0, 1536 );
				}
				$embeddings[] = $vec;
			}
			return $embeddings;
		}

		$single_vec = $body['data'][0]['embedding'];
		if ( is_array( $single_vec ) && count( $single_vec ) > 1536 ) {
			$single_vec = array_slice( $single_vec, 0, 1536 );
		}
		return $single_vec;
	}

	/**
	 * Check if all required Supabase and FreeLLMAPI credentials are configured.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$url        = self::get_supabase_url();
		$secret_key = self::get_supabase_secret_key();
		$pub_key    = self::get_supabase_publishable_key();
		$llm_key    = self::get_freellm_api_key();

		return ( ! empty( $url ) && ( ! empty( $secret_key ) || ! empty( $pub_key ) ) && ! empty( $llm_key ) );
	}

	/**
	 * Perform semantic search via Supabase pgvector match_chunks RPC.
	 *
	 * @param string $query_text Visitor query.
	 * @param string $workspace_id Workspace UUID.
	 * @param float  $threshold Similarity floor (e.g. 0.70).
	 * @param int    $limit Max chunks to return.
	 * @return array
	 */
	public static function search_chunks( $query_text, $workspace_id, $threshold = 0.70, $limit = 4 ) {
		$url        = self::get_supabase_url();
		$secret_key = self::get_supabase_secret_key();

		if ( empty( $url ) || empty( $secret_key ) ) {
			return array();
		}

		// 1. Generate query embedding
		$embedding = self::generate_embedding( $query_text );
		if ( is_wp_error( $embedding ) ) {
			return array();
		}

		// Sanitize UUID for Postgres: null if empty or invalid
		$valid_ws_id = ( ! empty( $workspace_id ) && wp_is_uuid( $workspace_id ) ) ? $workspace_id : null;

		// 2. Call Supabase RPC match_chunks
		$rpc_url = $url . '/rest/v1/rpc/match_chunks';
		$payload = array(
			'query_embedding'     => $embedding,
			'match_threshold'     => (float) $threshold,
			'match_count'         => (int) $limit,
			'filter_workspace_id' => $valid_ws_id,
		);

		$resp = wp_remote_post(
			$rpc_url,
			array(
				'headers' => array(
					'apikey'        => $secret_key,
					'Authorization' => 'Bearer ' . $secret_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) !== 200 ) {
			return array();
		}

		$results = json_decode( wp_remote_retrieve_body( $resp ), true );
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Insert document chunks with embeddings into Supabase.
	 *
	 * @param string $workspace_id Workspace UUID.
	 * @param string $document_id Document identifier.
	 * @param array  $chunks Array of text chunks.
	 * @return bool|WP_Error
	 */
	public static function insert_chunks( $workspace_id, $document_id, $chunks ) {
		$url        = self::get_supabase_url();
		$secret_key = self::get_supabase_secret_key();

		if ( empty( $url ) || empty( $secret_key ) ) {
			return new WP_Error( 'not_configured', 'Supabase credentials missing.' );
		}

		if ( empty( $chunks ) ) {
			return true;
		}

		// Generate embeddings in batches of 50
		$batch_size = 50;
		$all_rows   = array();

		for ( $i = 0; $i < count( $chunks ); $i += $batch_size ) {
			$batch_chunks = array_slice( $chunks, $i, $batch_size );
			$embeddings   = self::generate_embedding( $batch_chunks );

			if ( is_wp_error( $embeddings ) ) {
				return $embeddings;
			}

			foreach ( $batch_chunks as $idx => $content ) {
				$all_rows[] = array(
					'workspace_id' => $workspace_id,
					'document_id'  => (string) $document_id,
					'content'      => $content,
					'embedding'    => $embeddings[ $idx ],
					'metadata'     => array(
						'chunk_index' => $i + $idx,
						'total_chunks' => count( $chunks ),
					),
				);
			}
		}

		// Post rows to Supabase document_chunks
		$post_url = $url . '/rest/v1/document_chunks';
		$resp     = wp_remote_post(
			$post_url,
			array(
				'headers' => array(
					'apikey'        => $secret_key,
					'Authorization' => 'Bearer ' . $secret_key,
					'Content-Type'  => 'application/json',
					'Prefer'        => 'resolution=merge-duplicates',
				),
				'body'    => wp_json_encode( $all_rows ),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = wp_remote_retrieve_response_code( $resp );
		return ( $code >= 200 && $code < 300 );
	}

	/**
	 * Delete document chunks from Supabase.
	 *
	 * @param string $document_id Document ID.
	 * @return bool
	 */
	public static function delete_document_chunks( $document_id ) {
		$url        = self::get_supabase_url();
		$secret_key = self::get_supabase_secret_key();

		if ( empty( $url ) || empty( $secret_key ) ) {
			return false;
		}

		$del_url = $url . '/rest/v1/document_chunks?document_id=eq.' . rawurlencode( $document_id );
		$resp    = wp_remote_request(
			$del_url,
			array(
				'method'  => 'DELETE',
				'headers' => array(
					'apikey'        => $secret_key,
					'Authorization' => 'Bearer ' . $secret_key,
				),
				'timeout' => 10,
			)
		);

		return ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) < 300 );
	}

	/**
	 * Extract plain text from uploaded file (TXT, MD, HTML, CSV, JSON, PDF).
	 *
	 * @param string $file_path Local path to the uploaded file.
	 * @param string $original_filename Original file name with extension.
	 * @return string Extracted plain text.
	 */
	public static function extract_text_from_file( $file_path, $original_filename = '' ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return '';
		}

		$ext = strtolower( pathinfo( $original_filename ? $original_filename : $file_path, PATHINFO_EXTENSION ) );
		$raw = file_get_contents( $file_path );

		if ( 'pdf' === $ext ) {
			return self::extract_text_from_pdf( $file_path, $raw );
		}

		if ( in_array( $ext, array( 'html', 'htm' ), true ) ) {
			return wp_strip_all_tags( $raw );
		}

		if ( 'json' === $ext ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return wp_json_encode( $decoded, JSON_PRETTY_PRINT );
			}
			return $raw;
		}

		// Plain text / Markdown / CSV
		return $raw;
	}

	/**
	 * Extract text from PDF content.
	 *
	 * @param string $file_path Path to file.
	 * @param string $raw Raw file contents.
	 * @return string
	 */
	public static function extract_text_from_pdf( $file_path, $raw ) {
		// Try command-line pdftotext if available
		if ( function_exists( 'exec' ) ) {
			$out = array();
			$ret = 1;
			@exec( 'pdftotext ' . escapeshellarg( $file_path ) . ' - 2>/dev/null', $out, $ret );
			if ( 0 === $ret && ! empty( $out ) ) {
				$txt = implode( "\n", $out );
				if ( strlen( trim( $txt ) ) > 10 ) {
					return $txt;
				}
			}
		}

		// Fallback: Native PHP PDF stream extractor
		$text = '';
		if ( preg_match_all( '#stream[\r\n]+(.*?)[\r\n]+endstream#s', $raw, $matches ) ) {
			foreach ( $matches[1] as $stream ) {
				$uncompressed = @gzuncompress( $stream );
				if ( false === $uncompressed ) {
					$uncompressed = $stream;
				}

				if ( preg_match_all( '#\((.*?)\)\s*Tj#s', $uncompressed, $tj_matches ) ) {
					$text .= implode( ' ', $tj_matches[1] ) . "\n";
				} elseif ( preg_match_all( '#\[(.*?)\]\s*TJ#s', $uncompressed, $tj_matches ) ) {
					foreach ( $tj_matches[1] as $tj_content ) {
						if ( preg_match_all( '#\((.*?)\)#s', $tj_content, $sub ) ) {
							$text .= implode( '', $sub[1] ) . ' ';
						}
					}
					$text .= "\n";
				}
			}
		}

		if ( empty( trim( $text ) ) ) {
			preg_match_all( '#[a-zA-Z0-9.,;:!?\'"()\-\s]{4,}#', $raw, $words );
			if ( ! empty( $words[0] ) ) {
				$text = implode( ' ', $words[0] );
			}
		}

		return trim( $text );
	}

	/**
	 * Break text into semantic chunks (~500 tokens / 1800 chars) with 50-token overlap.
	 *
	 * @param string $text Raw text.
	 * @param int    $target_size Approx character target.
	 * @param int    $overlap Character overlap.
	 * @return array Array of string chunks.
	 */
	public static function chunk_text( $text, $target_size = 1800, $overlap = 180 ) {
		$text = trim( preg_replace( '/\r\n|\r/', "\n", $text ) );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );

		if ( mb_strlen( $text ) <= $target_size ) {
			return ! empty( $text ) ? array( $text ) : array();
		}

		$chunks = array();
		$start  = 0;
		$length = mb_strlen( $text );

		while ( $start < $length ) {
			$end = min( $start + $target_size, $length );

			if ( $end < $length ) {
				$slice = mb_substr( $text, $start, $target_size );
				$break_pos = false;

				$paragraph_pos = mb_strrpos( $slice, "\n\n" );
				if ( false !== $paragraph_pos && $paragraph_pos > ( $target_size * 0.5 ) ) {
					$break_pos = $paragraph_pos + 2;
				} else {
					$sentence_pos = mb_strrpos( $slice, '. ' );
					if ( false !== $sentence_pos && $sentence_pos > ( $target_size * 0.5 ) ) {
						$break_pos = $sentence_pos + 2;
					} else {
						$newline_pos = mb_strrpos( $slice, "\n" );
						if ( false !== $newline_pos && $newline_pos > ( $target_size * 0.5 ) ) {
							$break_pos = $newline_pos + 1;
						} else {
							$space_pos = mb_strrpos( $slice, ' ' );
							if ( false !== $space_pos && $space_pos > ( $target_size * 0.5 ) ) {
								$break_pos = $space_pos + 1;
							}
						}
					}
				}

				if ( false !== $break_pos ) {
					$end = $start + $break_pos;
				}
			}

			$chunk = trim( mb_substr( $text, $start, $end - $start ) );
			if ( ! empty( $chunk ) ) {
				$chunks[] = $chunk;
			}

			if ( $end >= $length ) {
				break;
			}

			$start = max( $end - $overlap, $start + 1 );
		}

		return $chunks;
	}

	/**
	 * Build comprehensive system prompt with two-tier autonomous guardrails.
	 *
	 * @param string $custom_prompt Custom prompt or persona from Agent Studio.
	 * @return string
	 */
	public static function build_system_prompt( $custom_prompt = '' ) {
		$site_name = get_bloginfo( 'name' );
		if ( empty( $site_name ) ) {
			$site_name = 'Our Store';
		}

		$base = ! empty( trim( $custom_prompt ) )
			? trim( $custom_prompt )
			: sprintf( 'You are Mteja, an autonomous, courteous, and highly capable customer support specialist for %s.', $site_name );

		$guardrails = <<<GUARDRAILS

CORE OPERATIONAL AGENTIC DIRECTIVES:
1. BREVITY & CHAT CONCISENESS (CRITICAL):
   - You are chatting inside a small website chat widget. Keep ALL responses short, summarized, and easy to skim.
   - MAXIMUM LENGTH: 2 to 4 sentences total, OR a clean bullet list of 3-4 items (each under 8 words).
   - NEVER write walls of text, bloated introductory filler, long paragraphs, or multi-sentence bullets.
   - Example of a great reply to "what can you help me do":
     "I can assist you with:
     • Orders, tracking & shipping
     • Returns and 30-day refunds
     • Account and store settings
     • Technical troubleshooting

     What would you like assistance with today?"

2. DIGEST & SYNTHESIZE (NEVER COPY-PASTE OR PARROT):
   - The knowledge base contains raw reference facts for your mind, NOT text to copy-paste or read aloud!
   - NEVER copy-paste chunks, raw headings, or code snippets (like <?php wp_footer(); ?>) unless explicitly requested.
   - You must digest the facts, internalize them, and explain them in your own natural, friendly, human voice.
   - Never say "According to reference 1" or "Based on our documentation chunk". Speak like an empathetic, experienced product specialist.

3. DIAGNOSE BEFORE PRESCRIBING:
   - When a visitor brings up a general or broad topic (e.g. "let's talk about troubleshooting", "something isn't working", "I have an issue", "tell me about settings"):
   - NEVER assume one specific problem and dump a wall of technical instructions!
   - Instead, act as a professional diagnostic specialist:
     1. Acknowledge warmly.
     2. Outline the 2-3 most common issues (e.g. 1. Chat widget not appearing on site, 2. Messages failing to send / connection errors, 3. Setup / database configuration).
     3. Ask the user: "Which of these are you experiencing, or what specific symptom are you seeing?"
   - Once the user tells you their specific issue, THEN guide them through the solution step-by-step.

4. CONVERSATIONAL CONTINUITY & PLAIN-LANGUAGE SUMMARIZATION:
   - When the user asks to "summarize", "summarize in common language", "simplify", "explain in plain words", or asks "what do you mean?":
   - Summarize the EXACT solution or concept you just discussed in the previous turn into 2-3 friendly, jargon-free bullet points.
   - DO NOT introduce new documentation topics or switch subjects.
   - Use plain, everyday terms and relatable analogies. Avoid technical jargon like CORS, REST API, or firewalls unless explained simply.

5. CONTINUOUS AUTONOMOUS ASSISTANCE (ZERO PREMATURE ESCALATION):
   - You are a fully capable AI specialist. Solve customer problems thoroughly and autonomously.
   - NEVER escalate to human support or ask for an email address unless the visitor EXPLICITLY asks to speak with a human or real person.
   - When a visitor greets you ("hi", "hello") or says "I need help", respond warmly and ask what they need help with.

6. STRICT TOPIC GUARDRAILS & DEFLECTION:
   - Your scope is strictly customer support for {$site_name} (products, orders, shipping, returns, store policies, account questions, and troubleshooting).
   - If a visitor asks off-topic questions (e.g., about food, waffles, recipes, weather, sports, politics, homework, trivia, or entertainment), politely decline and steer them back to our store and support.

7. HUMAN ESCALATION PROTOCOL:
   - ONLY if the visitor explicitly asks to speak to a human, agent, operator, or representative (e.g., "human please", "talk to a real person", "transfer me to support", "speak with an agent"), acknowledge their request courteously, reassure them that a specialist will assist them, and append the exact token [ESCALATE_TO_HUMAN] at the end of your response.
GUARDRAILS;

		return $base . "\n" . $guardrails;
	}

	/**
	 * Generate an autonomous AI response using FreeLLMAPI / OpenAI-compatible Chat Completions API with grounded context.
	 *
	 * @param array  $conversation_history Array of messages with sender_type and content.
	 * @param string $system_prompt Persona and instruction prompt.
	 * @param array  $context_chunks Array of matched knowledge chunks.
	 * @return string|WP_Error
	 */
	public static function generate_chat_completion( $conversation_history, $system_prompt = '', $context_chunks = array() ) {
		$api_key  = self::get_freellm_api_key();
		$base_url = self::get_freellm_base_url();
		$model    = self::get_freellm_chat_model();

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_key', 'FREELLM_API_KEY is not configured.' );
		}

		$context_text = '';
		if ( ! empty( $context_chunks ) ) {
			$chunk_texts = array();
			foreach ( $context_chunks as $idx => $chunk ) {
				$content = is_array( $chunk ) ? ( isset( $chunk['content'] ) ? $chunk['content'] : '' ) : (string) $chunk;
				if ( ! empty( $content ) ) {
					$chunk_texts[] = sprintf( "[Background Fact %d]:\n%s", $idx + 1, trim( $content ) );
				}
			}
			if ( ! empty( $chunk_texts ) ) {
				$context_text = "\n\nBACKGROUND KNOWLEDGE BASE FACTS (FOR INTERNAL REFERENCE ONLY - DO NOT COPY-PASTE DIRECTLY):\n" . implode( "\n\n", $chunk_texts );
			}
		}

		$system_content = self::build_system_prompt( $system_prompt ) . $context_text;

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $system_content,
			),
		);

		// Include up to last 10 conversation messages for robust multi-turn reasoning
		$history = array_slice( $conversation_history, -10 );
		foreach ( $history as $msg ) {
			$sender  = isset( $msg['sender_type'] ) ? $msg['sender_type'] : '';
			$content = isset( $msg['content'] ) ? trim( $msg['content'] ) : '';

			if ( empty( $content ) ) {
				continue;
			}

			if ( in_array( $sender, array( 'ai', 'agent' ), true ) ) {
				$messages[] = array(
					'role'    => 'assistant',
					'content' => $content,
				);
			} elseif ( 'visitor' === $sender ) {
				$messages[] = array(
					'role'    => 'user',
					'content' => $content,
				);
			}
		}

		// Candidate models for seamless failover if upstream router encounters rate limits or errors
		$candidates = array( $model );
		if ( 'auto' !== $model && ! in_array( 'auto', $candidates, true ) ) {
			$candidates[] = 'auto';
		}
		if ( ! in_array( 'gemini-flash', $candidates, true ) ) {
			$candidates[] = 'gemini-flash';
		}

		$last_error = null;
		foreach ( $candidates as $candidate_model ) {
			$resp = wp_remote_post(
				$base_url . '/chat/completions',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'model'       => $candidate_model,
							'messages'    => $messages,
							'temperature' => 0.3,
							'max_tokens'  => 3500,
						)
					),
					'timeout' => 60,
				)
			);

			if ( is_wp_error( $resp ) ) {
				$last_error = $resp;
				continue;
			}

			$code = wp_remote_retrieve_response_code( $resp );
			$body = json_decode( wp_remote_retrieve_body( $resp ), true );

			if ( 200 === $code && ! empty( $body['choices'][0]['message']['content'] ) ) {
				return trim( $body['choices'][0]['message']['content'] );
			}

			$msg        = ! empty( $body['error']['message'] ) ? $body['error']['message'] : ( 'FreeLLMAPI chat completion failed with HTTP ' . $code );
			$last_error = new WP_Error( 'llm_chat_failed', $msg );
		}

		return $last_error;
	}
}
