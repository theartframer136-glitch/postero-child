<?php

namespace QuadLayers\IGG\Services;

/**
 * Simple file-based logger for Instagram Gallery
 * Logs token renewal and email sending activities to files
 */
class File_Logger {

	protected static $instance;
	private $log_dir;
	private $max_file_size = 5242880; // 5MB
	private $max_files     = 5;

	private function __construct() {
		$this->setup_log_directory();
	}

	/**
	 * Setup log directory
	 */
	private function setup_log_directory() {
		$upload_dir    = wp_upload_dir();
		$this->log_dir = $upload_dir['basedir'] . '/insta-gallery-logs/';

		if ( ! file_exists( $this->log_dir ) ) {
			wp_mkdir_p( $this->log_dir );

			// Create .htaccess to protect log files
			$htaccess_content = "Order deny,allow\nDeny from all\n";
			file_put_contents( $this->log_dir . '.htaccess', $htaccess_content );

			// Create index.php to prevent directory listing
			file_put_contents( $this->log_dir . 'index.php', '<?php // Silence is golden' );
		}
	}

	/**
	 * Log a message to file
	 *
	 * @param string $level Log level (INFO, SUCCESS, WARNING, ERROR)
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @param string $category Log category (token_renewal, email, general)
	 */
	public function log( $level, $message, $context = array(), $category = 'general' ) {
		$timestamp = current_time( 'Y-m-d H:i:s' );
		$log_entry = array(
			'timestamp' => $timestamp,
			'level'     => $level,
			'message'   => $message,
			'context'   => $context,
		);

		$formatted_entry = $this->format_log_entry( $log_entry );
		$this->write_to_file( $category, $formatted_entry );
	}

	/**
	 * Format log entry for file output
	 *
	 * @param array $entry Log entry data
	 * @return string Formatted log entry
	 */
	private function format_log_entry( $entry ) {
		$formatted = sprintf(
			'[%s] [%s] %s',
			$entry['timestamp'],
			$entry['level'],
			$entry['message']
		);

		if ( ! empty( $entry['context'] ) ) {
			$formatted .= ' | Context: ' . wp_json_encode( $entry['context'] );
		}

		$formatted .= "\n";

		return $formatted;
	}

	/**
	 * Write log entry to file
	 *
	 * @param string $category Log category
	 * @param string $formatted_entry Formatted log entry
	 */
	private function write_to_file( $category, $formatted_entry ) {
		$filename = $this->log_dir . 'insta-gallery-' . $category . '.log';

		// Check file size and rotate if necessary
		if ( file_exists( $filename ) && filesize( $filename ) > $this->max_file_size ) {
			$this->rotate_log_file( $filename );
		}

		// Write to file
		file_put_contents( $filename, $formatted_entry, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Rotate log file when it gets too large
	 *
	 * @param string $filename Current log file path
	 */
	private function rotate_log_file( $filename ) {
		$base_name = str_replace( '.log', '', $filename );

		// Remove oldest file if we have too many
		$oldest_file = $base_name . '-' . $this->max_files . '.log';
		if ( file_exists( $oldest_file ) ) {
			unlink( $oldest_file );
		}

		// Rotate existing files
		for ( $i = $this->max_files - 1; $i >= 1; $i-- ) {
			$old_file = $base_name . '-' . $i . '.log';
			$new_file = $base_name . '-' . ( $i + 1 ) . '.log';

			if ( file_exists( $old_file ) ) {
				rename( $old_file, $new_file );
			}
		}

		// Move current file to -1
		rename( $filename, $base_name . '-1.log' );
	}



	/**
	 * Get recent logs from a specific category
	 *
	 * @param string $category Log category
	 * @param int    $lines Number of lines to retrieve
	 * @return array Log entries
	 */
	public function get_recent_logs( $category = 'general', $lines = 50 ) {
		$filename = $this->log_dir . 'insta-gallery-' . $category . '.log';

		if ( ! file_exists( $filename ) ) {
			return array();
		}

		$file_lines = file( $filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		return array_slice( array_reverse( $file_lines ), 0, $lines );
	}

	/**
	 * Get log file path for a category
	 *
	 * @param string $category Log category
	 * @return string Log file path
	 */
	public function get_log_file_path( $category = 'general' ) {
		return $this->log_dir . 'insta-gallery-' . $category . '.log';
	}

	/**
	 * Clear logs for a specific category
	 *
	 * @param string $category Log category
	 * @return bool Success status
	 */
	public function clear_logs( $category = 'general' ) {
		$filename = $this->log_dir . 'insta-gallery-' . $category . '.log';

		if ( file_exists( $filename ) ) {
			return unlink( $filename );
		}

		return true;
	}

	/**
	 * Get log directory path
	 *
	 * @return string Log directory path
	 */
	public function get_log_directory() {
		return $this->log_dir;
	}

	/**
	 * Get singleton instance
	 *
	 * @return File_Logger
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
