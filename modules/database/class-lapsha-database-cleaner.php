<?php
/**
 * Destructive cleanup operations.
 *
 * Work is time-boxed so a single HTTP request stays under cheap-hosting
 * PHP limits. Callers continue the job across redirects until complete.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Deletes allowlisted leftover data. Re-queries the database; does not trust scan IDs.
 */
class Lapsha_Database_Cleaner {

	const POST_BATCH      = 25;
	const TRANSIENT_BATCH = 80;

	/**
	 * Delete as much as the time budget allows.
	 *
	 * @param string[] $categories Allowlisted category ids, in order.
	 * @param array    $results    Accumulated results keyed by category.
	 * @param float    $deadline   microtime(true) deadline.
	 * @return array{results:array, pending:string[], complete:bool}
	 */
	public function run_until_budget( $categories, $results, $deadline ) {
		$categories = lapsha_wp_tools_sanitize_category_ids( $categories );

		foreach ( $categories as $category ) {
			if ( ! $this->has_time( $deadline ) ) {
				break;
			}

			if ( ! isset( $results[ $category ] ) ) {
				$results[ $category ] = array(
					'deleted'  => 0,
					'skipped'  => 0,
					'complete' => false,
				);
			}

			if ( ! empty( $results[ $category ]['complete'] ) ) {
				continue;
			}

			$chunk = $this->clean_category_chunk( $category, $deadline );

			$results[ $category ]['deleted'] += $chunk['deleted'];
			$results[ $category ]['skipped'] += $chunk['skipped'];
			$results[ $category ]['complete'] = $chunk['complete'];
		}

		$pending = array();
		foreach ( $categories as $category ) {
			if ( empty( $results[ $category ]['complete'] ) ) {
				$pending[] = $category;
			}
		}

		return array(
			'results'  => $results,
			'pending'  => $pending,
			'complete' => empty( $pending ),
		);
	}

	/**
	 * @param string $category Category id.
	 * @param float  $deadline Deadline.
	 * @return array{deleted:int, skipped:int, complete:bool}
	 */
	private function clean_category_chunk( $category, $deadline ) {
		switch ( $category ) {
			case Lapsha_Database_Scanner::CATEGORY_REVISIONS:
				return $this->delete_revisions( $deadline );
			case Lapsha_Database_Scanner::CATEGORY_AUTO_DRAFTS:
				return $this->delete_posts_with_status( 'auto-draft', $deadline );
			case Lapsha_Database_Scanner::CATEGORY_TRASHED_POSTS:
				return $this->delete_posts_with_status( 'trash', $deadline );
			case Lapsha_Database_Scanner::CATEGORY_SPAM_COMMENTS:
				return $this->delete_comments_with_approved( 'spam', $deadline );
			case Lapsha_Database_Scanner::CATEGORY_TRASHED_COMMENTS:
				return $this->delete_comments_with_approved( 'trash', $deadline );
			case Lapsha_Database_Scanner::CATEGORY_EXPIRED_TRANSIENTS:
				return $this->delete_expired_transients( $deadline );
			default:
				return array(
					'deleted'  => 0,
					'skipped'  => 0,
					'complete' => true,
				);
		}
	}

	/**
	 * @param float $deadline Deadline.
	 * @return bool
	 */
	private function has_time( $deadline ) {
		return microtime( true ) < $deadline;
	}

	/**
	 * @param float $deadline Deadline.
	 * @return array{deleted:int, skipped:int, complete:bool}
	 */
	private function delete_revisions( $deadline ) {
		global $wpdb;

		$deleted = 0;
		$skipped = 0;

		while ( $this->has_time( $deadline ) ) {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s LIMIT %d",
					'revision',
					self::POST_BATCH
				)
			);

			if ( empty( $ids ) ) {
				return array(
					'deleted'  => $deleted,
					'skipped'  => $skipped,
					'complete' => true,
				);
			}

			$batch_deleted = 0;
			foreach ( $ids as $id ) {
				if ( ! $this->has_time( $deadline ) ) {
					return array(
						'deleted'  => $deleted,
						'skipped'  => $skipped,
						'complete' => false,
					);
				}

				$result = wp_delete_post_revision( (int) $id );
				if ( $result ) {
					++$deleted;
					++$batch_deleted;
				} else {
					++$skipped;
				}
			}

			if ( 0 === $batch_deleted ) {
				return array(
					'deleted'  => $deleted,
					'skipped'  => $skipped,
					'complete' => true,
				);
			}
		}

		return array(
			'deleted'  => $deleted,
			'skipped'  => $skipped,
			'complete' => false,
		);
	}

	/**
	 * @param string $status   Post status.
	 * @param float  $deadline Deadline.
	 * @return array{deleted:int, skipped:int, complete:bool}
	 */
	private function delete_posts_with_status( $status, $deadline ) {
		global $wpdb;

		if ( ! in_array( $status, array( 'auto-draft', 'trash' ), true ) ) {
			return array(
				'deleted'  => 0,
				'skipped'  => 0,
				'complete' => true,
			);
		}

		$deleted = 0;
		$skipped = 0;

		$exclude = ( new Lapsha_Database_Compat() )->sql_and_exclude_protected_types();

		while ( $this->has_time( $deadline ) ) {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_status = %s{$exclude} LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $exclude is a prepared IN list or empty.
					$status,
					self::POST_BATCH
				)
			);

			if ( empty( $ids ) ) {
				return array(
					'deleted'  => $deleted,
					'skipped'  => $skipped,
					'complete' => true,
				);
			}

			$batch_deleted = 0;
			foreach ( $ids as $id ) {
				if ( ! $this->has_time( $deadline ) ) {
					return array(
						'deleted'  => $deleted,
						'skipped'  => $skipped,
						'complete' => false,
					);
				}

				$post = get_post( (int) $id );
				if ( ! $post || $status !== $post->post_status ) {
					++$skipped;
					continue;
				}

				$result = wp_delete_post( (int) $id, true );
				if ( $result ) {
					++$deleted;
					++$batch_deleted;
				} else {
					++$skipped;
				}
			}

			if ( 0 === $batch_deleted ) {
				return array(
					'deleted'  => $deleted,
					'skipped'  => $skipped,
					'complete' => true,
				);
			}
		}

		return array(
			'deleted'  => $deleted,
			'skipped'  => $skipped,
			'complete' => false,
		);
	}

	/**
	 * @param string $approved Comment approved value.
	 * @param float  $deadline Deadline.
	 * @return array{deleted:int, skipped:int, complete:bool}
	 */
	private function delete_comments_with_approved( $approved, $deadline ) {
		global $wpdb;

		if ( ! in_array( $approved, array( 'spam', 'trash' ), true ) ) {
			return array(
				'deleted'  => 0,
				'skipped'  => 0,
				'complete' => true,
			);
		}

		$deleted = 0;
		$skipped = 0;

		while ( $this->has_time( $deadline ) ) {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = %s LIMIT %d",
					$approved,
					self::POST_BATCH
				)
			);

			if ( empty( $ids ) ) {
				return array(
					'deleted'  => $deleted,
					'skipped'  => $skipped,
					'complete' => true,
				);
			}

			$batch_deleted = 0;
			foreach ( $ids as $id ) {
				if ( ! $this->has_time( $deadline ) ) {
					return array(
						'deleted'  => $deleted,
						'skipped'  => $skipped,
						'complete' => false,
					);
				}

				$comment = get_comment( (int) $id );
				if ( ! $comment || $approved !== $comment->comment_approved ) {
					++$skipped;
					continue;
				}

				$result = wp_delete_comment( (int) $id, true );
				if ( $result ) {
					++$deleted;
					++$batch_deleted;
				} else {
					++$skipped;
				}
			}

			if ( 0 === $batch_deleted ) {
				return array(
					'deleted'  => $deleted,
					'skipped'  => $skipped,
					'complete' => true,
				);
			}
		}

		return array(
			'deleted'  => $deleted,
			'skipped'  => $skipped,
			'complete' => false,
		);
	}

	/**
	 * Batch expired transients. Core's delete_expired_transients() can wipe
	 * a huge options table in one query and time out on cheap hosting.
	 *
	 * @param float $deadline Deadline.
	 * @return array{deleted:int, skipped:int, complete:bool}
	 */
	private function delete_expired_transients( $deadline ) {
		$deleted = 0;
		$skipped = 0;

		while ( $this->has_time( $deadline ) ) {
			$names = $this->expired_transient_timeout_names( self::TRANSIENT_BATCH );
			if ( empty( $names ) ) {
				return array(
					'deleted'  => $deleted,
					'skipped'  => $skipped,
					'complete' => true,
				);
			}

			$batch_deleted = 0;
			foreach ( $names as $timeout_name ) {
				if ( ! $this->has_time( $deadline ) ) {
					return array(
						'deleted'  => $deleted,
						'skipped'  => $skipped,
						'complete' => false,
					);
				}

				$key = $this->transient_key_from_timeout_name( $timeout_name );
				if ( '' === $key['key'] ) {
					++$skipped;
					continue;
				}

				if ( $key['site'] ) {
					delete_site_transient( $key['key'] );
				} else {
					delete_transient( $key['key'] );
				}

				++$deleted;
				++$batch_deleted;
			}

			if ( 0 === $batch_deleted ) {
				return array(
					'deleted'  => $deleted,
					'skipped'  => $skipped,
					'complete' => true,
				);
			}
		}

		return array(
			'deleted'  => $deleted,
			'skipped'  => $skipped,
			'complete' => false,
		);
	}

	/**
	 * @param int $limit Limit.
	 * @return string[]
	 */
	private function expired_transient_timeout_names( $limit ) {
		global $wpdb;

		$now = time();
		$like = $wpdb->esc_like( '_transient_timeout_' ) . '%';
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d LIMIT %d",
				$like,
				$now,
				$limit
			)
		);

		if ( ! empty( $names ) ) {
			return $names;
		}

		$like = $wpdb->esc_like( '_site_transient_timeout_' ) . '%';
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d LIMIT %d",
				$like,
				$now,
				$limit
			)
		);

		if ( ! empty( $names ) || ! is_multisite() || empty( $wpdb->sitemeta ) ) {
			return $names;
		}

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s AND meta_value < %d LIMIT %d",
				$like,
				$now,
				$limit
			)
		);
	}

	/**
	 * @param string $timeout_name Timeout option / meta key.
	 * @return array{key:string, site:bool}
	 */
	private function transient_key_from_timeout_name( $timeout_name ) {
		if ( 0 === strpos( $timeout_name, '_site_transient_timeout_' ) ) {
			return array(
				'key'  => substr( $timeout_name, strlen( '_site_transient_timeout_' ) ),
				'site' => true,
			);
		}

		if ( 0 === strpos( $timeout_name, '_transient_timeout_' ) ) {
			return array(
				'key'  => substr( $timeout_name, strlen( '_transient_timeout_' ) ),
				'site' => false,
			);
		}

		return array(
			'key'  => '',
			'site' => false,
		);
	}
}
