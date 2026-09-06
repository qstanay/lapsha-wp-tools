<?php
/**
 * Divi cleanup exceptions.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reserves Divi layout and Theme Builder types while Divi is running.
 */
class Lapsha_Database_Compat_Divi extends Lapsha_Database_Compat_Integration {

	/**
	 * @return string
	 */
	public function id() {
		return 'divi';
	}

	/**
	 * @return string
	 */
	public function label() {
		return 'Divi';
	}

	/**
	 * Divi theme, Extra theme, or the Divi Builder plugin.
	 *
	 * @return bool
	 */
	public function is_active() {
		return Lapsha_Database_Compat_Detect::any_constant(
			array( 'ET_BUILDER_VERSION', 'ET_BUILDER_PLUGIN_DIR', 'ET_CORE_VERSION' )
		)
			|| Lapsha_Database_Compat_Detect::any_func(
				array( 'et_pb_is_pagebuilder_used', 'et_setup_builder' )
			)
			|| Lapsha_Database_Compat_Detect::parent_theme( array( 'Divi', 'Extra' ) )
			|| Lapsha_Database_Compat_Detect::plugin( 'divi-builder/divi-builder.php' );
	}

	/**
	 * @return Lapsha_Database_Compat_Rules
	 */
	public function rules() {
		return Lapsha_Database_Compat_Rules::from_array(
			array(
				Lapsha_Database_Compat_Rules::EXCLUDE_POST_TYPES => array(
					'et_pb_layout',
					'et_template',
					'et_header_layout',
					'et_body_layout',
					'et_footer_layout',
				),
			)
		);
	}
}
