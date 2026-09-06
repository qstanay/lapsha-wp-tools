<?php
/**
 * Database module bootstrap.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-lapsha-database-compat-rules.php';
require_once __DIR__ . '/class-lapsha-database-compat-detect.php';
require_once __DIR__ . '/class-lapsha-database-compat-integration.php';
require_once __DIR__ . '/compat/class-lapsha-database-compat-elementor.php';
require_once __DIR__ . '/compat/class-lapsha-database-compat-divi.php';
require_once __DIR__ . '/class-lapsha-database-compat.php';
require_once __DIR__ . '/class-lapsha-database-scanner.php';
require_once __DIR__ . '/class-lapsha-database-cleaner.php';

/**
 * Database tools module (Cleaner in 0.1.0).
 */
class Lapsha_Database_Module extends Lapsha_Module {

	const PAGE_SLUG       = 'lapsha-database-cleaner';
	const ACTION_SCAN     = 'lapsha_scan_database';
	const ACTION_CLEAN    = 'lapsha_clean_database';
	const NONCE_SCAN      = 'lapsha_scan_database';
	const NONCE_CLEAN     = 'lapsha_clean_database';
	const TRANSIENT_SCAN  = 'lapsha_wp_tools_scan_';
	const TRANSIENT_CLEAN = 'lapsha_wp_tools_clean_';
	const TRANSIENT_JOB   = 'lapsha_wp_tools_job_';
	const TRANSIENT_TTL   = 900;
	const JOB_TTL         = 1800;

	/**
	 * @return string
	 */
	public function id() {
		return 'database';
	}

	/**
	 * @return string
	 */
	public function name() {
		return __( 'Database', 'lapsha-wp-tools' );
	}

	/**
	 * @return string
	 */
	public function description() {
		return __( 'Scan and remove leftover WordPress data such as revisions, trash, spam, and expired transients.', 'lapsha-wp-tools' );
	}

	/**
	 * @param Lapsha_Admin $admin Admin coordinator.
	 * @return void
	 */
	public function register_admin( $admin ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		add_action( 'lapsha_wp_tools_admin_menu', array( $this, 'register_menu' ), 10, 2 );
		add_action( 'admin_post_' . self::ACTION_SCAN, array( $this, 'handle_scan' ) );
		add_action( 'admin_post_' . self::ACTION_CLEAN, array( $this, 'handle_clean' ) );
	}

	/**
	 * @param string $parent_slug Parent menu slug.
	 * @param string $capability  Capability.
	 * @return void
	 */
	public function register_menu( $parent_slug, $capability ) {
		add_submenu_page(
			$parent_slug,
			__( 'Database Cleaner', 'lapsha-wp-tools' ),
			_x( 'DB Cleanup', 'admin menu title', 'lapsha-wp-tools' ),
			$capability,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * @return void
	 */
	public function render_page() {
		Lapsha_Security::require_capability();

		$scanner        = new Lapsha_Database_Scanner();
		$compat_notice  = ( new Lapsha_Database_Compat() )->admin_notice();
		$scan_payload   = get_transient( $this->transient_key( self::TRANSIENT_SCAN ) );
		$scan           = $this->scan_results_from_payload( $scan_payload );
		$scan_duration  = $this->scan_duration_from_payload( $scan_payload );
		$clean_result   = get_transient( $this->transient_key( self::TRANSIENT_CLEAN ) );
		$job            = get_transient( $this->transient_key( self::TRANSIENT_JOB ) );
		$confirm        = isset( $_GET['confirm'] ) && '1' === $_GET['confirm']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$working        = isset( $_GET['working'] ) && '1' === $_GET['working']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error          = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cleaned        = isset( $_GET['cleaned'] ) && '1' === $_GET['cleaned']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$scanned        = isset( $_GET['scanned'] ) && '1' === $_GET['scanned']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected       = array();
		$progress_items = array();

		if ( ! $cleaned && is_array( $job ) && ! empty( $job['categories'] ) ) {
			$working = true;
			$confirm = false;
		}

		if ( $working && ( ! is_array( $job ) || empty( $job['categories'] ) ) ) {
			$working = false;
			$error   = 'job_missing';
		}

		if ( $working && is_array( $job ) ) {
			$progress_items = $this->progress_items_from_job( $job, $scanner );
		}

		if ( $confirm && ! $working ) {
			$selected = $this->pending_categories_from_request();
			if ( empty( $selected ) ) {
				$confirm = false;
				$error   = 'none_selected';
			} else {
				$scan = $scanner->scan_many( $selected );
			}
		}

		if ( $cleaned && is_array( $clean_result ) ) {
			delete_transient( $this->transient_key( self::TRANSIENT_CLEAN ) );
		}

		echo '<div class="wrap lapsha-wrap">';
		require LAPSHA_WP_TOOLS_DIR . 'modules/database/views/cleaner.php';
		echo '</div>';
	}

	/**
	 * @return void
	 */
	public function handle_scan() {
		Lapsha_Security::require_capability();
		Lapsha_Security::require_nonce( 'lapsha_scan_nonce', self::NONCE_SCAN );
		lapsha_wp_tools_prepare_budgeted_request();

		$scanner = new Lapsha_Database_Scanner();
		$started = microtime( true );
		set_transient(
			$this->transient_key( self::TRANSIENT_SCAN ),
			array(
				'results'  => $scanner->scan(),
				'duration' => microtime( true ) - $started,
			),
			self::TRANSIENT_TTL
		);

		wp_safe_redirect( $this->page_url( array( 'scanned' => '1' ) ) );
		exit;
	}

	/**
	 * @return void
	 */
	public function handle_clean() {
		Lapsha_Security::require_capability();
		Lapsha_Security::require_nonce( 'lapsha_clean_nonce', self::NONCE_CLEAN );
		lapsha_wp_tools_prepare_budgeted_request();

		$continuing = isset( $_POST['continue'] ) && '1' === $_POST['continue'];

		if ( $continuing ) {
			$this->run_job_step();
		}

		$categories = array();
		if ( isset( $_POST['categories'] ) ) {
			$categories = lapsha_wp_tools_sanitize_category_ids( wp_unslash( $_POST['categories'] ) );
		}

		if ( empty( $categories ) ) {
			wp_safe_redirect( $this->page_url( array( 'error' => 'none_selected' ) ) );
			exit;
		}

		$confirmed = isset( $_POST['confirmed'] ) && '1' === $_POST['confirmed'];
		if ( ! $confirmed ) {
			wp_safe_redirect(
				$this->page_url(
					array(
						'confirm'    => '1',
						'categories' => implode( ',', $categories ),
					)
				)
			);
			exit;
		}

		$scanner = new Lapsha_Database_Scanner();
		$totals  = array();
		foreach ( $categories as $category ) {
			$totals[ $category ] = $scanner->count( $category );
		}

		$job = array(
			'categories' => $categories,
			'totals'     => $totals,
			'results'    => array(),
		);

		set_transient( $this->transient_key( self::TRANSIENT_JOB ), $job, self::JOB_TTL );

		/*
		 * Run the first slice in this request. Small jobs finish here and skip
		 * the progress screen; large jobs land on progress already moving.
		 */
		$outcome = $this->process_job_step();
		if ( empty( $outcome['ok'] ) ) {
			wp_safe_redirect( $this->page_url( array( 'error' => 'job_missing' ) ) );
			exit;
		}
		if ( ! empty( $outcome['complete'] ) ) {
			wp_safe_redirect( $this->page_url( array( 'cleaned' => '1' ) ) );
			exit;
		}

		wp_safe_redirect( $this->page_url( array( 'working' => '1' ) ) );
		exit;
	}

	/**
	 * Whether this request should return JSON instead of a redirect.
	 *
	 * @return bool
	 */
	private function wants_json() {
		return isset( $_POST['lapsha_ajax'] ) && '1' === $_POST['lapsha_ajax']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Process one time-boxed slice of the current cleanup job.
	 *
	 * @return void
	 */
	private function run_job_step() {
		$outcome = $this->process_job_step();

		if ( $this->wants_json() ) {
			if ( empty( $outcome['ok'] ) ) {
				wp_send_json_error(
					array(
						'code' => isset( $outcome['error'] ) ? $outcome['error'] : 'job_missing',
					)
				);
			}

			wp_send_json_success( $outcome['payload'] );
		}

		if ( empty( $outcome['ok'] ) ) {
			wp_safe_redirect( $this->page_url( array( 'error' => 'job_missing' ) ) );
			exit;
		}

		if ( ! empty( $outcome['complete'] ) ) {
			wp_safe_redirect( $this->page_url( array( 'cleaned' => '1' ) ) );
			exit;
		}

		wp_safe_redirect( $this->page_url( array( 'working' => '1' ) ) );
		exit;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function process_job_step() {
		$job = get_transient( $this->transient_key( self::TRANSIENT_JOB ) );
		if ( ! is_array( $job ) || empty( $job['categories'] ) ) {
			return array(
				'ok'    => false,
				'error' => 'job_missing',
			);
		}

		$cleaner  = new Lapsha_Database_Cleaner();
		$deadline = microtime( true ) + lapsha_wp_tools_http_time_budget();
		$step     = $cleaner->run_until_budget(
			$job['categories'],
			isset( $job['results'] ) ? $job['results'] : array(),
			$deadline
		);

		$job['results'] = $step['results'];

		if ( $step['complete'] ) {
			delete_transient( $this->transient_key( self::TRANSIENT_JOB ) );
			delete_transient( $this->transient_key( self::TRANSIENT_SCAN ) );
			set_transient( $this->transient_key( self::TRANSIENT_CLEAN ), $job['results'], self::TRANSIENT_TTL );
		} else {
			set_transient( $this->transient_key( self::TRANSIENT_JOB ), $job, self::JOB_TTL );
		}

		return array(
			'ok'       => true,
			'complete' => $step['complete'],
			'payload'  => $this->job_payload_for_js( $job, $step['complete'] ),
		);
	}

	/**
	 * @param array $job      Job data.
	 * @param bool  $complete Whether the job finished.
	 * @return array<string, mixed>
	 */
	private function job_payload_for_js( $job, $complete ) {
		return Lapsha_Admin_Progress::payload(
			$this->progress_items_from_job( $job ),
			$complete,
			$this->page_url( array( 'cleaned' => '1' ) )
		);
	}

	/**
	 * Map a cleaner job into the shared progress UI rows.
	 *
	 * @param array                       $job     Job data.
	 * @param Lapsha_Database_Scanner|null $scanner Optional scanner for labels.
	 * @return array<int, array<string, mixed>>
	 */
	private function progress_items_from_job( $job, $scanner = null ) {
		if ( ! $scanner instanceof Lapsha_Database_Scanner ) {
			$scanner = new Lapsha_Database_Scanner();
		}

		$definitions = $scanner->definitions();
		$items       = array();

		foreach ( $job['categories'] as $category_id ) {
			$items[] = array(
				'id'       => $category_id,
				'label'    => isset( $definitions[ $category_id ] ) ? $definitions[ $category_id ]['label'] : $category_id,
				'current'  => isset( $job['results'][ $category_id ]['deleted'] ) ? (int) $job['results'][ $category_id ]['deleted'] : 0,
				'total'    => isset( $job['totals'][ $category_id ] ) ? (int) $job['totals'][ $category_id ] : 0,
				'complete' => ! empty( $job['results'][ $category_id ]['complete'] ),
			);
		}

		return $items;
	}

	/**
	 * @param mixed $payload Transient payload.
	 * @return array|false
	 */
	private function scan_results_from_payload( $payload ) {
		if ( ! is_array( $payload ) ) {
			return false;
		}

		if ( isset( $payload['results'] ) && is_array( $payload['results'] ) ) {
			return $payload['results'];
		}

		return $payload;
	}

	/**
	 * @param mixed $payload Transient payload.
	 * @return float|null
	 */
	private function scan_duration_from_payload( $payload ) {
		if ( is_array( $payload ) && isset( $payload['duration'] ) ) {
			return (float) $payload['duration'];
		}

		return null;
	}

	/**
	 * @param string $prefix Transient prefix.
	 * @return string
	 */
	private function transient_key( $prefix ) {
		return $prefix . get_current_user_id();
	}

	/**
	 * @param array $args Query args.
	 * @return string
	 */
	private function page_url( $args = array() ) {
		return add_query_arg(
			array_merge(
				array( 'page' => self::PAGE_SLUG ),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Categories passed to the confirmation screen.
	 *
	 * @return string[]
	 */
	private function pending_categories_from_request() {
		$raw = '';
		if ( isset( $_GET['categories'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw = sanitize_text_field( wp_unslash( $_GET['categories'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( '' === $raw ) {
			return array();
		}

		return lapsha_wp_tools_sanitize_category_ids( explode( ',', $raw ) );
	}
}
