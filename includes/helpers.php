<?php
/**
 * Shared helpers.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Category keys the Database Cleaner accepts from the request.
 *
 * @return string[]
 */
function lapsha_wp_tools_cleaner_categories() {
	return array(
		'revisions',
		'auto_drafts',
		'trashed_posts',
		'spam_comments',
		'trashed_comments',
		'expired_transients',
	);
}

/**
 * Keep only known cleaner category ids.
 *
 * @param mixed $ids Raw request value.
 * @return string[]
 */
function lapsha_wp_tools_sanitize_category_ids( $ids ) {
	if ( ! is_array( $ids ) ) {
		return array();
	}

	$clean = array();
	foreach ( $ids as $id ) {
		$clean[] = sanitize_key( (string) $id );
	}

	return array_values( array_intersect( $clean, lapsha_wp_tools_cleaner_categories() ) );
}

/**
 * Format a byte count for admin display.
 *
 * @param int|null $bytes Byte size or null when unknown.
 * @return string
 */
function lapsha_wp_tools_format_bytes( $bytes ) {
	if ( null === $bytes ) {
		return '';
	}

	$bytes = (int) $bytes;
	if ( $bytes < 1024 ) {
		return $bytes . ' B';
	}

	$units = array( 'KB', 'MB', 'GB' );
	$size  = (float) $bytes;
	foreach ( $units as $unit ) {
		$size /= 1024;
		if ( $size < 1024 ) {
			return ( $size >= 10 ? (string) (int) round( $size ) : number_format( $size, 1 ) ) . ' ' . $unit;
		}
	}

	return number_format( $size / 1024, 1 ) . ' TB';
}

/**
 * Seconds of work allowed in one HTTP request (cheap-hosting safe).
 *
 * @return float
 */
function lapsha_wp_tools_http_time_budget() {
	$budget = (float) apply_filters( 'lapsha_wp_tools_http_time_budget', 8.0 );
	return max( 2.0, min( 15.0, $budget ) );
}

/**
 * Tighten PHP limits for one admin action without relying on unlimited runtime.
 *
 * @return void
 */
function lapsha_wp_tools_prepare_budgeted_request() {
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}

	/*
	 * Hosts often cap this at 30s and ignore higher values.
	 * The cleaner stops on its own budget before that.
	 */
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 25 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}
