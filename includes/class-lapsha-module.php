<?php
/**
 * Base class for Lapsha modules.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Module contract used by Lapsha_Core.
 */
abstract class Lapsha_Module {

	/**
	 * Stable machine identifier.
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Translatable module title.
	 *
	 * @return string
	 */
	abstract public function name();

	/**
	 * Short translatable description.
	 *
	 * @return string
	 */
	abstract public function description();

	/**
	 * Hooks that must run on every request. Keep this empty unless required.
	 *
	 * @return void
	 */
	public function init() {}

	/**
	 * Register admin pages and admin-post handlers.
	 *
	 * @param Lapsha_Admin $admin Admin coordinator.
	 * @return void
	 */
	public function register_admin( $admin ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	}

	/**
	 * Optional extra assets on Lapsha screens.
	 *
	 * @param string $hook Current admin hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	}
}
