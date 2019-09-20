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
	 * Mapping from Excel rows to imported post IDs.
	 *
	 * @var array $mapping An array where the key is an md5 hash of the resource URL and the value is the post ID.
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
		$empty_types              = [ 'resource' => [] ];
		$this->mapping            = $empty_types;
		$this->requires_remapping = $empty_types;
		$this->exists             = $empty_types;
		$this->options            = wp_parse_args(
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
		$r           = 0;
		foreach ( $spreadsheet->getActiveSheet()->getRowIterator() as $row ) {
			// Column Headings
			if ( 0 === $r ) {
				$cell_iterator = $row->getCellIterator();
				$cell_iterator->setIterateOnlyExistingCells( false );
				foreach ( $cell_iterator as $cell ) {
					$this->headings[] = mb_convert_encoding( $cell->getValue(), 'Windows-1252', 'UTF-8' );
				}
			} else {
				$data->resource_count++;
			}
			$r++;
		}

		return $data;
	}

	/*

	$cell_iterator = $row->getCellIterator();
				$cell_iterator->setIterateOnlyExistingCells( false );
				$c = 0;
				foreach ( $cell_iterator as $cell ) {
					if ( $c >= 1 && $c < 41 && ! in_array( $c, [ 12, 13, 14 ], true ) ) {
						$val = $cell->getValue();
						if ( $val ) {
							switch ( $headings[ $c ] ) {
								case 'Author':
									$val = explode( '; ', $val );
									if ( ! is_array( $val ) ) {
										$val = [ $val ];
									}
									foreach ( $val as $v ) {
										$parts                                = explode( ', ', $v );
										$name                                 = $parts[1] . ' ' . $parts[0];
										$resources[ $r ][ $headings[ $c ] ][] = mb_convert_encoding( $name, 'Windows-1252', 'UTF-8' );
									}
									break;
								case 'Manual Tags':
								case 'Automatic Tags':
									$val = explode( '; ', $val );
									if ( ! is_array( $val ) ) {
										$val = [ $val ];
									}
									foreach ( $val as $v ) {
										$resources[ $r ]['Topics'][] = ucwords( mb_convert_encoding( $v, 'Windows-1252', 'UTF-8' ) );
									}
									break;
								default:
									if ( Date::isDateTime( $cell ) ) {
										$resources[ $r ][ $headings[ $c ] ] = Date::excelToDateTimeObject( $val )->format( 'Y-m-d' );
									} else {
										$resources[ $r ][ $headings[ $c ] ] = mb_convert_encoding( $val, 'Windows-1252', 'UTF-8' );
									}
									break;
							}
						}
					}

					$c++;
				}
				*/


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
		$r = 0;
		foreach ( $spreadsheet->getActiveSheet()->getRowIterator() as $row ) {
			if ( 0 < $r ) {
				$parsed = $this->parse_post_row( $row );
				$this->process_post( $parsed['data'], $parsed['meta'], $parsed['terms'] );
			}
			$r++;
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
			$this->logger->debug( var_export( $data, true ) );
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
		return mb_convert_encoding( $value, 'Windows-1252', 'UTF-8' );
	}

	/**
	 * Parse a row into post data.
	 *
	 * @param RowIterator $row Iterator object for the row.
	 *
	 * @return array|WP_Error Post data array on success, error otherwise.
	 */
	protected function parse_post_row( $row ) {
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
							// TODO: Map item types.
							break;
						case 'Publication Year':
							$meta[] = [
								'key'   => 'lc_resource_publication_year',
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
							$meta[] = [
								'key'   => 'lc_resource_author',
								'value' => $values,
							];
							break;
						case 'Title':
							$data['post_title'] = $this->convert_string_encoding( $val );
							$data['post_name']  = '';
							break;
						case 'Publication Title':
							// TODO: Add a field?
							break;
						case 'ISBN':
						case 'ISSN':
						case 'DOI':
							$meta_key = 'lc_resource_' . strtolower( $this->headings[ $c ] );
							$meta[]   = [
								'key'   => $meta_key,
								'value' => $val,
							];
							break;
						case 'Url':
							$meta[] = [
								'key'   => 'lc_resource_permanent_link',
								'value' => esc_url( $val ),
							];
							break;
						case 'Abstract Note':
							$data['post_content'] = $this->convert_string_encoding( $val );
							break;
						case 'Date':
							if ( Date::isDateTime( $cell ) ) {
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
						case 'Short Title':
						case 'Series':
						case 'Series Number':
						case 'Series Text':
						case 'Series Title':
							// TODO: Add a field?
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
							// TODO: Map to WordPress languages and set Polylang language.
							break;
						case 'Rights':
							// TODO: Handle licensing. @see https://github.com/platform-coop-toolkit/learning-commons-framework/issues/5
							break;
						case 'Manual Tags':
						case 'Automatic Tags':
							/* @codingStandardsIgnoreStart
							// TODO: Add parse_topic() method.
							$val = explode( '; ', $val );
							if ( ! is_array( $val ) ) {
								$val = [ $val ];
							}
							foreach ( $val as $v ) {
								$term_item = $this->parse_topic( $v );
								if ( ! empty( $term_item ) ) {
									$terms[] = $term_item;
								}
							}
							@codingStandardsIgnoreEnd */
							break;
					}
				}
			}

			$c++;
		}

		return compact( 'data', 'meta', 'terms' );
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
		/**
		 * Pre-process post data.
		 *
		 * @param array $data Post data. (Return empty to skip.)
		 * @param array $meta Meta data.
		 * @param array $comments Comments on the post.
		 * @param array $terms Terms on the post.
		 */
		$data = apply_filters( 'wxr_importer.pre_process.post', $data, $meta, $comments, $terms );
		if ( empty( $data ) ) {
			return false;
		}

		$original_id = isset( $data['post_id'] ) ? (int) $data['post_id'] : 0;
		$parent_id   = isset( $data['post_parent'] ) ? (int) $data['post_parent'] : 0;
		$author_id   = isset( $data['post_author'] ) ? (int) $data['post_author'] : 0;

		// Have we already processed this?
		if ( isset( $this->mapping['post'][ $original_id ] ) ) {
			return;
		}

		$post_type_object = get_post_type_object( $data['post_type'] );

		// Is this type even valid?
		if ( ! $post_type_object ) {
			$this->logger->warning(
				sprintf(
					__( 'Failed to import "%1$s": Invalid post type %2$s', 'learning-commons-importer' ),
					$data['post_title'],
					$data['post_type']
				)
			);
			return false;
		}

		$post_exists = $this->post_exists( $data );
		if ( $post_exists ) {
			$this->logger->info(
				sprintf(
					__( '%1$s "%2$s" already exists.', 'learning-commons-importer' ),
					$post_type_object->labels->singular_name,
					$data['post_title']
				)
			);

			/**
			 * Post processing already imported.
			 *
			 * @param array $data Raw data imported for the post.
			 */
			do_action( 'wxr_importer.process_already_imported.post', $data );

			// Even though this post already exists, new comments might need importing
			$this->process_comments( $comments, $original_id, $data, $post_exists );

			return false;
		}

		// Map the parent post, or mark it as one we need to fix
		$requires_remapping = false;
		if ( $parent_id ) {
			if ( isset( $this->mapping['post'][ $parent_id ] ) ) {
				$data['post_parent'] = $this->mapping['post'][ $parent_id ];
			} else {
				$meta[]             = array(
					'key'   => '_wxr_import_parent',
					'value' => $parent_id,
				);
				$requires_remapping = true;

				$data['post_parent'] = 0;
			}
		}

		// Map the author, or mark it as one we need to fix
		$author = sanitize_user( $data['post_author'], true );
		if ( empty( $author ) ) {
			// Missing or invalid author, use default if available.
			$data['post_author'] = $this->options['default_author'];
		} elseif ( isset( $this->mapping['user_slug'][ $author ] ) ) {
			$data['post_author'] = $this->mapping['user_slug'][ $author ];
		} else {
			$meta[]             = array(
				'key'   => '_wxr_import_user_slug',
				'value' => $author,
			);
			$requires_remapping = true;

			$data['post_author'] = (int) get_current_user_id();
		}

		// Does the post look like it contains attachment images?
		if ( preg_match( self::REGEX_HAS_ATTACHMENT_REFS, $data['post_content'] ) ) {
			$meta[]             = array(
				'key'   => '_wxr_import_has_attachment_refs',
				'value' => true,
			);
			$requires_remapping = true;
		}

		// Whitelist to just the keys we allow
		$postdata = array(
			'import_id' => $data['post_id'],
		);
		$allowed  = array(
			'post_author'    => true,
			'post_date'      => true,
			'post_date_gmt'  => true,
			'post_content'   => true,
			'post_excerpt'   => true,
			'post_title'     => true,
			'post_status'    => true,
			'post_name'      => true,
			'comment_status' => true,
			'ping_status'    => true,
			'guid'           => true,
			'post_parent'    => true,
			'menu_order'     => true,
			'post_type'      => true,
			'post_password'  => true,
		);
		foreach ( $data as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}

			$postdata[ $key ] = $data[ $key ];
		}

		$postdata = apply_filters( 'wp_import_post_data_processed', $postdata, $data );

		if ( 'attachment' === $postdata['post_type'] ) {
			if ( ! $this->options['fetch_attachments'] ) {
				$this->logger->notice(
					sprintf(
						__( 'Skipping attachment "%s", fetching attachments disabled' ),
						$data['post_title']
					)
				);
				/**
				 * Post processing skipped.
				 *
				 * @param array $data Raw data imported for the post.
				 * @param array $meta Raw meta data, already processed by {@see process_post_meta}.
				 */
				do_action( 'wxr_importer.process_skipped.post', $data, $meta );
				return false;
			}
			$remote_url = ! empty( $data['attachment_url'] ) ? $data['attachment_url'] : $data['guid'];
			$post_id    = $this->process_attachment( $postdata, $meta, $remote_url );
		} else {
			$post_id = wp_insert_post( $postdata, true );
			do_action( 'wp_import_insert_post', $post_id, $original_id, $postdata, $data );
		}

		if ( is_wp_error( $post_id ) ) {
			$this->logger->error(
				sprintf(
					__( 'Failed to import "%1$s" (%2$s)', 'learning-commons-importer' ),
					$data['post_title'],
					$post_type_object->labels->singular_name
				)
			);
			$this->logger->debug( $post_id->get_error_message() );

			/**
			 * Post processing failed.
			 *
			 * @param WP_Error $post_id Error object.
			 * @param array $data Raw data imported for the post.
			 * @param array $meta Raw meta data, already processed by {@see process_post_meta}.
			 * @param array $comments Raw comment data, already processed by {@see process_comments}.
			 * @param array $terms Raw term data, already processed.
			 */
			do_action( 'wxr_importer.process_failed.post', $post_id, $data, $meta, $comments, $terms );
			return false;
		}

		// Ensure stickiness is handled correctly too
		if ( $data['is_sticky'] === '1' ) {
			stick_post( $post_id );
		}

		// map pre-import ID to local ID
		$this->mapping['post'][ $original_id ] = (int) $post_id;
		if ( $requires_remapping ) {
			$this->requires_remapping['post'][ $post_id ] = true;
		}
		$this->mark_post_exists( $data, $post_id );

		$this->logger->info(
			sprintf(
				__( 'Imported "%1$s" (%2$s)', 'learning-commons-importer' ),
				$data['post_title'],
				$post_type_object->labels->singular_name
			)
		);
		$this->logger->debug(
			sprintf(
				__( 'Post %1$d remapped to %2$d', 'learning-commons-importer' ),
				$original_id,
				$post_id
			)
		);

		// Handle the terms too
		$terms = apply_filters( 'wp_import_post_terms', $terms, $post_id, $data );

		if ( ! empty( $terms ) ) {
			$term_ids = array();
			foreach ( $terms as $term ) {
				$taxonomy = $term['taxonomy'];
				$key      = sha1( $taxonomy . ':' . $term['slug'] );

				if ( isset( $this->mapping['term'][ $key ] ) ) {
					$term_ids[ $taxonomy ][] = (int) $this->mapping['term'][ $key ];
				} else {
					$meta[]             = array(
						'key'   => '_wxr_import_term',
						'value' => $term,
					);
					$requires_remapping = true;
				}
			}

			foreach ( $term_ids as $tax => $ids ) {
				$tt_ids = wp_set_post_terms( $post_id, $ids, $tax );
				do_action( 'wp_import_set_post_terms', $tt_ids, $ids, $tax, $post_id, $data );
			}
		}

		$this->process_comments( $comments, $post_id, $data );
		$this->process_post_meta( $meta, $post_id, $data );

		if ( 'nav_menu_item' === $data['post_type'] ) {
			$this->process_menu_item_meta( $post_id, $data, $meta );
		}

		/**
		 * Post processing completed.
		 *
		 * @param int $post_id New post ID.
		 * @param array $data Raw data imported for the post.
		 * @param array $meta Raw meta data, already processed by {@see process_post_meta}.
		 * @param array $comments Raw comment data, already processed by {@see process_comments}.
		 * @param array $terms Raw term data, already processed.
		 */
		do_action( 'wxr_importer.processed.post', $post_id, $data, $meta, $comments, $terms );

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
			$meta_item = apply_filters( 'wxr_importer.pre_process.post_meta', $meta_item, $post_id );
			if ( empty( $meta_item ) ) {
				return false;
			}

			$key   = apply_filters( 'import_post_meta_key', $meta_item['key'], $post_id, $post );
			$value = false;

			if ( '_edit_last' === $key ) {
				$value = intval( $meta_item['value'] );
				if ( ! isset( $this->mapping['user'][ $value ] ) ) {
					// Skip!
					continue;
				}

				$value = $this->mapping['user'][ $value ];
			}

			if ( $key ) {
				// export gets meta straight from the DB so could have a serialized string
				if ( ! $value ) {
					$value = maybe_unserialize( $meta_item['value'] );
				}

				add_post_meta( $post_id, $key, $value );
				do_action( 'import_post_meta', $post_id, $key, $value );

				// if the post has a featured image, take note of this in case of remap
				if ( '_thumbnail_id' === $key ) {
					$this->featured_images[ $post_id ] = (int) $value;
				}
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
		$posts = $wpdb->get_results( "SELECT ID, guid FROM {$wpdb->posts}" );

		foreach ( $posts as $item ) {
			$this->exists['post'][ $item->guid ] = $item->ID;
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
		$exists_key = $data['guid'];

		if ( $this->options['prefill_existing_posts'] ) {
			return isset( $this->exists['post'][ $exists_key ] ) ? $this->exists['post'][ $exists_key ] : false;
		}

		// No prefilling, but might have already handled it.
		if ( isset( $this->exists['post'][ $exists_key ] ) ) {
			return $this->exists['post'][ $exists_key ];
		}

		// Still nothing, try post_exists, and cache it.
		$exists                              = post_exists( $data['post_title'], $data['post_content'], $data['post_date'] );
		$this->exists['post'][ $exists_key ] = $exists;

		return $exists;
	}

	/**
	 * Mark the post as existing.
	 *
	 * @param array $data Post data to mark as existing.
	 * @param int   $post_id Post ID.
	 */
	protected function mark_post_exists( $data, $post_id ) {
		$exists_key                          = $data['guid'];
		$this->exists['post'][ $exists_key ] = $post_id;
	}
}
