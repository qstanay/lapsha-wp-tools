<?php
/**
 * Admin coordinator.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Menus, shared assets, module admin registration.
 */
class Lapsha_Admin {

	const PARENT_SLUG = 'lapsha-wp-tools';

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
	public function init() {
		$menu = new Lapsha_Admin_Menu( $this->core );
		$menu->hooks();

		foreach ( $this->core->modules() as $module ) {
			$module->register_admin( $this );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * @return Lapsha_Core
	 */
	public function core() {
		return $this->core;
	}

	/**
	 * Load CSS/JS only on Lapsha screens.
	 *
	 * @param string $hook Admin hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( ! $this->is_lapsha_screen( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'lapsha-wp-tools-admin',
			LAPSHA_WP_TOOLS_URL . 'assets/css/admin.css',
			array(),
			(string) filemtime( LAPSHA_WP_TOOLS_DIR . 'assets/css/admin.css' )
		);

		wp_enqueue_script(
			'lapsha-wp-tools-admin',
			LAPSHA_WP_TOOLS_URL . 'assets/js/admin.js',
			array(),
			(string) filemtime( LAPSHA_WP_TOOLS_DIR . 'assets/js/admin.js' ),
			true
		);

		wp_localize_script(
			'lapsha-wp-tools-admin',
			'lapshaWpTools',
			array(
				'i18n' => array(
					'working' => __( 'Working…', 'lapsha-wp-tools' ),
					'done'    => __( 'Done', 'lapsha-wp-tools' ),
				),
			)
		);

		foreach ( $this->core->modules() as $module ) {
			$module->enqueue_assets( $hook );
		}
	}

	/**
	 * @param string $hook Hook suffix.
	 * @return bool
	 */
	private function is_lapsha_screen( $hook ) {
		return false !== strpos( $hook, 'lapsha-wp-tools' ) || false !== strpos( $hook, 'lapsha-database' );
	}
}
