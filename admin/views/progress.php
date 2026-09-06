<?php
/**
 * Shared admin progress panel.
 *
 * @package Lapsha_WP_Tools
 *
 * @var string $id
 * @var string $form_id
 * @var string $status_title
 * @var string $status_text
 * @var string $state_active
 * @var string $state_done
 * @var string $overall_template
 * @var string $continue_label
 * @var string $continue_hint
 * @var string $form_action
 * @var string $action
 * @var string $nonce_action
 * @var string $nonce_field
 * @var array  $hidden_fields
 * @var array  $rows
 * @var int    $overall_current
 * @var int    $overall_total
 * @var int    $overall_percent
 * @var string $current_id
 */

defined( 'ABSPATH' ) || exit;
?>
<div
	class="lapsha-progress-panel"
	id="<?php echo esc_attr( $id ); ?>"
	data-lapsha-progress="1"
	data-form="<?php echo esc_attr( $form_id ); ?>"
	data-overall-template="<?php echo esc_attr( $overall_template ); ?>"
	data-state-active="<?php echo esc_attr( $state_active ); ?>"
	data-state-done="<?php echo esc_attr( $state_done ); ?>"
>
	<div class="notice notice-info inline">
		<p class="lapsha-status">
			<span class="lapsha-spinner" aria-hidden="true"></span>
			<span class="lapsha-status-text">
				<strong><?php echo esc_html( $status_title ); ?></strong>
				<?php if ( '' !== $status_text ) : ?>
					<?php echo esc_html( $status_text ); ?>
				<?php endif; ?>
			</span>
		</p>
	</div>

	<div class="lapsha-overall" aria-live="polite">
		<div class="lapsha-overall__label">
			<span data-role="overall-label">
				<?php
				echo esc_html(
					sprintf(
						$overall_template,
						number_format_i18n( $overall_current ),
						number_format_i18n( $overall_total )
					)
				);
				?>
			</span>
			<strong data-role="overall-percent"><?php echo esc_html( (string) $overall_percent ); ?>%</strong>
		</div>
		<div
			class="lapsha-progress lapsha-progress--lg"
			role="progressbar"
			aria-valuemin="0"
			aria-valuemax="100"
			aria-valuenow="<?php echo esc_attr( (string) $overall_percent ); ?>"
			data-role="overall-bar-wrap"
		>
			<div class="lapsha-progress__bar is-active" data-role="overall-bar" style="width: <?php echo esc_attr( (string) $overall_percent ); ?>%;"></div>
		</div>
	</div>

	<div class="lapsha-progress-list">
		<?php foreach ( $rows as $row ) : ?>
			<?php
			$active = ( $row['id'] === $current_id );
			$state  = $row['complete'] ? $state_done : ( $active ? $state_active : '' );
			?>
			<div
				class="lapsha-progress-row<?php echo $row['complete'] ? ' is-done' : ''; ?><?php echo $active ? ' is-active' : ''; ?>"
				data-progress-item="<?php echo esc_attr( $row['id'] ); ?>"
			>
				<div class="lapsha-progress-row__head">
					<strong><?php echo esc_html( $row['label'] ); ?></strong>
					<span class="lapsha-progress-row__meta">
						<span class="lapsha-progress-row__counts">
							<span data-role="current"><?php echo esc_html( number_format_i18n( $row['current'] ) ); ?></span>
							/
							<span data-role="total"><?php echo esc_html( number_format_i18n( $row['total'] ) ); ?></span>
						</span>
						<span data-role="state"><?php echo esc_html( $state ); ?></span>
					</span>
				</div>
				<div class="lapsha-progress">
					<div class="lapsha-progress__bar<?php echo $row['complete'] ? ' is-done' : ( $active ? ' is-active' : '' ); ?>" data-role="bar" style="width: <?php echo esc_attr( (string) $row['percent'] ); ?>%;"></div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<?php if ( '' !== $action && '' !== $nonce_action ) : ?>
	<form method="post" action="<?php echo esc_url( $form_action ); ?>" id="<?php echo esc_attr( $form_id ); ?>" class="lapsha-continue-form">
		<?php wp_nonce_field( $nonce_action, $nonce_field ); ?>
		<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" />
		<?php foreach ( $hidden_fields as $field_name => $field_value ) : ?>
			<input type="hidden" name="<?php echo esc_attr( (string) $field_name ); ?>" value="<?php echo esc_attr( (string) $field_value ); ?>" />
		<?php endforeach; ?>
		<p class="lapsha-actions">
			<button type="submit" class="button button-primary">
				<?php echo esc_html( $continue_label ); ?>
			</button>
			<?php if ( '' !== $continue_hint ) : ?>
				<span class="description"><?php echo esc_html( $continue_hint ); ?></span>
			<?php endif; ?>
		</p>
	</form>
<?php endif; ?>
