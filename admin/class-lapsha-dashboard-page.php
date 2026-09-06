<?php
/**
 * Dashboard admin page.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Landing page for Lapsha tools.
 */
class Lapsha_Dashboard_Page extends Lapsha_Admin_Page {

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
	protected function render() {
		$modules = $this->core->modules();
		require LAPSHA_WP_TOOLS_DIR . 'admin/views/dashboard.php';
	}
}
