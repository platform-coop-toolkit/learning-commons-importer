<?php
/**
 * HTML logger class.
 *
 * @package LearningCommonsImporter
 */

namespace LearningCommonsImporter;

/**
 * Info about the current import.
 */
class ImportInfo {
	/**
	 * The resource count.
	 *
	 * @var int $resource_count
	 */
	public $resource_count = 0;

	/**
	 * The term (topic) count.
	 *
	 * @var int $term_count
	 */
	public $term_count = 0;
}
