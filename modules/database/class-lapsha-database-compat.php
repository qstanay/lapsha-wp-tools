<?php
/**
 * Registry of plugin/theme cleanup integrations.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Collects active integrations and turns their rules into query constraints.
 *
 * Scanner and cleaner talk only to this class. They must not name Elementor,
 * Divi, or any other third-party slug.
 */
class Lapsha_Database_Compat {

	/**
	 * Integrations shipped with this module.
	 *
	 * Add a new shipped pack here (and require its file from database.php).
	 * Third parties should use the `lapsha_wp_tools_database_compat` filter.
	 *
	 * @return Lapsha_Database_Compat_Integration[]
	 */
	public static function bundled() {
		return array(
			new Lapsha_Database_Compat_Elementor(),
			new Lapsha_Database_Compat_Divi(),
		);
	}

	/**
	 * Registered integrations, including filter additions.
	 *
	 * @return Lapsha_Database_Compat_Integration[]
	 */
	public function integrations() {
		$items = apply_filters( 'lapsha_wp_tools_database_compat', self::bundled() );
		if ( ! is_array( $items ) ) {
			$items = self::bundled();
		}

		$out  = array();
		$seen = array();
		foreach ( $items as $item ) {
			if ( ! $item instanceof Lapsha_Database_Compat_Integration ) {
				continue;
			}

			$id = sanitize_key( $item->id() );
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				continue;
			}

			$seen[ $id ] = true;
			$out[]       = $item;
		}

		return $out;
	}

	/**
	 * Integrations that should apply on this request.
	 *
	 * @return Lapsha_Database_Compat_Integration[]
	 */
	public function active_integrations() {
		$active = array();

		foreach ( $this->integrations() as $item ) {
			$is_active = (bool) apply_filters(
				'lapsha_wp_tools_database_compat_is_active',
				$item->is_active(),
				$item->id(),
				$item
			);
			if ( $is_active ) {
				$active[] = $item;
			}
		}

		return $active;
	}

	/**
	 * @return string[]
	 */
	public function active_ids() {
		$ids = array();
		foreach ( $this->active_integrations() as $item ) {
			$ids[] = $item->id();
		}

		return $ids;
	}

	/**
	 * Post types reserved by every active integration.
	 *
	 * @return string[]
	 */
	public function exclude_post_types() {
		$types = array();

		foreach ( $this->active_integrations() as $item ) {
			foreach ( $item->rules()->exclude_post_types() as $type ) {
				if ( ! in_array( $type, $types, true ) ) {
					$types[] = $type;
				}
			}
		}

		/**
		 * Final CPT list skipped for auto-draft and trash cleanup.
		 *
		 * @param string[] $types Reserved post types.
		 * @param string[] $ids   Active integration ids.
		 */
		$filtered = apply_filters( 'lapsha_wp_tools_database_compat_exclude_post_types', $types, $this->active_ids() );
		if ( ! is_array( $filtered ) ) {
			return $types;
		}

		$out = array();
		foreach ( $filtered as $type ) {
			$type = sanitize_key( (string) $type );
			if ( '' !== $type && ! in_array( $type, $out, true ) ) {
				$out[] = $type;
			}
		}

		return $out;
	}

	/**
	 * Prepared SQL fragment for leftover post-status queries, or empty.
	 *
	 * @return string
	 */
	public function sql_and_exclude_protected_types() {
		global $wpdb;

		$types = $this->exclude_post_types();
		if ( empty( $types ) ) {
			return '';
		}

		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		return $wpdb->prepare(
			" AND post_type NOT IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are %s only.
			...$types
		);
	}

	/**
	 * Admin explanation when any integration is active. Empty otherwise.
	 *
	 * @return string
	 */
	public function admin_notice() {
		$labels = array();
		foreach ( $this->active_integrations() as $item ) {
			$label = trim( (string) $item->label() );
			if ( '' !== $label && ! in_array( $label, $labels, true ) ) {
				$labels[] = $label;
			}
		}

		if ( empty( $labels ) ) {
			return '';
		}

		$list  = function_exists( 'wp_sprintf_l' ) ? wp_sprintf_l( '%l', $labels ) : implode( ', ', $labels );
		$count = count( $labels );

		return sprintf(
			/* translators: %s: plugin or theme names, e.g. Elementor, Divi */
			_n(
				'%s is active. Its templates and layouts (including those in trash) are not selected. Revisions, unsaved page auto-drafts, ordinary trash, spam, and expired transients can still be cleaned.',
				'%s are active. Their templates and layouts (including those in trash) are not selected. Revisions, unsaved page auto-drafts, ordinary trash, spam, and expired transients can still be cleaned.',
				$count,
				'lapsha-wp-tools'
			),
			$list
		);
	}

	/**
	 * Extra sentence for a leftover category while integrations are running.
	 *
	 * @param string $category_id Category id.
	 * @return string
	 */
	public function category_note( $category_id ) {
		if ( empty( $this->active_ids() ) ) {
			return '';
		}

		switch ( $category_id ) {
			case 'revisions':
				return __( 'Includes builder history. The published layout is not removed.', 'lapsha-wp-tools' );
			case 'auto_drafts':
				return __( 'Unsaved builder pages are included. Library and layout auto-drafts are not.', 'lapsha-wp-tools' );
			case 'trashed_posts':
				return __( 'Trashed posts and pages are included. Builder templates in trash are not.', 'lapsha-wp-tools' );
			case 'expired_transients':
				return __( 'Expired builder caches are included. Active transients are not.', 'lapsha-wp-tools' );
			default:
				return '';
		}
	}
}
