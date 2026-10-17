<?php
/**
 * Mteja Gutenberg Block Pattern Registrar
 *
 * @package Mteja
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mteja_Patterns {

	/**
	 * Initialize pattern registration and token assets.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_patterns' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_tokens' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_tokens' ) );
	}

	/**
	 * Register Gutenberg block patterns.
	 */
	public static function register_patterns() {
		if ( ! function_exists( 'register_block_pattern_category' ) || ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		// Register Mteja category
		register_block_pattern_category(
			'mteja',
			array(
				'label' => __( 'Mteja Support', 'mteja' ),
			)
		);

		// 1. Landing Page Pattern
		$landing_json_file = dirname( dirname( __FILE__ ) ) . '/patterns/landing-page.json';
		if ( file_exists( $landing_json_file ) ) {
			$landing_data = json_decode( file_get_contents( $landing_json_file ), true );
			if ( $landing_data && ! empty( $landing_data['content'] ) ) {
				register_block_pattern(
					'mteja/landing-page',
					array(
						'title'       => __( 'Mteja — Autonomous Support Landing Page', 'mteja' ),
						'description' => __( 'High-fidelity autonomous AI agent landing page with interactive OS showcase frame and RAG telemetry.', 'mteja' ),
						'categories'  => array( 'mteja', 'featured', 'pages' ),
						'content'     => $landing_data['content'],
					)
				);
			}
		}

		// 2. Login Page Pattern
		$login_json_file = dirname( dirname( __FILE__ ) ) . '/patterns/login-page.json';
		if ( file_exists( $login_json_file ) ) {
			$login_data = json_decode( file_get_contents( $login_json_file ), true );
			if ( $login_data && ! empty( $login_data['content'] ) ) {
				register_block_pattern(
					'mteja/login-page',
					array(
						'title'       => __( 'Mteja — Custom Support Login Card', 'mteja' ),
						'description' => __( 'Fast asynchronous login card for support agents and team members.', 'mteja' ),
						'categories'  => array( 'mteja', 'forms' ),
						'content'     => $login_data['content'],
					)
				);
			}
		}
	}

	/**
	 * Enqueue design tokens CSS on frontend.
	 */
	public static function enqueue_tokens() {
		wp_enqueue_style(
			'mteja-tokens',
			plugins_url( 'assets/tokens.css', dirname( __FILE__ ) ),
			array(),
			'1.0.0'
		);
	}

	/**
	 * Enqueue design tokens CSS inside Gutenberg Block Editor.
	 */
	public static function enqueue_editor_tokens() {
		wp_enqueue_style(
			'mteja-editor-tokens',
			plugins_url( 'assets/tokens.css', dirname( __FILE__ ) ),
			array(),
			'1.0.0'
		);
	}
}
