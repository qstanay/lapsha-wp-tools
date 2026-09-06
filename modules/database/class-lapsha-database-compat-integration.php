<?php
/**
 * One plugin or theme that can reserve leftover rows while it is running.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ship a new compatibility pack by subclassing this, returning rules(), and
 * appending an instance from the `lapsha_wp_tools_database_compat` filter
 * (or from Lapsha_Database_Compat::bundled() for code that lives in this repo).
 */
abstract class Lapsha_Database_Compat_Integration {

	/**
	 * Stable machine id (sanitize_key).
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Public name for admin copy (plugin or theme title, not translated).
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * Whether that plugin or theme is running on this request.
	 *
	 * @return bool
	 */
	abstract public function is_active();

	/**
	 * Exceptions to apply when is_active() is true.
	 *
	 * @return Lapsha_Database_Compat_Rules
	 */
	public function rules() {
		return Lapsha_Database_Compat_Rules::none();
	}
}
