<?php
/**
 * Top-level admin menu.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Lapsha menu and dashboard.
 */
class Lapsha_Admin_Menu {

	/**
	 * @var Lapsha_Core
	 */
	private $core;

	/**
	 * @param Lapsha_Core $core Core instance.
	 */
	public function __construct( Lapsha_Core $core ) {
		$this->core = $core;
	}

	/**
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register' ) );
	}

	/**
	 * @return void
	 */
	public function register() {
		$capability = Lapsha_Security::capability();
		$dashboard  = new Lapsha_Dashboard_Page( $this->core );

		add_menu_page(
			__( 'Lapsha WP Tools', 'lapsha-wp-tools' ),
			__( 'Lapsha WP Tools', 'lapsha-wp-tools' ),
			$capability,
			Lapsha_Admin::PARENT_SLUG,
			array( $dashboard, 'render_page' ),
			'dashicons-admin-tools',
			58
		);

		add_submenu_page(
			Lapsha_Admin::PARENT_SLUG,
			__( 'Dashboard', 'lapsha-wp-tools' ),
			__( 'Dashboard', 'lapsha-wp-tools' ),
			$capability,
			Lapsha_Admin::PARENT_SLUG,
			array( $dashboard, 'render_page' )
		);

		/**
		 * Register extra Lapsha submenus (modules hook here).
		 *
		 * @param string $parent_slug Parent menu slug.
		 * @param string $capability  Required capability.
		 */
		do_action( 'lapsha_wp_tools_admin_menu', Lapsha_Admin::PARENT_SLUG, $capability );
	}
}
