<?php
/**
 * Capability and nonce helpers.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Server-side security checks for admin actions.
 */
class Lapsha_Security {

	/**
	 * Capability required for Lapsha admin screens and cleanup.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filter the capability used for all Lapsha admin tools.
		 *
		 * @param string $capability Default manage_options.
		 */
		return apply_filters( 'lapsha_wp_tools_capability', 'manage_options' );
	}

	/**
	 * Whether the current user may use Lapsha tools.
	 *
	 * @return bool
	 */
	public static function current_user_can() {
		return current_user_can( self::capability() );
	}

	/**
	 * Stop the request when the user lacks permission.
	 *
	 * @return void
	 */
	public static function require_capability() {
		if ( ! self::current_user_can() ) {
			wp_die(
				esc_html__( 'You do not have permission to do this.', 'lapsha-wp-tools' ),
				esc_html__( 'Forbidden', 'lapsha-wp-tools' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Verify a nonce from the request.
	 *
	 * @param string $key    Request key holding the nonce.
	 * @param string $action Nonce action name.
	 * @return void
	 */
	public static function require_nonce( $key, $action ) {
		$nonce = '';
		if ( isset( $_REQUEST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die(
				esc_html__( 'Security check failed. Please try again.', 'lapsha-wp-tools' ),
				esc_html__( 'Forbidden', 'lapsha-wp-tools' ),
				array( 'response' => 403 )
			);
		}
	}
}
