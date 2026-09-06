<?php
/**
 * Dashboard view.
 *
 * @package Lapsha_WP_Tools
 *
 * @var Lapsha_Module[] $modules
 */

defined( 'ABSPATH' ) || exit;
?>
<h1><?php echo esc_html__( 'Lapsha WP Tools', 'lapsha-wp-tools' ); ?></h1>
<p class="lapsha-lead">
	<?php echo esc_html__( 'Free administration tools for WordPress. Nothing is deleted unless you scan, select, and confirm.', 'lapsha-wp-tools' ); ?>
</p>

<div class="lapsha-cards">
	<?php foreach ( $modules as $module ) : ?>
		<section class="lapsha-card">
			<h2><?php echo esc_html( $module->name() ); ?></h2>
			<p><?php echo esc_html( $module->description() ); ?></p>
			<?php if ( 'database' === $module->id() ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=lapsha-database-cleaner' ) ); ?>">
					<?php echo esc_html__( 'Open Database Cleaner', 'lapsha-wp-tools' ); ?>
				</a>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>
</div>
