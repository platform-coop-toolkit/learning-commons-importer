<?php
/**
 * Select options template.
 *
 * @package LearningCommonsImporter
 */

$this->render_header();

// echo '<pre>' . esc_attr( print_r( $data, true ) ) . '</pre>'; // @codingStandardsIgnoreLine

?>
<div class="welcome-panel">
	<div class="welcome-panel-content">
		<h2><?php esc_html_e( 'Step 2: Import Settings', 'learning-commons-importer' ); ?></h2>
		<p><?php esc_html_e( 'Your import is almost ready to go.', 'learning-commons-importer' ); ?></p>

		<div class="welcome-panel-column-container">
			<div class="welcome-panel-column">
				<h3><?php esc_html_e( 'Import Summary', 'learning-commons-importer' ); ?></h3>
				<ul>
					<li>
						<span class="dashicons dashicons-archive"></span>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: Number of resources when there's only one resource (1). %d: Number of resources. */
								_n( '%d resource', '%d resources', $data->resource_count, 'learning-commons-importer' ),
								$data->resource_count
							)
						);
						?>
					</li>
					<li>
						<span class="dashicons dashicons-category"></span>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: Number of terms when there's only one term (1). %d: Number of terms. */
								_n( '%d topic', '%d topics', $data->term_count, 'learning-commons-importer' ),
								$data->term_count
							)
						);
						?>
					</li>
				</ul>
			</div>
		</div>
	</div>
</div>

<form action="<?php echo esc_url( $this->get_url( 2 ) ); ?>" method="post">

	<input type="hidden" name="import_id" value="<?php echo esc_attr( $this->id ); ?>" />
	<?php wp_nonce_field( sprintf( 'resource.import:%d', $this->id ) ); ?>

	<?php submit_button( __( 'Start Importing', 'learning-commons-importer' ) ); ?>

</form>

<?php

$this->render_footer();
