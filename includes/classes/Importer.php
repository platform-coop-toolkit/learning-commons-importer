<?php
/**
 * Importer class.
 *
 * @package LearningCommonsImporter
 */

namespace LearningCommonsImporter;

use \PhpOffice\PhpSpreadsheet\Reader\Xls;
use \PhpOffice\PhpSpreadsheet\Shared\Date;
use \PhpOffice\PhpSpreadsheet\Worksheet\RowIterator;

/**
 * Class which handles the resource importer.
 */
class Importer {


	/**
	 * An array of column headings from the import source spreadsheet.
	 *
	 * @var array $headings An array of column headings.
	 */
	protected $headings = [];

	/**
	 * Mapping from resources/terms to imported resource/term IDs.
	 *
	 * @var array $mapping An array where the key is an md5 hash of the resource title or term slug and the value is the resource or term ID.
	 */
	protected $mapping = [];

	/**
	 * An array of posts.
	 *
	 * @var array $exists An array of posts which exist (GUID => ID).
	 */
	protected $exists = [];

	/**
	 * Logger instance.
	 *
	 * @var LearningCommonsImporter\Logger
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param array $options Options for constructor
	 */
	public function __construct( $options = [] ) {
		$empty_types   = [
			'resource' => [],
			'term'     => [],
		];
		$this->mapping = $empty_types;
		$this->exists  = $empty_types;
		$this->options = wp_parse_args(
			$options,
			[
				'prefill_existing_posts' => true,
			]
		);
	}

	/**
	 * Define the logger.
	 *
	 * @param LearningCommonsImporter\Logger $logger The logger.
	 */
	public function set_logger( $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Load a spreadsheet object from the file.
	 *
	 * @param string $file Path to the Excel file.
	 * @return \PHPOffice\PHPSpreadsheet\Spreadsheet|WP_Error Spreadsheet instance on success, error otherwise.
	 */
	protected function load_spreadsheet( $file ) {
		$reader      = new Xls();
		$spreadsheet = $reader->load( $file );
		return $spreadsheet;
	}

	/**
	 * The main controller for the actual import stage.
	 *
	 * @param string $file Path to the Excel file for importing
	 *
	 * @return ImportInfo $data Information about the current import.
	 */
	public function get_preliminary_information( $file ) {
		$spreadsheet = $this->load_spreadsheet( $file );
		$data        = new ImportInfo();
		foreach ( $spreadsheet->getActiveSheet()->getRowIterator() as $row ) {
			if ( 1 < $row->getRowIndex() ) {
				$data->resource_count++;
			}
		}

		return $data;
	}

	/**
	 * The main controller for the actual import stage.
	 *
	 * @param string $file Path to the Excell file for importing.
	 *
	 * @return void|\WP_Error Returns WP_Error if there's a problem.
	 */
	public function import( $file ) {
		// Verify that the file exists, prepare for import.
		$result = $this->import_start( $file );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Load the file into a spreadsheet object.
		$spreadsheet = $this->load_spreadsheet( $file );
		if ( is_wp_error( $spreadsheet ) ) {
			return $spreadsheet;
		}

		// Start processing the spreadsheet object.
		foreach ( $spreadsheet->getActiveSheet()->getRowIterator() as $row ) {
			if ( 1 === $row->getRowIndex() ) {
				$cell_iterator = $row->getCellIterator();
				$cell_iterator->setIterateOnlyExistingCells( false );
				foreach ( $cell_iterator as $cell ) {
					$this->headings[] = $this->convert_string_encoding( $cell->getValue() );
				}
			} elseif ( 1 < $row->getRowIndex() ) {
				$parsed_post = $this->parse_row_post( $row );
				$this->process_post( $parsed_post['data'], $parsed_post['meta'], $parsed_post['terms'] );
			}
		}

		// End the import routine.
		$this->import_end();
	}

	/**
	 * Log an error instance to the logger.
	 *
	 * @param \WP_Error $error Error instance to log.
	 */
	protected function log_error( \WP_Error $error ) {
		$this->logger->warning( $error->get_error_message() );

		// Log the data as debug info too.
		$data = $error->get_error_data();
		if ( ! empty( $data ) ) {
			$this->logger->debug(var_export($data, true)); // @codingStandardsIgnoreLine
		}
	}

	/**
	 * Checks that the Excel file exists and prepares us for the task of processing parsed data.
	 *
	 * @param string $file Path to the WXR file for importing
	 */
	protected function import_start( $file ) {
		if ( ! is_file( $file ) ) {
			return new WP_Error( 'resource_importer.file_missing', __( 'The file does not exist, please try again.', 'learning-commons-importer' ) );
		}

		// Suspend term counting and cache invalidation during the import routine.
		wp_defer_term_counting( true );
		wp_suspend_cache_invalidation( true );

		// Prefill existing posts if required.
		if ( $this->options['prefill_existing_posts'] ) {
			$this->prefill_existing_posts();
		}

		/**
		 * Begin the import.
		 *
		 * Fires before the import process has begun. If you need to suspend caching or heavy processing on hooks,
		 * do so here.
		 */
		do_action( 'import_start' );
	}

	/**
	 * Performs post-import cleanup of files and the cache
	 */
	protected function import_end() {
		// Re-enable stuff in core
		wp_suspend_cache_invalidation( false );
		wp_cache_flush();
		foreach ( get_taxonomies() as $tax ) {
			delete_option( "{$tax}_children" );
			_get_term_hierarchy( $tax );
		}

		wp_defer_term_counting( false );

		/**
		 * Complete the import.
		 *
		 * Fires after the import process has finished. If you need to update
		 * your cache or re-enable processing, do so here.
		 */
		do_action( 'import_end' );
	}

	/**
	 * Convert encoding of strings coming from Excel.
	 *
	 * @param string $value The source string.
	 *
	 * @return string The string with converted encoding.
	 */
	protected function convert_string_encoding( $value ) {
		if ( ! seems_utf8( $value ) ) {
            if ( function_exists( 'mb_convert_encoding' ) ) {
                return mb_convert_encoding( $value, 'UTF-8', 'windows-1252' );
            } elseif ( function_exists( 'iconv' ) ) {
                return iconv( 'windows-1252', 'UTF-8', $value );
            } else {
                return utf8_encode( $value );
            }
		}

		return $value;
	}

	/**
	 * Parse a row into post data.
	 *
	 * @param RowIterator $row Iterator object for the row.
	 *
	 * @return array|WP_Error Post data array on success, error otherwise.
	 */
	protected function parse_row_post( $row ) {
		$data  = [
			'post_type'   => 'lc_resource',
			'post_status' => 'pending',
		];
		$meta  = [];
		$terms = [];

		$cell_iterator = $row->getCellIterator();
		$cell_iterator->setIterateOnlyExistingCells( false );
		$c = 0;

		foreach ( $cell_iterator as $cell ) {
			if ( $c >= 1 && $c < 41 && ! in_array( $c, [ 12, 13, 14 ], true ) ) {
				$val = $cell->getValue();
				if ( $val ) {
					switch ( $this->headings[ $c ] ) {
						case 'Item Type':
							$format    = $this->map_format( $this->convert_string_encoding( $val ) );
							$term_item = $this->parse_term( $format, 'lc_format' );
							if ( ! empty( $term_item ) ) {
								$terms[] = $term_item;
							}
							break;
						case 'Publication Year':
							$meta[] = [
								'key'   => 'lc_resource_publication_year',
								'value' => absint( $val ),
							];
							$meta[] = [
								'key'   => 'lc_resource_publication_date',
								'value' => absint( $val ),
							];
							break;
						case 'Author':
							$val = explode( '; ', $val );
							if ( ! is_array( $val ) ) {
								$val = [ $val ];
							}
							$vals = [];
							foreach ( $val as $v ) {
								$parts    = explode( ', ', $v );
								$name     = $parts[1] . ' ' . $parts[0];
								$values[] = $this->convert_string_encoding( $name );
							}
							$i = 0;
							foreach ( $values as $author ) {
								$meta[] = [
									'key'   => "lc_resource_author_${i}_author",
									'value' => $author,
								];
								$meta[] = [
									'key'   => "_lc_resource_author_${i}_author",
									'value' => 'field_5e56edce93658',
								];
								$i++;
							}
							$meta[] = [
								'key'   => 'lc_resource_author',
								'value' => $i + 1,
							];
							$meta[] = [
								'key'   => '_lc_resource_author',
								'value' => 'field_5e56ed9c93657',
							];
							break;
						case 'Title':
							$data['post_title'] = $this->convert_string_encoding( $val );
							$data['post_name']  = sanitize_title( $data['post_title'] );
							$data['hash']       = md5( $data['post_title'] );
							break;
						case 'Publication Title':
							$meta[] = [
								'key'   => 'lc_resource_publication_title',
								'value' => $this->convert_string_encoding( $val ),
							];
							break;
						case 'ISBN':
						case 'ISSN':
						case 'DOI':
							$meta_key = 'lc_resource_' . strtolower( $this->headings[ $c ] );
							$meta[]   = [
								'key'   => $meta_key,
								'value' => $this->convert_string_encoding( $val ),
							];
							break;
						case 'Url':
							$meta[] = [
								'key'   => 'lc_resource_permanent_link',
								'value' => esc_url( $this->convert_string_encoding( $val ) ),
							];
							break;
						case 'Abstract Note':
							$data['post_content'] = $this->convert_string_encoding( $val );
							break;
						case 'Date':
							if ( Date::isDateTime( $cell ) ) {
								$meta[] = [
									'key'   => 'lc_resource_publication_month',
									'value' => Date::excelToDateTimeObject( $val )->format( 'm' ),
								];
								$meta[] = [
									'key'   => 'lc_resource_publication_day',
									'value' => Date::excelToDateTimeObject( $val )->format( 'd' ),
								];
								$meta[] = [
									'key'   => 'lc_resource_publication_date',
									'value' => Date::excelToDateTimeObject( $val )->format( 'Y-m-d' ),
								];
							}
							break;
						case 'Pages':
						case 'Num Pages':
						case 'Issue':
						case 'Volume':
						case 'Number Of Volumes':
							// Add unused fields in case we want them later.
							$meta_key = 'lc_resource_' . str_replace( ' ', '_', strtolower( $this->headings[ $c ] ) );
							$meta[]   = [
								'key'   => $meta_key,
								'value' => $this->convert_string_encoding( $val ),
							];
							break;
						case 'Short Title':
							$meta[] = [
								'key'   => 'lc_resource_short_title',
								'value' => $this->convert_string_encoding( $val ),
							];
							break;
						case 'Series':
						case 'Series Number':
						case 'Series Text':
						case 'Series Title':
							// Add unused fields in case we want them later.
							$meta_key = 'lc_resource_' . str_replace( ' ', '_', strtolower( $this->headings[ $c ] ) );
							$meta[]   = [
								'key'   => $meta_key,
								'value' => $this->convert_string_encoding( $val ),
							];
							break;
						case 'Publisher':
							$meta[] = [
								'key'   => 'lc_resource_publisher_name',
								'value' => $this->convert_string_encoding( $val ),
							];
							break;
						case 'Place':
							$meta[] = [
								'key'   => 'lc_resource_publisher_locality',
								'value' => $this->convert_string_encoding( $val ),
							];
							break;
						case 'Language':
							$lang      = $this->map_language( $this->convert_string_encoding( $val ) );
							$meta[]    = [
								'key'   => 'language',
								'value' => $lang,
							];
							break;
						case 'Rights':
							$rights = $this->map_rights( $this->convert_string_encoding( $val ) );
							$meta[] = [
								'key'   => 'lc_resource_rights',
								'value' => $rights,
							];
							break;
					}
				}
			}

			$c++;
		}

		return compact( 'data', 'meta', 'terms' );
	}

    /**
	 * Parse a term.
	 *
	 * @param string $term     The term slug.
	 * @param string $taxonomy The term's taxonomy.
	 *
	 * @return array An array of term data.
	 */
	protected function parse_term( $term, $taxonomy ) {
		$data = [
			'name'     => $term,
			'slug'     => sanitize_title( $term ),
			'taxonomy' => $taxonomy,
		];
		return $data;
	}

	/**
	 * Create new posts based on import information
	 *
	 * Posts marked as having a parent which doesn't exist will become top level items.
	 * Doesn't create a new post if: the post type doesn't exist, the given post ID
	 * is already noted as imported or a post with the same title and date already exists.
	 * Note that new/updated terms, comments and meta are imported for the last of the above.
	 *
	 * @param array $data  Post data.
	 * @param array $meta  Post meta.
	 * @param array $terms Post terms.
	 */
	protected function process_post( $data, $meta, $terms ) {
		if ( empty( $data ) ) {
			return false;
		}

		$post_type_object = get_post_type_object( $data['post_type'] );

		// Is this type even valid?
		if ( ! $post_type_object ) {
			$this->logger->warning(
				sprintf(
					/* Translators: %1$s: The post title. %2$s: The post type. */
					__( 'Failed to import "%1$s": invalid post type %2$s.', 'learning-commons-importer' ),
					$data['post_title'],
					$data['post_type']
				)
			);
			return false;
		}

		// Have we already processed this?
		if ( isset( $this->mapping['resource'][ $data['hash'] ] ) ) {
			$this->logger->info(
				sprintf(
					/* Translators: %1$s: The post type name. %2$s: The post title. */
					__( '%1$s "%2$s" already processed.', 'learning-commons-importer' ),
					$post_type_object->labels->singular_name,
					$data['post_title']
				)
			);

			do_action('resource_importer.process_skipped.resource', $data); // @codingStandardsIgnoreLine

			return false;
		}

		// Does this post already exist?
		$post_exists = $this->post_exists( $data );
		if ( $post_exists ) {
			$this->logger->info(
				sprintf(
					/* Translators: %1$s: The post type name. %2$s: The post title. */
					__( '%1$s "%2$s" already exists.', 'learning-commons-importer' ),
					$post_type_object->labels->singular_name,
					$data['post_title']
				)
			);

			do_action('resource_importer.process_already_imported.resource', $data); // @codingStandardsIgnoreLine

			return false;
		}

		// Whitelist to just the keys we allow
		$allowed = [
			'post_content' => true,
			'post_title'   => true,
			'post_status'  => true,
			'post_type'    => true,
		];
		foreach ( $data as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}

			$postdata[ $key ] = $data[ $key ];
		}

		$postdata = apply_filters( 'wp_import_post_data_processed', $postdata, $data );

		$post_id = wp_insert_post( $postdata, true );

		if ( is_wp_error( $post_id ) ) {
			$this->logger->error(
				sprintf(
					/* Translators: %1$s: The post title. %2$s: The post type. */
					__( 'Failed to import "%1$s" (%2$s).', 'learning-commons-importer' ),
					$data['post_title'],
					$post_type_object->labels->singular_name
				)
			);
			$this->logger->debug( $post_id->get_error_message() );

			do_action('resource_importer.process_failed.resource', $post_id, $data, $meta, $terms); // @codingStandardsIgnoreLine
			return false;
		}

		$this->mapping['resource'][ $data['hash'] ] = (int) $post_id;
		$this->mark_post_exists( $data, $post_id );

		$this->logger->info(
			sprintf(
				/* Translators: %1$s: The post title. %2$s: The post type. */
				__( 'Imported "%1$s" (%2$s)', 'learning-commons-importer' ),
				$data['post_title'],
				$post_type_object->labels->singular_name
			)
		);

		$this->process_post_meta( $meta, $post_id, $data );

		do_action('resource_importer.processed.resource', $post_id, $data, $meta, $terms); // @codingStandardsIgnoreLine
	}

	/**
	 * Process and import post meta items.
	 *
	 * @param array $meta List of meta data arrays
	 * @param int   $post_id Post to associate with
	 * @param array $post Post data
	 * @return int|WP_Error Number of meta items imported on success, error otherwise.
	 */
	protected function process_post_meta( $meta, $post_id, $post ) {
		if ( empty( $meta ) ) {
			return true;
		}

		foreach ( $meta as $meta_item ) {
			/**
			 * Pre-process post meta data.
			 *
			 * @param array $meta_item Meta data. (Return empty to skip.)
			 * @param int $post_id Post the meta is attached to.
			 */
			if ( empty( $meta_item ) ) {
				return false;
			}

			$key   = apply_filters( 'import_post_meta_key', $meta_item['key'], $post_id, $post );
			$value = false;

			if ( $key ) {
				// Export gets meta straight from the DB so could have a serialized string.
				if ( ! $value ) {
					$value = maybe_unserialize( $meta_item['value'] );
				}

				add_post_meta( $post_id, $key, $value );
				do_action( 'import_post_meta', $post_id, $key, $value );
			}
		}

		return true;
	}

	/**
	 * Prefill existing post data.
	 *
	 * This preloads all GUIDs into memory, allowing us to avoid hitting the
	 * database when we need to check for existence. With larger imports, this
	 * becomes prohibitively slow to perform SELECT queries on each.
	 *
	 * By preloading all this data into memory, it's a constant-time lookup in
	 * PHP instead. However, this does use a lot more memory, so for sites doing
	 * small imports onto a large site, it may be a better tradeoff to use
	 * on-the-fly checking instead.
	 */
	protected function prefill_existing_posts() {
		global $wpdb;
		$posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s", 'lc_resource' ) );

		foreach ( $posts as $item ) {
			$this->exists['resource'][ md5( $item->post_title ) ] = $item->ID;
		}
	}

	/**
	 * Does the post exist?
	 *
	 * @param array $data Post data to check against.
	 * @return int|bool Existing post ID if it exists, false otherwise.
	 */
	protected function post_exists( $data ) {
		// Constant-time lookup if we prefilled.
		$exists_key = $data['hash'];

		if ( $this->options['prefill_existing_posts'] ) {
			return isset( $this->exists['resource'][ $exists_key ] ) ? $this->exists['resource'][ $exists_key ] : false;
		}

		// No prefilling, but might have already handled it.
		if ( isset( $this->exists['resource'][ $exists_key ] ) ) {
			return $this->exists['resource'][ $exists_key ];
		}

		// Still nothing, try post_exists, and cache it.
		$exists                                  = post_exists( $data['post_title'], $data['post_content'], $data['post_date'] );
		$this->exists['resource'][ $exists_key ] = $exists;

		return $exists;
	}

	/**
	 * Mark the post as existing.
	 *
	 * @param array $data Post data to mark as existing.
	 * @param int   $post_id Post ID.
	 */
	protected function mark_post_exists( $data, $post_id ) {
		$this->exists['resource'][ $data['hash'] ] = $post_id;
	}

	/**
	 * Convert resource language code to Polylang language code.
	 *
	 * @param string $lang A language code from the Excel sheet.
	 *
	 * @return string $output A two-character language code for the post meta field.
	 */
	protected function map_language( $lang ) {
		$input = strpos( $lang, '-' ) ? explode( '-', $lang )[0] : $lang;
		switch ( $input ) {
			case 'ger':
			case 'de-DE':
				$output = 'de';
				break;
			case 'sv-SE':
				$output = 'sv';
				break;
			case 'spa':
				$output = 'es';
				break;
			case 'fre':
			case 'fr-FR':
				$output = 'fr';
				break;
			case 'it-IT':
				$output = 'it';
				break;
			case 'portuguese':
			case 'pt-BR':
				$output = 'pt';
				break;
			case '':
			case 'eng':
			case 'English':
			case 'en-US':
			case 'en-GB':
			case 'en-AU':
			case 'en-CAC':
				$output = 'en';
				break;
			default:
				$output = $input;
				break;
		}

		return $output;
	}

	/**
	 * Map item type from Excel to resource format taxonomy.
	 *
	 * @param string $format The Schema-ish format from the Excel sheet.
	 *
	 * @return string The mapped format's term slug.
	 */
	protected function map_format( $format ) {
		switch ( $format ) {
			case 'audioRecording':
				return 'audio';
			case 'caseStudy':
				return 'case-study';
			case 'game':
				return 'toolkit';
			case 'conferencePaper':
			case 'journalArticle':
			case 'thesis':
				return 'academic-paper';
			case 'podcast':
				return 'podcast';
			case 'videoRecording':
				return 'video';
			case 'book':
			case 'bookSection':
				return 'book';
			case 'report':
				return 'report';
			case 'presentation':
				return 'presentation-slides';
			case 'blogPost':
			case 'magazineArticle':
			case 'newspaperArticle':
			case 'webpage':
			default:
				return 'article';
		}
	}

	/**
	 * Map rights from Excel to resource rights field.
	 *
	 * @param string $rights The rights as indicated in the Excel sheet.
	 *
	 * @return string The mapped rights value.
	 */
	protected function map_rights( $rights ) {
		$rights = strtolower( $rights );
		if ( strpos( $rights, 'attribution-noncommercial-sharealike' ) || strpos( $rights, 'by-nc-sa' ) ) {
			return 'cc-by-nc-sa';
		}
		if ( strpos( $rights, 'attribution-noncommercial-noderivatives' ) || strpos( $rights, 'by-nc-nd' ) ) {
			return 'cc-by-nc-nd';
		}
		if ( strpos( $rights, 'attribution-sharealike' ) || strpos( $rights, 'by-sa' ) ) {
			return 'cc-by-sa';
		}
		if ( strpos( $rights, 'attribution-noderivatives' ) || strpos( $rights, 'by-nd' ) ) {
			return 'cc-by-nd';
		}
		if ( strpos( $rights, 'attribution-noncommercial' ) || strpos( $rights, 'by-nc' ) ) {
			return 'cc-by-nc';
		}
		if ( strpos( $rights, 'attribution' ) || strpos( $rights, 'by' ) ) {
			return 'cc-by';
		}
		if ( strpos( $rights, 'educational community license' ) || strpos( $rights, 'ecl' ) ) {
			return 'ecl';
		}
		if ( strpos( $rights, 'no rights reserved' ) || strpos( $rights, 'zero' ) || strpos( $rights, 'cc0' ) ) {
			return 'cc0';
		}
		if ( strpos( $rights, 'public domain' ) || strpos( $rights, 'no known copyright' ) ) {
			return 'public-domain';
		}
		return 'all-rights-reserved';
	}
}
