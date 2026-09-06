<?php
/**
 * Database Cleaner admin view.
 *
 * @package Lapsha_WP_Tools
 *
 * @var Lapsha_Database_Scanner $scanner
 * @var array|false             $scan
 * @var float|null              $scan_duration
 * @var array|false             $clean_result
 * @var array|false             $job
 * @var bool                    $confirm
 * @var bool                    $working
 * @var string                  $error
 * @var bool                    $cleaned
 * @var bool                    $scanned
 * @var string[]                $selected
 * @var array                   $progress_items
 * @var string                  $compat_notice
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $compat_notice ) ) {
	$compat_notice = '';
}

$definitions = $scanner->definitions();
$form_scan   = admin_url( 'admin-post.php' );
$form_clean  = admin_url( 'admin-post.php' );
$show_lead   = ! $confirm && ! $working && ! $cleaned;
?>
<h1><?php echo esc_html__( 'Database Cleaner', 'lapsha-wp-tools' ); ?></h1>
<hr class="wp-header-end" />

<?php if ( 'none_selected' === $error ) : ?>
	<div class="notice notice-error inline"><p><?php echo esc_html__( 'Select at least one category with items to clean.', 'lapsha-wp-tools' ); ?></p></div>
<?php endif; ?>

<?php if ( 'job_missing' === $error ) : ?>
	<div class="notice notice-error inline"><p><?php echo esc_html__( 'The cleanup job expired or was not found. Scan again, then confirm cleanup.', 'lapsha-wp-tools' ); ?></p></div>
<?php endif; ?>

<?php if ( $scanned && is_array( $scan ) ) : ?>
	<div class="notice notice-success inline is-dismissible">
		<p>
			<?php echo esc_html__( 'Scan complete. Review the categories below, then confirm before anything is deleted.', 'lapsha-wp-tools' ); ?>
			<?php if ( null !== $scan_duration ) : ?>
				<?php
				echo ' ' . esc_html(
					sprintf(
						/* translators: %s: seconds, e.g. 0.18 */
						__( '(completed in %s seconds)', 'lapsha-wp-tools' ),
						number_format_i18n( $scan_duration, 2 )
					)
				);
				?>
			<?php endif; ?>
		</p>
	</div>
<?php endif; ?>

<?php if ( $show_lead ) : ?>
	<p class="lapsha-lead">
		<?php echo esc_html__( 'Scan first. Cleanup permanently deletes the selected leftover data and cannot be undone from this screen.', 'lapsha-wp-tools' ); ?>
	</p>
<?php endif; ?>

<?php if ( ! $working && '' !== $compat_notice ) : ?>
	<div class="notice notice-info inline">
		<p><?php echo esc_html( $compat_notice ); ?></p>
	</div>
<?php endif; ?>

<?php if ( $cleaned && is_array( $clean_result ) ) : ?>
	<div class="notice notice-success inline">
		<p><strong><?php echo esc_html__( 'Cleanup completed.', 'lapsha-wp-tools' ); ?></strong></p>
		<ul class="lapsha-result-list">
			<?php foreach ( $clean_result as $category_id => $stats ) : ?>
				<?php
				$label = isset( $definitions[ $category_id ] ) ? $definitions[ $category_id ]['label'] : $category_id;
				$n     = isset( $stats['deleted'] ) ? (int) $stats['deleted'] : 0;
				?>
				<li>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: number of items, 2: category name */
							_n( 'Deleted %1$d item from %2$s.', 'Deleted %1$d items from %2$s.', $n, 'lapsha-wp-tools' ),
							$n,
							$label
						)
					);
					?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<?php if ( $working && is_array( $job ) ) : ?>
	<?php
	Lapsha_Admin_Progress::render(
		array(
			'items'            => $progress_items,
			'form_action'      => $form_clean,
			'action'           => Lapsha_Database_Module::ACTION_CLEAN,
			'nonce_action'     => Lapsha_Database_Module::NONCE_CLEAN,
			'nonce_field'      => 'lapsha_clean_nonce',
			'hidden_fields'    => array(
				'continue' => '1',
			),
			'status_title'     => __( 'Deleting…', 'lapsha-wp-tools' ),
			'status_text'      => __( 'Counters update after each short step. The page stays open — it does not need a full reload.', 'lapsha-wp-tools' ),
			'state_active'     => __( 'Deleting…', 'lapsha-wp-tools' ),
			/* translators: 1: completed count, 2: estimated total */
			'overall_template' => __( '%1$s of %2$s items removed', 'lapsha-wp-tools' ),
			'continue_label'   => __( 'Continue cleanup', 'lapsha-wp-tools' ),
		)
	);
	?>
<?php elseif ( $confirm && is_array( $scan ) && ! empty( $selected ) ) : ?>
	<div class="notice notice-warning inline">
		<p><strong><?php echo esc_html__( 'Are you sure?', 'lapsha-wp-tools' ); ?></strong></p>
		<p><?php echo esc_html__( 'The selected items will be permanently deleted.', 'lapsha-wp-tools' ); ?></p>
		<ul>
			<?php foreach ( $selected as $category_id ) : ?>
				<?php
				$row   = isset( $scan[ $category_id ] ) ? $scan[ $category_id ] : null;
				$label = $row ? $row['label'] : $category_id;
				$count = $row ? (int) $row['count'] : 0;
				?>
				<li>
					<?php echo esc_html( sprintf( '%s — %s', $label, number_format_i18n( $count ) ) ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="description"><?php echo esc_html__( 'Large amounts of data are removed in several short requests so the site does not time out.', 'lapsha-wp-tools' ); ?></p>
	</div>

	<form method="post" action="<?php echo esc_url( $form_clean ); ?>" class="lapsha-confirm-form">
		<?php wp_nonce_field( Lapsha_Database_Module::NONCE_CLEAN, 'lapsha_clean_nonce' ); ?>
		<input type="hidden" name="action" value="<?php echo esc_attr( Lapsha_Database_Module::ACTION_CLEAN ); ?>" />
		<input type="hidden" name="confirmed" value="1" />
		<?php foreach ( $selected as $category_id ) : ?>
			<input type="hidden" name="categories[]" value="<?php echo esc_attr( $category_id ); ?>" />
		<?php endforeach; ?>
		<p class="lapsha-actions">
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Lapsha_Database_Module::PAGE_SLUG ) ); ?>">
				<?php echo esc_html__( 'Cancel', 'lapsha-wp-tools' ); ?>
			</a>
			<button type="submit" class="button button-primary lapsha-button-danger">
				<?php echo esc_html__( 'Confirm cleanup', 'lapsha-wp-tools' ); ?>
			</button>
		</p>
	</form>
<?php else : ?>
	<form method="post" action="<?php echo esc_url( $form_scan ); ?>" class="lapsha-scan-form">
		<?php wp_nonce_field( Lapsha_Database_Module::NONCE_SCAN, 'lapsha_scan_nonce' ); ?>
		<input type="hidden" name="action" value="<?php echo esc_attr( Lapsha_Database_Module::ACTION_SCAN ); ?>" />
		<p class="lapsha-actions">
			<button type="submit" class="button button-primary">
				<?php echo esc_html__( 'Scan database', 'lapsha-wp-tools' ); ?>
			</button>
		</p>
	</form>

	<?php if ( is_array( $scan ) ) : ?>
		<form method="post" action="<?php echo esc_url( $form_clean ); ?>" class="lapsha-clean-form">
			<?php wp_nonce_field( Lapsha_Database_Module::NONCE_CLEAN, 'lapsha_clean_nonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( Lapsha_Database_Module::ACTION_CLEAN ); ?>" />

			<table class="widefat striped lapsha-table">
				<thead>
					<tr>
						<td class="check-column">
							<input type="checkbox" id="lapsha-select-all" checked />
						</td>
						<th><?php echo esc_html__( 'Category', 'lapsha-wp-tools' ); ?></th>
						<th class="lapsha-col-count"><?php echo esc_html__( 'Items', 'lapsha-wp-tools' ); ?></th>
						<th><?php echo esc_html__( 'Preview', 'lapsha-wp-tools' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $scan as $row ) : ?>
						<?php
						if ( ! is_array( $row ) || empty( $row['id'] ) ) {
							continue;
						}
						$count    = (int) $row['count'];
						$disabled = $count < 1;
						?>
						<tr>
							<th class="check-column">
								<input
									type="checkbox"
									name="categories[]"
									value="<?php echo esc_attr( $row['id'] ); ?>"
									<?php disabled( $disabled ); ?>
									<?php echo $disabled ? '' : 'checked'; ?>
								/>
							</th>
							<td>
								<strong><?php echo esc_html( $row['label'] ); ?></strong>
								<p class="description"><?php echo esc_html( $row['description'] ); ?></p>
							</td>
							<td class="lapsha-col-count">
								<span class="lapsha-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
							</td>
							<td>
								<?php if ( empty( $row['preview'] ) ) : ?>
									<span class="description"><?php echo esc_html__( 'None', 'lapsha-wp-tools' ); ?></span>
								<?php else : ?>
									<ul class="lapsha-preview">
										<?php foreach ( $row['preview'] as $item ) : ?>
											<li>
												<?php
												$line = $item['title'];
												if ( ! empty( $item['extra'] ) ) {
													$line .= ' — ' . $item['extra'];
												}
												if ( ! empty( $item['date'] ) ) {
													$line .= ' · ' . $item['date'];
												}
												echo esc_html( $line );
												?>
											</li>
										<?php endforeach; ?>
									</ul>
									<?php if ( $count > count( $row['preview'] ) ) : ?>
										<p class="description">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %s: number of remaining items */
													__( '…and %s more', 'lapsha-wp-tools' ),
													number_format_i18n( $count - count( $row['preview'] ) )
												)
											);
											?>
										</p>
									<?php endif; ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="lapsha-actions">
				<button type="submit" class="button button-primary" id="lapsha-clean-submit">
					<?php echo esc_html__( 'Clean selected', 'lapsha-wp-tools' ); ?>
				</button>
			</p>
		</form>
	<?php endif; ?>
<?php endif; ?>
