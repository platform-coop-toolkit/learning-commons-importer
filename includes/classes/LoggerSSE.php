<?php
/**
 * SSE logger class.
 *
 * @package LearningCommonsImporter
 */

namespace LearningCommonsImporter;

/**
 * SSE logger class.
 */
class LoggerSSE extends Logger {
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
		$data = compact( 'level', 'message' );

		switch ( $level ) {
			case 'emergency':
			case 'alert':
			case 'critical':
			case 'error':
			case 'warning':
			case 'notice':
			case 'info':
				echo "event: log\n";
				echo 'data: ' . wp_json_encode( $data ) . "\n\n";
				flush();
				break;

			case 'debug':
				if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
					echo "event: log\n";
					echo 'data: ' . wp_json_encode( $data ) . "\n\n";
					flush();
					break;
				}
				break;
		}
	}
}
