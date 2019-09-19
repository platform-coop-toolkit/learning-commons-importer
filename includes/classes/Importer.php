<?php
/**
 * Importer class.
 *
 * @package LearningCommonsImporter
 */

namespace LearningCommonsImporter;

use \PhpOffice\PhpSpreadsheet\Reader\Xls;
use \PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Class which handles the resource importer.
 */
class Importer {
	/**
	 * Mapping from Excel rows to imported post IDs.
	 *
	 * @var array $mapping An array where the key is an md5 hash of the resource URL and the value is the post ID.
	 */
	protected $mapping = [];

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
	 * The main controller for the actual import stage.
	 *
	 * @param string $file Path to the Excel file for importing
	 */
	public function get_preliminary_information( $file ) {
		$reader      = new Xls();
		$spreadsheet = $reader->load( $file );

		if ( is_wp_error( $spreadsheet ) ) {
			return $spreadsheet;
		}

		$worksheet = $spreadsheet->getActiveSheet();

		$headings  = [];
		$resources = [];

		$data = new ImportInfo();

		$r = 0;
		foreach ( $worksheet->getRowIterator() as $row ) {
			if ( 0 === $r ) {
				$cell_iterator = $row->getCellIterator();
				$cell_iterator->setIterateOnlyExistingCells( false );
				foreach ( $cell_iterator as $cell ) {
					$headings[] = mb_convert_encoding( $cell->getValue(), 'Windows-1252', 'UTF-8' );
				}
			} else {
				$data->resource_count++;
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
			}
			$r++;
		}
		return $data;
	}
}
