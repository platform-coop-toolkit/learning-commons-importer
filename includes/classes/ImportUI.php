<?php
/**
 * Importer UI class.
 *
 * @package LearningCommonsImporter
 */

namespace LearningCommonsImporter;

use LearningCommonsImporter\LoggerSSE;

/**
 * Class which handles the resource importer UI.
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
				'learning-commons-importer'
			);
			$error .= sprintf( '<p><strong>%s</strong></p></div>', $upload_dir['error'] );
			echo esc_attr( $error );
			return;
		}

		// Queue the JS needed for the page.
		$url  = LEARNING_COMMONS_IMPORTER_URL . 'dist/js/upload.js';
		$deps = [
			'wp-backbone',
			'wp-plupload',
		];
		wp_enqueue_script( 'import-upload', $url, $deps, LEARNING_COMMONS_IMPORTER_VERSION, true );

		// Set uploader settings.
		wp_plupload_default_settings();
		$settings = [
			'l10n'     => [
				'frameTitle' => esc_html__( 'Select', 'learning-commons-importer' ),
				'buttonText' => esc_html__( 'Import', 'learning-commons-importer' ),
			],
			'next_url' => wp_nonce_url( $this->get_url( 1 ), 'import-upload' ) . '&id={id}',
			'plupload' => [
				'filters'          => [
					'max_file_size' => $max_upload_size . 'b',
					'mime_types'    => [
						[
							'title'      => esc_html__( 'Excel files', 'learning-commons-importer' ),
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

		wp_enqueue_style( 'resource-import-upload', LEARNING_COMMONS_IMPORTER_URL . 'dist/css/admin-style.css', [], LEARNING_COMMONS_IMPORTER_VERSION );

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
	 * Triggers on `async-upload.php?action=resource-import-upload` to handle
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
				__( 'Invalid media item ID.', 'learning-commons-importer' ),
				compact( 'id' )
			);
		}

		$id = (int) $id;

		$attachment = get_post( $id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error(
				'resource_importer.upload.invalid_id',
				__( 'Invalid media item ID.', 'learning-commons-importer' ),
				compact( 'id', 'attachment' )
			);
		}

		if ( ! current_user_can( 'read_post', $attachment->ID ) ) {
			return new WP_Error(
				'resource_importer.upload.sorry_dave',
				__( 'You cannot access the selected media item.', 'learning-commons-importer' ),
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

		require LEARNING_COMMONS_IMPORTER_PATH . '/templates/select-options.php';
	}

	/**
	 * Render the import page.
	 */
	private function display_import_step() {
		$args = wp_unslash( $_POST );
		if ( ! isset( $args['import_id'] ) ) {
			// Missing import ID.
			$error = new \WP_Error( 'resource_importer.import.missing_id', __( 'Missing import file ID from request.', 'resource-importer' ) );
			$this->display_error( $error );
			return;
		}

		// Check the nonce.
		check_admin_referer( sprintf( 'resource.import:%d', (int) $args['import_id'] ) );

		$this->id = (int) $args['import_id'];
		$file     = get_attached_file( $this->id );

		// Set our settings
		$settings = [];
		update_post_meta( $this->id, '_resource_import_settings', $settings );

		// Time to run the import!
		set_time_limit( 0 );

		// Ensure we're not buffered.
		wp_ob_end_flush_all();
		flush();

		$data = get_post_meta( $this->id, '_resource_import_info', true );
		require LEARNING_COMMONS_IMPORTER_PATH . '/templates/import.php';
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

		return $data;
	}

	/**
	 * Run an import, and send an event-stream response.
	 *
	 * Streams logs and success messages to the browser to allow live status
	 * and updates.
	 */
	public function stream_import() {
		// Turn off PHP output compression
		$previous = error_reporting( error_reporting() ^ E_WARNING ); // @codingStandardsIgnoreLine
		ini_set( 'output_buffering', 'off' ); // @codingStandardsIgnoreLine
		ini_set( 'zlib.output_compression', false ); // @codingStandardsIgnoreLine
		error_reporting( $previous ); // @codingStandardsIgnoreLine

		if ( $GLOBALS['is_nginx'] ) {
			// Setting this header instructs Nginx to disable fastcgi_buffering
			// and disable gzip for this request.
			header( 'X-Accel-Buffering: no' );
			header( 'Content-Encoding: none' );
		}

		// Start the event stream.
		header( 'Content-Type: text/event-stream' );

		$this->id = wp_unslash( (int) $_REQUEST['id'] ); // @codingStandardsIgnoreLine
		$settings = get_post_meta( $this->id, '_resource_import_settings', true );
		if ( ! is_array( $settings ) ) {
			// Tell the browser to stop reconnecting.
			status_header( 204 );
			exit;
		}

		// 2KB padding for IE
		echo ':' . str_repeat( ' ', 2048 ) . "\n\n"; // @codingStandardsIgnoreLine

		// Time to run the import!
		set_time_limit( 0 );

		// Ensure we're not buffered.
		wp_ob_end_flush_all();
		flush();

		$importer = $this->get_importer();

		// Keep track of our progress
		add_action( 'resource_importer.processed.resource', [ $this, 'imported_resource' ], 10, 2 );
		add_action( 'resource_importer.process_failed.resource', [ $this, 'imported_resource' ], 10, 2 );
		add_action( 'resource_importer.process_already_imported.resource', [ $this, 'already_imported_resource' ], 10, 2 );
		add_action( 'resource_importer.process_skipped.resource', [ $this, 'already_imported_resource' ], 10, 2 );

		// Clean up some memory
		unset( $settings );

		// Flush once more.
		flush();

		$file = get_attached_file( $this->id );
		$err  = $importer->import( $file );

		// Remove the settings to stop future reconnects.
		delete_post_meta( $this->id, '_resource_import_settings' );

		// Let the browser know we're done.
		$complete = array(
			'action' => 'complete',
			'error'  => false,
		);
		if ( is_wp_error( $err ) ) {
			$complete['error'] = $err->get_error_message();
		}

		$this->emit_sse_message( $complete );
		exit;
	}

	/**
	 * Get the importer instance.
	 *
	 * @return Importer
	 */
	protected function get_importer() {
		$importer = new Importer();
		$logger   = new LoggerSSE();
		$importer->set_logger( $logger );

		return $importer;
	}

	/**
	 * Get options for the importer.
	 *
	 * @return array Options to pass to Importer::__construct
	 */
	protected function get_import_options() {
		$options = array(
			'fetch_attachments' => $this->fetch_attachments,
			'default_author'    => get_current_user_id(),
		);

		/**
		 * Filter the importer options used in the admin UI.
		 *
		 * @param array $options Options to pass to Importer::__construct
		 */
		return apply_filters( 'resource_importer.admin.import_options', $options ); // @codingStandardsIgnoreLine
	}

	/**
	 * Emit a Server-Sent Events message.
	 *
	 * @param mixed $data Data to be JSON-encoded and sent in the message.
	 */
	protected function emit_sse_message( $data ) {
		echo "event: message\n";
		echo 'data: ' . wp_json_encode( $data ) . "\n\n";

		// Extra padding.
		echo ':' . str_repeat( ' ', 2048 ) . "\n\n"; // @codingStandardsIgnoreLine

		flush();
	}

	/**
	 * Send message when a post has been imported.
	 *
	 * @param int   $id Post ID.
	 * @param array $data Post data saved to the DB.
	 */
	public function imported_resource( $id, $data ) {
		$this->emit_sse_message(
			[
				'action' => 'updateDelta',
				'type'   => 'posts',
				'delta'  => 1,
			]
		);
	}

	/**
	 * Send message when a post is marked as already imported.
	 *
	 * @param array $data Post data saved to the DB.
	 */
	public function already_imported_resource( $data ) {
		$this->emit_sse_message(
			[
				'action' => 'updateDelta',
				'type'   => 'posts',
				'delta'  => 1,
			]
		);
	}
}
