<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Version 0.1.1 does not store persistent Lapsha settings. Site content
 * (revisions, comments, transients) must never be removed here.
 *
 * @package Lapsha_WP_Tools
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
