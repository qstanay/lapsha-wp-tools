<?php
/**
 * Plugin core: modules and admin bootstrap.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Singleton loader.
 */
class Lapsha_Core {

	/**
	 * Shared instance.
	 *
	 * @var Lapsha_Core|null
	 */
	private static $instance = null;

	/**
	 * Registered modules keyed by id.
	 *
	 * @var Lapsha_Module[]
	 */
	private $modules = array();

	/**
	 * Admin coordinator, only set in wp-admin.
	 *
	 * @var Lapsha_Admin|null
	 */
	private $admin = null;

	/**
	 * @return Lapsha_Core
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}

		return self::$instance;
	}

	private function __construct() {}

	/**
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Load translations, modules, and admin.
	 *
	 * @return void
	 */
	private function boot() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		$this->register_modules();

		foreach ( $this->modules as $module ) {
			$module->init();
		}

		if ( is_admin() ) {
			require_once LAPSHA_WP_TOOLS_DIR . 'includes/class-lapsha-admin.php';
			require_once LAPSHA_WP_TOOLS_DIR . 'admin/class-lapsha-admin-page.php';
			require_once LAPSHA_WP_TOOLS_DIR . 'admin/class-lapsha-admin-menu.php';
			require_once LAPSHA_WP_TOOLS_DIR . 'admin/class-lapsha-admin-progress.php';
			require_once LAPSHA_WP_TOOLS_DIR . 'admin/class-lapsha-dashboard-page.php';

			$this->admin = new Lapsha_Admin( $this );
			$this->admin->init();
		}
	}

	/**
	 * Load bundled translations from languages/.
	 *
	 * Hooked on init so WordPress 6.7+ does not treat this as too early.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'lapsha-wp-tools',
			false,
			dirname( LAPSHA_WP_TOOLS_BASENAME ) . '/languages'
		);
	}

	/**
	 * @return void
	 */
	private function register_modules() {
		require_once LAPSHA_WP_TOOLS_DIR . 'modules/database/database.php';
		$this->add_module( new Lapsha_Database_Module() );
	}

	/**
	 * @param Lapsha_Module $module Module instance.
	 * @return void
	 */
	public function add_module( Lapsha_Module $module ) {
		$this->modules[ $module->id() ] = $module;
	}

	/**
	 * @return Lapsha_Module[]
	 */
	public function modules() {
		return $this->modules;
	}

	/**
	 * @param string $id Module id.
	 * @return Lapsha_Module|null
	 */
	public function module( $id ) {
		return isset( $this->modules[ $id ] ) ? $this->modules[ $id ] : null;
	}

	/**
	 * @return Lapsha_Admin|null
	 */
	public function admin() {
		return $this->admin;
	}
}
