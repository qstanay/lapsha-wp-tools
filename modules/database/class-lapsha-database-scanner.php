<?php
/**
 * Read-only database scans for the cleaner.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Finds cleanup candidates. Never deletes.
 */
class Lapsha_Database_Scanner {

	const CATEGORY_REVISIONS           = 'revisions';
	const CATEGORY_AUTO_DRAFTS         = 'auto_drafts';
	const CATEGORY_TRASHED_POSTS       = 'trashed_posts';
	const CATEGORY_SPAM_COMMENTS       = 'spam_comments';
	const CATEGORY_TRASHED_COMMENTS    = 'trashed_comments';
	const CATEGORY_EXPIRED_TRANSIENTS  = 'expired_transients';

	const PREVIEW_LIMIT = 8;

	/**
	 * Category metadata (translated).
	 *
	 * @return array<string, array{label:string, description:string}>
	 */
	public function definitions() {
		$compat = new Lapsha_Database_Compat();
		$defs   = array(
			self::CATEGORY_REVISIONS          => array(
				'label'       => __( 'Post Revisions', 'lapsha-wp-tools' ),
				'description' => __( 'Previous versions of posts and pages. Current content is not removed.', 'lapsha-wp-tools' ),
			),
			self::CATEGORY_AUTO_DRAFTS        => array(
				'label'       => __( 'Auto-Drafts', 'lapsha-wp-tools' ),
				'description' => __( 'Automatic drafts created by the editor. Normal drafts are left alone.', 'lapsha-wp-tools' ),
			),
			self::CATEGORY_TRASHED_POSTS      => array(
				'label'       => __( 'Trashed Posts', 'lapsha-wp-tools' ),
				'description' => __( 'Posts, pages, and other types currently in the trash.', 'lapsha-wp-tools' ),
			),
			self::CATEGORY_SPAM_COMMENTS      => array(
				'label'       => __( 'Spam Comments', 'lapsha-wp-tools' ),
				'description' => __( 'Comments marked as spam. Approved comments are not included.', 'lapsha-wp-tools' ),
			),
			self::CATEGORY_TRASHED_COMMENTS   => array(
				'label'       => __( 'Trashed Comments', 'lapsha-wp-tools' ),
				'description' => __( 'Comments currently in the trash.', 'lapsha-wp-tools' ),
			),
			self::CATEGORY_EXPIRED_TRANSIENTS => array(
				'label'       => __( 'Expired Transients', 'lapsha-wp-tools' ),
				'description' => __( 'Timed-out transients in the options table. Active transients are kept.', 'lapsha-wp-tools' ),
			),
		);

		foreach ( $defs as $id => $row ) {
			$note = $compat->category_note( $id );
			if ( '' !== $note ) {
				$defs[ $id ]['description'] = $row['description'] . ' ' . $note;
			}
		}

		return $defs;
	}

	/**
	 * Full scan of every category.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function scan() {
		$results = array();
		foreach ( array_keys( $this->definitions() ) as $id ) {
			$results[ $id ] = $this->scan_category( $id );
		}
		return $results;
	}

	/**
	 * @param string $id Category id.
	 * @return array<string, mixed>
	 */
	public function scan_category( $id ) {
		$definitions = $this->definitions();
		$meta        = isset( $definitions[ $id ] ) ? $definitions[ $id ] : array(
			'label'       => $id,
			'description' => '',
		);

		return array(
			'id'          => $id,
			'label'       => $meta['label'],
			'description' => $meta['description'],
			'count'       => $this->count( $id ),
			'preview'     => $this->preview( $id, self::PREVIEW_LIMIT ),
		);
	}

	/**
	 * Scan selected categories only (used on the confirmation screen).
	 *
	 * @param string[] $ids Category ids.
	 * @return array<string, array<string, mixed>>
	 */
	public function scan_many( $ids ) {
		$results = array();
		foreach ( $ids as $id ) {
			$results[ $id ] = $this->scan_category( $id );
		}
		return $results;
	}

	/**
	 * @param string $id Category id.
	 * @return int
	 */
	public function count( $id ) {
		switch ( $id ) {
			case self::CATEGORY_REVISIONS:
				return $this->count_posts( array( 'post_type' => 'revision' ) );
			case self::CATEGORY_AUTO_DRAFTS:
				return $this->count_posts( array( 'post_status' => 'auto-draft' ) );
			case self::CATEGORY_TRASHED_POSTS:
				return $this->count_posts( array( 'post_status' => 'trash' ) );
			case self::CATEGORY_SPAM_COMMENTS:
				return $this->count_comments( 'spam' );
			case self::CATEGORY_TRASHED_COMMENTS:
				return $this->count_comments( 'trash' );
			case self::CATEGORY_EXPIRED_TRANSIENTS:
				return $this->count_expired_transients();
			default:
				return 0;
		}
	}

	/**
	 * @param string $id    Category id.
	 * @param int    $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function preview( $id, $limit = 8 ) {
		$limit = max( 1, (int) $limit );

		switch ( $id ) {
			case self::CATEGORY_REVISIONS:
				return $this->preview_posts( array( 'post_type' => 'revision' ), $limit );
			case self::CATEGORY_AUTO_DRAFTS:
				return $this->preview_posts( array( 'post_status' => 'auto-draft' ), $limit );
			case self::CATEGORY_TRASHED_POSTS:
				return $this->preview_posts( array( 'post_status' => 'trash' ), $limit, true );
			case self::CATEGORY_SPAM_COMMENTS:
				return $this->preview_comments( 'spam', $limit );
			case self::CATEGORY_TRASHED_COMMENTS:
				return $this->preview_comments( 'trash', $limit );
			case self::CATEGORY_EXPIRED_TRANSIENTS:
				return $this->preview_expired_transients( $limit );
			default:
				return array();
		}
	}

	/**
	 * @param array $where Column => value (post_type or post_status).
	 * @return int
	 */
	private function count_posts( $where ) {
		global $wpdb;

		$column = key( $where );
		$value  = $where[ $column ];
		if ( ! in_array( $column, array( 'post_type', 'post_status' ), true ) ) {
			return 0;
		}

		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$column} = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$value
		);
		$sql .= $this->sql_exclude_compat_types_for_status_column( $column );

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- suffix is a prepared IN list or empty.
	}

	/**
	 * @param string $approved Comment approved value.
	 * @return int
	 */
	private function count_comments( $approved ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = %s",
				$approved
			)
		);
	}

	/**
	 * @return int
	 */
	private function count_expired_transients() {
		global $wpdb;

		$count  = $this->count_expired_in_table( $wpdb->options, 'option_name', 'option_value', '_transient_timeout_' );
		$count += $this->count_expired_in_table( $wpdb->options, 'option_name', 'option_value', '_site_transient_timeout_' );

		if ( is_multisite() && isset( $wpdb->sitemeta ) ) {
			$count += $this->count_expired_in_table( $wpdb->sitemeta, 'meta_key', 'meta_value', '_site_transient_timeout_' );
		}

		return $count;
	}

	/**
	 * @param string $table     Table name from $wpdb.
	 * @param string $name_col  Name column.
	 * @param string $value_col Value column.
	 * @param string $prefix    Timeout option prefix.
	 * @return int
	 */
	private function count_expired_in_table( $table, $name_col, $value_col, $prefix ) {
		global $wpdb;

		$allowed_cols = array( 'option_name', 'option_value', 'meta_key', 'meta_value' );
		if ( ! in_array( $name_col, $allowed_cols, true ) || ! in_array( $value_col, $allowed_cols, true ) ) {
			return 0;
		}

		$like = $wpdb->esc_like( $prefix ) . '%';

		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE {$name_col} LIKE %s AND {$value_col} < %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$like,
			time()
		);

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * @param array $where     Column => value.
	 * @param int   $limit     Limit.
	 * @param bool  $with_type Include post_type.
	 * @return array<int, array<string, mixed>>
	 */
	private function preview_posts( $where, $limit, $with_type = false ) {
		global $wpdb;

		$column = key( $where );
		$value  = $where[ $column ];
		if ( ! in_array( $column, array( 'post_type', 'post_status' ), true ) ) {
			return array();
		}

		$fields  = 'ID, post_title, post_date, post_type';
		$exclude = $this->sql_exclude_compat_types_for_status_column( $column );
		$sql     = $wpdb->prepare(
			"SELECT {$fields} FROM {$wpdb->posts} WHERE {$column} = %s{$exclude} LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$value,
			$limit
		);

		$rows  = $wpdb->get_results( $sql );
		$items = array();
		foreach ( $rows as $row ) {
			$title   = $row->post_title;
			$items[] = array(
				'id'    => (int) $row->ID,
				'title' => '' !== $title ? $title : __( '(no title)', 'lapsha-wp-tools' ),
				'date'  => $row->post_date,
				'extra' => $with_type ? $row->post_type : '',
			);
		}

		return $items;
	}

	/**
	 * Reserve post types owned by an active compatibility integration.
	 *
	 * @param string $column posts column used in the WHERE clause.
	 * @return string
	 */
	private function sql_exclude_compat_types_for_status_column( $column ) {
		if ( 'post_status' !== $column ) {
			return '';
		}

		$compat = new Lapsha_Database_Compat();

		return $compat->sql_and_exclude_protected_types();
	}

	/**
	 * @param string $approved Approved value.
	 * @param int    $limit    Limit.
	 * @return array<int, array<string, mixed>>
	 */
	private function preview_comments( $approved, $limit ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_ID, comment_author, comment_date, comment_post_ID, comment_content
				FROM {$wpdb->comments}
				WHERE comment_approved = %s
				LIMIT %d",
				$approved,
				$limit
			)
		);

		$items = array();
		foreach ( $rows as $row ) {
			$author  = $row->comment_author;
			$items[] = array(
				'id'    => (int) $row->comment_ID,
				'title' => '' !== $author ? $author : __( '(no author)', 'lapsha-wp-tools' ),
				'date'  => $row->comment_date,
				'extra' => wp_trim_words( wp_strip_all_tags( (string) $row->comment_content ), 8, '…' ),
			);
		}

		return $items;
	}

	/**
	 * @param int $limit Limit.
	 * @return array<int, array<string, mixed>>
	 */
	private function preview_expired_transients( $limit ) {
		global $wpdb;

		$like = $wpdb->esc_like( '_transient_timeout_' ) . '%';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options}
				WHERE option_name LIKE %s AND option_value < %d
				LIMIT %d",
				$like,
				time(),
				$limit
			)
		);

		$items = array();
		foreach ( $rows as $row ) {
			$name    = preg_replace( '/^_transient_timeout_/', '', $row->option_name );
			$items[] = array(
				'id'    => 0,
				'title' => $name,
				'date'  => '',
				'extra' => '',
			);
		}

		return $items;
	}
}
