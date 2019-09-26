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
				</ul>
			</div>
		</div>
	</div>
</div>

<form action="<?php echo esc_url( $this->get_url( 2 ) ); ?>" method="post">

	<?php
	/* @codingStandardsIgnoreStart
	 if ( ! empty( $data->users ) ) : ?>

		<h3><?php esc_html_e( 'Assign Authors', 'learning-commons-importer' ); ?></h3>
		<p>
		<?php
			echo wp_kses(
				__( 'To make it easier for you to edit and save the imported content, you may want to reassign the author of the imported item to an existing user of this site. For example, you may want to import all the entries as <code>admin</code>s entries.', 'learning-commons-importer' ),
				'data'
			);
		?>
		</p>

		<?php if ( $this->allow_create_users() ) : ?>

			<p><?php printf( esc_html__( 'If a new user is created by WordPress, a new password will be randomly generated and the new user&#8217;s role will be set as %s. Manually changing the new user&#8217;s details will be necessary.', 'learning-commons-importer' ), esc_html( get_option( 'default_role' ) ) ); ?></p>

		<?php endif; ?>

		<ol id="authors">

			<?php foreach ( $data->users as $index => $users ) : ?>

				<li><?php $this->author_select( $index, $users['data'] ); ?></li>

			<?php endforeach ?>

		</ol>

	<?php endif; ?>

	<?php if ( $this->allow_fetch_attachments() ) : ?>

		<h3><?php esc_html_e( 'Import Attachments', 'learning-commons-importer' ); ?></h3>
		<p>
			<input type="checkbox" value="1" name="fetch_attachments" id="import-attachments" />
			<label for="import-attachments">
			<?php
				esc_html_e( 'Download and import file attachments', 'learning-commons-importer' )
			?>
				</label>
		</p>

	<?php endif; @codingStandardsIgnoreEnd */
	?>

	<input type="hidden" name="import_id" value="<?php echo esc_attr( $this->id ); ?>" />
	<?php wp_nonce_field( sprintf( 'resource.import:%d', $this->id ) ); ?>

	<?php submit_button( __( 'Start Importing', 'learning-commons-importer' ) ); ?>

</form>

<?php

$this->render_footer();
