<?php
/**
 * Plugin Name: Learning Commons Importer
 * Plugin URI: https://github.com/platform-coop-toolkit/learning-commons-importer/
 * Description: Resource importer for the Platform Co-op Resource Library.
 * Version: 1.0.0-alpha
 * Author: Platform Cooperative Development Kit
 * Author URI:  https://github.com/platform-coop-toolkit/
 * Text Domain: learning-commons-importer
 * Domain Path: /languages
 *
 * @package LearningCommonsImporter
 */

// Useful global constants.
define( 'LEARNING_COMMONS_IMPORTER_VERSION', '1.0.0-alpha.2' );
define( 'LEARNING_COMMONS_IMPORTER_URL', plugin_dir_url( __FILE__ ) );
define( 'LEARNING_COMMONS_IMPORTER_PATH', plugin_dir_path( __FILE__ ) );
define( 'LEARNING_COMMONS_IMPORTER_INC', LEARNING_COMMONS_IMPORTER_PATH . 'includes/' );

// Include files.
require_once LEARNING_COMMONS_IMPORTER_INC . 'functions/core.php';

// Activation/Deactivation.
register_activation_hook( __FILE__, '\LearningCommonsImporter\Core\activate' );
register_deactivation_hook( __FILE__, '\LearningCommonsImporter\Core\deactivate' );

// Bootstrap.
LearningCommonsImporter\Core\setup();


// Require Composer autoloader if it exists.
if ( file_exists( LEARNING_COMMONS_IMPORTER_PATH . '/vendor/autoload.php' ) ) {
	require_once LEARNING_COMMONS_IMPORTER_PATH . 'vendor/autoload.php';
}
