<?php
/**
 * Upload area template.
 *
 * @package LearningCommonsImporter
 */

?>
<div id="plupload-upload-ui" class="hide-if-no-js">
	<?php
	/**
	 * Fires before the upload interface loads.
	 *
	 * @since 2.6.0 As 'pre-flash-upload-ui'
	 * @since 3.3.0
	 */

	do_action( 'pre-plupload-upload-ui' ); // @codingStandardsIgnoreLine
	?>

	<div id="drag-drop-area">
		<div class="drag-drop-inside drag-drop-selector">
			<p class="drag-drop-info"><?php esc_html_e( 'Drop files here', 'learning-commons-importer' ); ?></p>
			<p><?php echo esc_html_x( 'or', 'Uploader: Drop files here - or - Select Files', 'learning-commons-importer' ); ?></p>
			<p class="drag-drop-buttons"><input id="plupload-browse-button" type="button" value="<?php esc_attr_e( 'Select Files', 'learning-commons-importer' ); ?>" class="button" /></p>
		</div>
		<div class="drag-drop-inside drag-drop-status"></div>
	</div>

	<?php
	/**
	 * Fires after the upload interface loads.
	 *
	 * @since 2.6.0 As 'post-flash-upload-ui'
	 * @since 3.3.0
	 */

	do_action( 'post-plupload-upload-ui' ); // @codingStandardsIgnoreLine
	?>
</div>

<div id="html-upload-ui" class="hide-if-js">
	<?php
	/**
	 * Fires before the upload button in the media upload interface.
	 *
	 * @since 2.6.0
	 */
	do_action( 'pre-html-upload-ui' ); // @codingStandardsIgnoreLine
	?>
	<p id="async-upload-wrap">
		<label class="screen-reader-text" for="async-upload"><?php esc_html_e( 'Upload', 'learning-commons-importer' ); ?></label>
		<input type="file" name="async-upload" id="async-upload" />
		<?php submit_button( __( 'Upload', 'learning-commons-importer' ), 'primary', 'html-upload', false ); ?>
		<a href="#" onclick="try{top.tb_remove();}catch(e){}; return false;"><?php esc_html_e( 'Cancel', 'learning-commons-importer' ); ?></a>
	</p>

	<div class="clear"></div>
	<?php
	/**
	 * Fires after the upload button in the media upload interface.
	 *
	 * @since 2.6.0
	 */
	do_action( 'post-html-upload-ui' ); // @codingStandardsIgnoreLine
	?>
</div>

<p class="max-upload-size">
<?php
printf(
	/* translators: %s: The maximum upload file size. */
	esc_attr__( 'Maximum upload file size: %s.', 'learning-commons-importer' ),
	esc_html( size_format( $max_upload_size ) )
)
?>
</p>

<script type="text/html" id="tmpl-import-upload-status">
	<# if ( data.uploading ) { #>
		<p>
		<?php
		echo wp_kses(
			sprintf(
				/* translators: %s: The file name of the file being uploaded. */
				__( 'Uploading %s&#8230;', 'learning-commons-importer' ),
				'<code>{{ data.filename }}</code>'
			),
			'data'
		)
		?>
		</p>

		<div class="media-item">
			<div class="progress">
				<div class="percent">0%</div>
				<div class="bar"></div>
			</div>
		</div>

	<# } else { #>

		<p><?php esc_html_e( 'Success! Your import file is ready, let&#8217;s get started.', 'learning-commons-importer' ); ?></p>
		<p><button type="submit" class="button"><?php esc_html_e( 'Start Import', 'learning-commons-importer' ); ?></button></p>

	<# } #>
</script>

<script type="text/html" id="tmpl-import-upload-error">
	<p>
	<?php
	printf(
		/* translators: %s: File upload error message.*/
		esc_html__( 'Whoops, could not upload file: %s', 'learning-commons-importer' ),
		'{{ data.message }}'
	)
	?>
	</p>
	<p><button type="button" class="button"><?php esc_html_e( 'Try Again', 'learning-commons-importer' ); ?></button></p>
</script>
