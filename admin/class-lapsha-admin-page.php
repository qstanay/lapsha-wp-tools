<?php
/**
 * Base admin page wrapper.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Capability-checked admin page.
 */
abstract class Lapsha_Admin_Page {

	/**
	 * @return void
	 */
	public function render_page() {
		Lapsha_Security::require_capability();

		echo '<div class="wrap lapsha-wrap">';
		$this->render();
		echo '</div>';
	}

	/**
	 * Output page body (already inside .wrap).
	 *
	 * @return void
	 */
	abstract protected function render();
}
