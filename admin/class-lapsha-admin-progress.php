<?php
/**
 * Shared stepped-progress UI for admin jobs.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the progress panel and builds the JSON payload its script expects.
 *
 * Job state (transients, SQL, what a “step” means) stays in the calling module.
 * This class is only the admin chrome and the response contract.
 */
class Lapsha_Admin_Progress {

	/**
	 * JSON keys the shared admin.js progress runner reads.
	 *
	 * @param array  $items    Rows: id, current, total, complete.
	 * @param bool   $complete Whether the whole job finished.
	 * @param string $redirect URL to load when complete.
	 * @return array<string, mixed>
	 */
	public static function payload( $items, $complete, $redirect ) {
		$out          = array();
		$current_id   = '';
		$all_current  = 0;
		$all_total    = 0;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				continue;
			}

			$id      = sanitize_key( (string) $item['id'] );
			$current = isset( $item['current'] ) ? (int) $item['current'] : 0;
			$total   = isset( $item['total'] ) ? (int) $item['total'] : 0;
			$done    = ! empty( $item['complete'] );

			$all_current += $current;
			$all_total   += $total;

			if ( '' === $current_id && ! $done ) {
				$current_id = $id;
			}

			$out[] = array(
				'id'       => $id,
				'current'  => $current,
				'total'    => $total,
				'complete' => $done,
				'percent'  => self::percent( $current, $total, $done ),
			);
		}

		return array(
			'complete' => (bool) $complete,
			'redirect' => esc_url_raw( $redirect ),
			'current'  => $current_id,
			'items'    => $out,
			'overall'  => array(
				'current' => $all_current,
				'total'   => $all_total,
				'percent' => self::percent( $all_current, $all_total, $complete ),
			),
		);
	}

	/**
	 * Print the panel + fallback continue form.
	 *
	 * @param array $args {
	 *     @type string $id                DOM prefix.
	 *     @type string $status_title      Strong text in the status notice.
	 *     @type string $status_text       Extra status sentence.
	 *     @type string $state_active      Per-row label while that row is running.
	 *     @type string $state_done        Per-row label when a row finished.
	 *     @type string $overall_template  sprintf-style "%1$s of %2$s …".
	 *     @type string $continue_label    Fallback submit button.
	 *     @type string $continue_hint     Text next to the fallback button.
	 *     @type string $form_action       Form action URL.
	 *     @type string $action            admin-post.php action name.
	 *     @type string $nonce_action      wp_nonce_field action.
	 *     @type string $nonce_field       wp_nonce_field name.
	 *     @type array  $hidden_fields     Extra hidden inputs (name => value).
	 *     @type array  $items             Rows: id, label, current, total, complete.
	 * }
	 * @return void
	 */
	public static function render( $args ) {
		$args = wp_parse_args( $args, self::defaults() );

		$id               = sanitize_html_class( $args['id'] );
		$status_title     = $args['status_title'];
		$status_text      = $args['status_text'];
		$state_active     = $args['state_active'];
		$state_done       = $args['state_done'];
		$overall_template = $args['overall_template'];
		$continue_label   = $args['continue_label'];
		$continue_hint    = $args['continue_hint'];
		$form_action      = $args['form_action'];
		$action           = $args['action'];
		$nonce_action     = $args['nonce_action'];
		$nonce_field      = $args['nonce_field'];
		$hidden_fields    = is_array( $args['hidden_fields'] ) ? $args['hidden_fields'] : array();
		$items            = is_array( $args['items'] ) ? $args['items'] : array();
		$form_id          = $id . '-continue-form';

		$overall_current = 0;
		$overall_total   = 0;
		$current_id      = '';
		$rows            = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				continue;
			}

			$row = array(
				'id'       => sanitize_key( (string) $item['id'] ),
				'label'    => isset( $item['label'] ) ? (string) $item['label'] : (string) $item['id'],
				'current'  => isset( $item['current'] ) ? (int) $item['current'] : 0,
				'total'    => isset( $item['total'] ) ? (int) $item['total'] : 0,
				'complete' => ! empty( $item['complete'] ),
			);

			$overall_current += $row['current'];
			$overall_total   += $row['total'];

			if ( '' === $current_id && ! $row['complete'] ) {
				$current_id = $row['id'];
			}

			$row['percent'] = self::percent( $row['current'], $row['total'], $row['complete'] );
			$rows[]         = $row;
		}

		$overall_percent = self::percent( $overall_current, $overall_total, false );

		require LAPSHA_WP_TOOLS_DIR . 'admin/views/progress.php';
	}

	/**
	 * @param int  $current  Completed count.
	 * @param int  $total    Estimated total.
	 * @param bool $done     Row finished.
	 * @return int
	 */
	public static function percent( $current, $total, $done ) {
		if ( $done && $current > 0 ) {
			return 100;
		}
		if ( $total <= 0 ) {
			return 0;
		}

		return (int) min( 100, floor( ( $current / $total ) * 100 ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function defaults() {
		return array(
			'id'               => 'lapsha-progress',
			'status_title'     => __( 'Working…', 'lapsha-wp-tools' ),
			'status_text'      => '',
			'state_active'     => __( 'Working…', 'lapsha-wp-tools' ),
			'state_done'       => __( 'Done', 'lapsha-wp-tools' ),
			/* translators: 1: completed count, 2: estimated total */
			'overall_template' => __( '%1$s of %2$s', 'lapsha-wp-tools' ),
			'continue_label'   => __( 'Continue', 'lapsha-wp-tools' ),
			'continue_hint'    => __( 'Needed only if JavaScript is off.', 'lapsha-wp-tools' ),
			'form_action'      => admin_url( 'admin-post.php' ),
			'action'           => '',
			'nonce_action'     => '',
			'nonce_field'      => 'lapsha_job_nonce',
			'hidden_fields'    => array(),
			'items'            => array(),
		);
	}
}
