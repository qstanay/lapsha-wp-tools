<?php
/**
 * Elementor cleanup exceptions.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reserves Elementor library types while Elementor is running.
 */
class Lapsha_Database_Compat_Elementor extends Lapsha_Database_Compat_Integration {

	/**
	 * @return string
	 */
	public function id() {
		return 'elementor';
	}

	/**
	 * @return string
	 */
	public function label() {
		return 'Elementor';
	}

	/**
	 * @return bool
	 */
	public function is_active() {
		return Lapsha_Database_Compat_Detect::constant( 'ELEMENTOR_VERSION' )
			|| Lapsha_Database_Compat_Detect::class_name( '\Elementor\Plugin' )
			|| Lapsha_Database_Compat_Detect::hook_fired( 'elementor/loaded' )
			|| Lapsha_Database_Compat_Detect::plugin( 'elementor/elementor.php' );
	}

	/**
	 * @return Lapsha_Database_Compat_Rules
	 */
	public function rules() {
		return Lapsha_Database_Compat_Rules::from_array(
			array(
				Lapsha_Database_Compat_Rules::EXCLUDE_POST_TYPES => array(
					'elementor_library',
					'e-floating-buttons',
				),
			)
		);
	}
}
