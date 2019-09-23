<?php
/**
 * Core plugin functionality.
 *
 * @package LearningCommonsImporter
 */

namespace LearningCommonsImporter\Core;

use \WP_Error as WP_Error;

/**
 * Default setup routine
 *
 * @return void
 */
function setup() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	add_action( 'init', $n( 'i18n' ) );
	add_action( 'init', $n( 'init' ) );
	add_action( 'wp_enqueue_scripts', $n( 'scripts' ) );
	add_action( 'wp_enqueue_scripts', $n( 'styles' ) );
	add_action( 'admin_init', $n( 'register_importer' ) );
	add_action( 'admin_enqueue_scripts', $n( 'admin_scripts' ) );
	add_action( 'admin_enqueue_scripts', $n( 'admin_styles' ) );

	// Editor styles. add_editor_style() doesn't work outside of a theme.
	add_filter( 'mce_css', $n( 'mce_css' ) );
	// Hook to allow async or defer on asset loading.
	add_filter( 'script_loader_tag', $n( 'script_loader_tag' ), 10, 2 );

	do_action( 'learning_commons_importer_loaded' );
}

/**
 * Registers the default textdomain.
 *
 * @return void
 */
function i18n() {
	$locale = apply_filters( 'plugin_locale', get_locale(), 'learning-commons-importer' );
	load_textdomain( 'learning-commons-importer', WP_LANG_DIR . '/learning-commons-importer/learning-commons-importer-' . $locale . '.mo' );
	load_plugin_textdomain( 'learning-commons-importer', false, plugin_basename( LEARNING_COMMONS_IMPORTER_PATH ) . '/languages/' );
}

/**
 * Initializes the plugin and fires an action other plugins can hook into.
 *
 * @return void
 */
function init() {
	do_action( 'learning_commons_importer_init' );
}

/**
 * Register a custom WordPress importer.
 *
 * @see https://developer.wordpress.org/reference/functions/register_importer/
 *
 * @return void
 */
function register_importer() {
	$GLOBALS['resource_importer'] = new \LearningCommonsImporter\ImportUI();
	\register_importer(
		'learning-commons-resources',
		__( 'Resources (Excel)', 'learning-commons-importer' ),
		__( 'Import resources from an Excel spreadsheet.', 'learning-commons-importer' ),
		[ $GLOBALS['resource_importer'], 'dispatch' ]
	);

	add_action( 'load-importer-resources', [ $GLOBALS['resource_importer'], 'on_load' ] );
	add_action( 'wp_ajax_resource-import', [ $GLOBALS['resource_importer'], 'stream_import' ] );
}

/**
 * Activate the plugin
 *
 * @return void
 */
function activate() {
	// First load the init scripts in case any rewrite functionality is being loaded
	init();
	flush_rewrite_rules();
}

/**
 * Deactivate the plugin
 *
 * Uninstall routines should be in uninstall.php
 *
 * @return void
 */
function deactivate() {

}


/**
 * The list of knows contexts for enqueuing scripts/styles.
 *
 * @return array
 */
function get_enqueue_contexts() {
	return [ 'admin', 'frontend', 'shared' ];
}

/**
 * Generate an URL to a script, taking into account whether SCRIPT_DEBUG is enabled.
 *
 * @param string $script Script file name (no .js extension)
 * @param string $context Context for the script ('admin', 'frontend', or 'shared')
 *
 * @return string|WP_Error URL
 */
function script_url( $script, $context ) {

	if ( ! in_array( $context, get_enqueue_contexts(), true ) ) {
		return new WP_Error( 'invalid_enqueue_context', 'Invalid $context specified in LearningCommonsImporter script loader.' );
	}

	return LEARNING_COMMONS_IMPORTER_URL . "dist/js/${script}.js";

}

/**
 * Generate an URL to a stylesheet, taking into account whether SCRIPT_DEBUG is enabled.
 *
 * @param string $stylesheet Stylesheet file name (no .css extension)
 * @param string $context Context for the script ('admin', 'frontend', or 'shared')
 *
 * @return string URL
 */
function style_url( $stylesheet, $context ) {

	if ( ! in_array( $context, get_enqueue_contexts(), true ) ) {
		return new WP_Error( 'invalid_enqueue_context', 'Invalid $context specified in LearningCommonsImporter stylesheet loader.' );
	}

	return LEARNING_COMMONS_IMPORTER_URL . "dist/css/${stylesheet}.css";

}

/**
 * Enqueue scripts for front-end.
 *
 * @return void
 */
function scripts() {

	wp_enqueue_script(
		'learning_commons_importer_shared',
		script_url( 'shared', 'shared' ),
		[],
		LEARNING_COMMONS_IMPORTER_VERSION,
		true
	);

	wp_enqueue_script(
		'learning_commons_importer_frontend',
		script_url( 'frontend', 'frontend' ),
		[],
		LEARNING_COMMONS_IMPORTER_VERSION,
		true
	);

}

/**
 * Enqueue scripts for admin.
 *
 * @return void
 */
function admin_scripts() {

	wp_enqueue_script(
		'learning_commons_importer_shared',
		script_url( 'shared', 'shared' ),
		[],
		LEARNING_COMMONS_IMPORTER_VERSION,
		true
	);

	wp_enqueue_script(
		'learning_commons_importer_admin',
		script_url( 'admin', 'admin' ),
		[],
		LEARNING_COMMONS_IMPORTER_VERSION,
		true
	);

}

/**
 * Enqueue styles for front-end.
 *
 * @return void
 */
function styles() {

	wp_enqueue_style(
		'learning_commons_importer_shared',
		style_url( 'shared-style', 'shared' ),
		[],
		LEARNING_COMMONS_IMPORTER_VERSION
	);

	if ( is_admin() ) {
		wp_enqueue_style(
			'learning_commons_importer_admin',
			style_url( 'admin-style', 'admin' ),
			[],
			LEARNING_COMMONS_IMPORTER_VERSION
		);
	} else {
		wp_enqueue_style(
			'learning_commons_importer_frontend',
			style_url( 'style', 'frontend' ),
			[],
			LEARNING_COMMONS_IMPORTER_VERSION
		);
	}

}

/**
 * Enqueue styles for admin.
 *
 * @return void
 */
function admin_styles() {

	wp_enqueue_style(
		'learning_commons_importer_shared',
		style_url( 'shared-style', 'shared' ),
		[],
		LEARNING_COMMONS_IMPORTER_VERSION
	);

	wp_enqueue_style(
		'learning_commons_importer_admin',
		style_url( 'admin-style', 'admin' ),
		[],
		LEARNING_COMMONS_IMPORTER_VERSION
	);

}

/**
 * Enqueue editor styles. Filters the comma-delimited list of stylesheets to load in TinyMCE.
 *
 * @param string $stylesheets Comma-delimited list of stylesheets.
 * @return string
 */
function mce_css( $stylesheets ) {
	if ( ! empty( $stylesheets ) ) {
		$stylesheets .= ',';
	}

	return $stylesheets . LEARNING_COMMONS_IMPORTER_URL . ( ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ?
			'assets/css/frontend/editor-style.css' :
			'dist/css/editor-style.min.css' );
}

/**
 * Add async/defer attributes to enqueued scripts that have the specified script_execution flag.
 *
 * @link https://core.trac.wordpress.org/ticket/12009
 * @param string $tag    The script tag.
 * @param string $handle The script handle.
 * @return string
 */
function script_loader_tag( $tag, $handle ) {
	$script_execution = wp_scripts()->get_data( $handle, 'script_execution' );

	if ( ! $script_execution ) {
		return $tag;
	}

	if ( 'async' !== $script_execution && 'defer' !== $script_execution ) {
		return $tag;
	}

	// Abort adding async/defer for scripts that have this script as a dependency. _doing_it_wrong()?
	foreach ( wp_scripts()->registered as $script ) {
		if ( in_array( $handle, $script->deps, true ) ) {
			return $tag;
		}
	}

	// Add the attribute if it hasn't already been added.
	if ( ! preg_match( ":\s$script_execution(=|>|\s):", $tag ) ) {
		$tag = preg_replace( ':(?=></script>):', " $script_execution", $tag, 1 );
	}

	return $tag;
}
