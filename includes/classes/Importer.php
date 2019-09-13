<?php
/**
 * Importer class.
 *
 * @package LearningCommonsImporter
 */

namespace LearningCommonsImporter;

use \PhpOffice\PhpSpreadsheet\Reader\Xls;

/**
 * Class which handles the resource importer.
 */
class Importer {
	/**
	 * Constructor.
	 */
	public function __construct() {

	}

	/**
	 * The main controller for the actual import stage.
	 *
	 * @param string $file Path to the Excel file for importing
	 */
	public function get_preliminary_information( $file ) {
		$reader = new Xls();
		$data   = $reader->load( $file );
		return $data;
	}
}
