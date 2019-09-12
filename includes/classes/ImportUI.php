<?php
/**
 * Importer functionality.
 *
 * @package LearningCommonsImporter
 */

namespace LearningCommonsImporter;

/**
 * Class which handles the resource import module.
 */
class ImportUI {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_action_resource-import-upload', [ $this, 'handle_async_upload' ] );
		add_filter( 'upload_mimes', [ $this, 'add_mime_type_xls' ] );
	}

	/**
	 * Add .xls files as supported format in the uploader.
	 *
	 * @param array $mimes Already supported mime types.
	 *
	 * @return array
	 */
	public function add_mime_type_xls( $mimes ) {
		$mimes = array_merge( $mimes, [ 'xls' => 'application/vnd.ms-excel' ] );

		return $mimes;
	}

	/**
	 * Get the URL for the importer.
	 *
	 * @param int $step Go to step rather than start.
	 */
	protected function get_url( $step = 0 ) {
		$path = 'admin.php?import=learning-commons-resources';
		if ( $step ) {
			$path = add_query_arg( 'step', (int) $step, $path );
		}
		return admin_url( $path );
	}

	/**
	 * Handle errors during import process.
	 *
	 * @param \WP_Error $err The error that has been encountered.
	 * @param int       $step An integer representing the step we're on.
	 */
	protected function display_error( \WP_Error $err, $step = 0 ) {
		$this->render_header();

		echo '<p><strong>' . esc_attr__( 'Sorry, there has been an error.', 'learning-commons-importer' ) . '</strong><br />';
		echo esc_attr( $err->get_error_message() );
		echo '</p>';
		printf(
			'<p><a class="button" href="%1$s">%2$s</a></p>',
			esc_url( $this->get_url( $step ) ),
			esc_attr__( 'Try Again', 'learning-commons-importer' )
		);

		$this->render_footer();
	}

	/**
	 * Handle load event for the importer.
	 */
	public function on_load() {
		// Skip outputting the header on our import page, so we can handle it.
		$_GET['noheader'] = true;
	}

	/**
	 * Render the import page.
	 */
	public function dispatch() {
		$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step']; // @codingStandardsIgnoreLine

		switch ( $step ) {
			case 0:
				$this->display_intro_step();
				break;
			case 1:
				$this->display_preparation_step();
				break;
			case 2:
				$this->display_import_step();
				break;
		}
	}

	/**
	 * Render the importer header.
	 */
	protected function render_header() {
		require LEARNING_COMMONS_IMPORTER_PATH . '/templates/header.php';
	}

	/**
	 * Render the importer footer.
	 */
	protected function render_footer() {
		require LEARNING_COMMONS_IMPORTER_PATH . '/templates/footer.php';
	}

	/**
	 * Render the intro page.
	 */
	private function display_intro_step() {
		require LEARNING_COMMONS_IMPORTER_PATH . '/templates/intro.php';
	}

	/**
	 * Render the upload form.
	 */
	protected function render_upload_form() {
		/**
		 * Filter the maximum allowed upload size for import files.
		 *
		 * @since 2.3.0
		 *
		 * @see wp_max_upload_size()
		 *
		 * @param int $max_upload_size Allowed upload size. Default 1 MB.
		 */
		$max_upload_size = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
		$upload_dir      = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			$error  = '<div class="error inline"><p>';
			$error .= esc_html__(
				'Before you can upload your import file, you will need to fix the following error:',
				'wordpress-importer'
			);
			$error .= sprintf( '<p><strong>%s</strong></p></div>', $upload_dir['error'] );
			echo esc_attr( $error );
			return;
		}

		// Queue the JS needed for the page.
		$url  = LEARNING_COMMONS_IMPORTER_URL . 'dist/js/admin.js';
		$deps = [
			'wp-backbone',
			'wp-plupload',
		];
		wp_enqueue_script( 'import-upload', $url, $deps, LEARNING_COMMONS_IMPORTER_VERSION, true );

		// Set uploader settings.
		wp_plupload_default_settings();
		$settings = [
			'l10n'     => [
				'frameTitle' => esc_html__( 'Select', 'wordpress-importer' ),
				'buttonText' => esc_html__( 'Import', 'wordpress-importer' ),
			],
			'next_url' => wp_nonce_url( $this->get_url( 1 ), 'import-upload' ) . '&id={id}',
			'plupload' => [
				'filters'          => [
					'max_file_size' => $max_upload_size . 'b',
					'mime_types'    => [
						[
							'title'      => esc_html__( 'Excel files', 'wordpress-importer' ),
							'extensions' => 'xls',
						],
					],
				],

				'file_data_name'   => 'import',
				'multipart_params' => [
					'action'   => 'resource-import-upload',
					'_wpnonce' => wp_create_nonce( 'resource-import-upload' ),
				],
			],
		];
		wp_localize_script( 'import-upload', 'importUploadSettings', $settings );

		wp_enqueue_style( 'wxr-import-upload', LEARNING_COMMONS_IMPORTER_URL . 'dist/css/admin-style.css', [], LEARNING_COMMONS_IMPORTER_VERSION );

		// Load the template
		remove_action( 'post-plupload-upload-ui', 'media_upload_flash_bypass' );
		require LEARNING_COMMONS_IMPORTER_PATH . '/templates/upload.php';
		add_action( 'post-plupload-upload-ui', 'media_upload_flash_bypass' );
	}

	/**
	 * Handles the resource upload.
	 *
	 * @return bool|\WP_Error True on success, error object otherwise.
	 */
	protected function handle_upload() {
		$file = wp_import_handle_upload();

		if ( isset( $file['error'] ) ) {
			return new \WP_Error( 'resource_importer.upload.error', esc_html( $file['error'] ), $file );
		} elseif ( ! file_exists( $file['file'] ) ) {
			$message = sprintf(
				/* translators: %s: Path to uploaded file. */
				esc_html__( 'The Excel file could not be found at %s. It is likely that this was caused by a permissions problem.', 'learning-commons-importer' ),
				'<code>' . esc_html( $file['file'] ) . '</code>'
			);
			return new \WP_Error( 'resource_importer.upload.no_file', $message, $file );
		}

		$this->id = (int) $file['id'];
		return true;
	}

	/**
	 * Handle an async upload.
	 *
	 * Triggers on `async-upload.php?action=wxr-import-upload` to handle
	 * Plupload requests from the importer.
	 */
	public function handle_async_upload() {
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		send_nosniff_header();
		nocache_headers();

		check_ajax_referer( 'resource-import-upload' );

		/*
			* This function does not use wp_send_json_success() / wp_send_json_error()
			* as the html4 Plupload handler requires a text/html content-type for older IE.
			* See https://core.trac.wordpress.org/ticket/31037
			*/

		$filename = wp_unslash( $_FILES['import']['name'] );
		$filename = sanitize_file_name( $filename );

		if ( ! current_user_can( 'upload_files' ) ) {
			echo wp_json_encode(
				[
					'success' => false,
					'data'    => [
						'message'  => __( 'You do not have permission to upload files.' ),
						'filename' => $filename,
					],
				]
			);

			exit;
		}

		$file = wp_import_handle_upload();
		if ( is_wp_error( $file ) ) {
			echo wp_json_encode(
				[
					'success' => false,
					'data'    => [
						'message'  => $file->get_error_message(),
						'filename' => $filename,
					],
				]
			);

			wp_die();
		}

		$attachment = wp_prepare_attachment_for_js( $file['id'] );
		if ( ! $attachment ) {
			exit;
		}

		echo wp_json_encode(
			[
				'success' => true,
				'data'    => $attachment,
			]
		);

		exit;
	}

	/**
	 * Handle an Excel file selected from the media browser.
	 *
	 * @param int|string $id Media item to import from.
	 * @return bool|WP_Error True on success, error object otherwise.
	 */
	protected function handle_select( $id ) {
		if ( ! is_numeric( $id ) || intval( $id ) < 1 ) {
			return new WP_Error(
				'resource_importer.upload.invalid_id',
				__( 'Invalid media item ID.', 'wordpress-importer' ),
				compact( 'id' )
			);
		}

		$id = (int) $id;

		$attachment = get_post( $id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error(
				'resource_importer.upload.invalid_id',
				__( 'Invalid media item ID.', 'wordpress-importer' ),
				compact( 'id', 'attachment' )
			);
		}

		if ( ! current_user_can( 'read_post', $attachment->ID ) ) {
			return new WP_Error(
				'resource_importer.upload.sorry_dave',
				__( 'You cannot access the selected media item.', 'wordpress-importer' ),
				compact( 'id', 'attachment' )
			);
		}

		$this->id = $id;
		return true;
	}

	/**
	 * Render the import preparation page.
	 */
	private function display_preparation_step() {
		if ( isset( $_REQUEST['id'] ) ) { // @codingStandardsIgnoreLine
			$err = $this->handle_select( wp_unslash( $_REQUEST['id'] ) ); // @codingStandardsIgnoreLine
		} else {
			$err = $this->handle_upload();
		}
		if ( is_wp_error( $err ) ) {
			$this->display_error( $err );
			return;
		}

		$data = $this->get_data_for_attachment( $this->id );
		if ( is_wp_error( $data ) ) {
			$this->display_error( $data );
			return;
		}
		echo '<p>[Prepare.]</p>';
	}

	/**
	 * Render the import page.
	 */
	private function display_import_step() {
		echo '<p>Import page.</p>';
	}

	/**
	 * Get preliminary data for an import file.
	 *
	 * This is a quick pre-parse to verify the file and grab authors from it.
	 *
	 * @param int $id Media item ID.
	 * @return \Resource_Import_Info|\WP_Error Import info instance on success, error otherwise.
	 */
	protected function get_data_for_attachment( $id ) {
		$existing = get_post_meta( $id, '_resource_import_info' );
		if ( ! empty( $existing ) ) {
			$data          = $existing[0];
			$this->authors = $data->users;
			$this->version = $data->version;
			return $data;
		}

		$file = get_attached_file( $id );

		$importer = $this->get_importer();
		$data     = $importer->get_preliminary_information( $file );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Cache the information on the upload
		if ( ! update_post_meta( $id, '_resource_import_info', $data ) ) {
			return new \WP_Error(
				'resource_importer.upload.failed_save_meta',
				__( 'Could not cache information on the import.', 'learning-commons-importer' ),
				compact( 'id' )
			);
		}

		$this->authors = $data->users;
		$this->version = $data->version;

		return $data;
	}
}
