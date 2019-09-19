<?php
/**
 * HTML logger class.
 *
 * @package LearningCommonsImporter
 */

namespace LearningCommonsImporter;

/**
 * HTML logger class.
 */
class LoggerHTML extends Logger {
	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed  $level The log level.
	 * @param string $message The message.
	 * @param array  $context The context for this message.
	 *
	 * @return void
	 */
	public function log( $level, $message, array $context = array() ) {
		switch ( $level ) {
			case 'emergency':
			case 'alert':
			case 'critical':
				echo '<p><strong>' . esc_attr__( 'Sorry, there has been an error.', 'learning-commons-importer' ) . '</strong><br />';
				echo esc_html( $message );
				echo '</p>';
				break;

			case 'error':
			case 'warning':
			case 'notice':
			case 'info':
				echo '<p>' . esc_html( $message ) . '</p>';
				break;

			case 'debug':
				if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
					echo '<p class="debug">' . esc_html( $message ) . '</p>';
				}
				break;
		}
	}
}
