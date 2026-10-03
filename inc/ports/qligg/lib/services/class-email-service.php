<?php

namespace QuadLayers\IGG\Services;

use QuadLayers\IGG\Services\File_Logger;

final class Email_Service {

	protected static $instance;

	/**
	 * Send email with retry logic
	 *
	 * @param string      $email_type Type of email being sent
	 * @param string      $recipient Email recipient
	 * @param string      $subject Email subject
	 * @param string      $message Email message
	 * @param File_Logger $file_logger Logger instance
	 * @param int         $max_retries Maximum number of retry attempts
	 * @param int         $retry_delay Delay between retries in seconds
	 * @return bool Final result of email sending
	 */
	public function send_email_with_retry( $email_type, $recipient, $subject, $message, $file_logger, $max_retries = 3, $retry_delay = 5 ) {
		$attempt      = 1;
		$email_result = false;

		// Log initial attempt
		$file_logger->log(
			'INFO',
			'Email sending attempt',
			array(
				'type'      => $email_type,
				'recipient' => $recipient,
				'subject'   => $subject,
			),
			'email'
		);

		while ( $attempt <= $max_retries && ! $email_result ) {
			// Log retry attempt if not the first attempt
			if ( $attempt > 1 ) {
				$file_logger->log(
					'INFO',
					'Email retry attempt',
					array(
						'type'        => $email_type,
						'recipient'   => $recipient,
						'attempt'     => $attempt,
						'max_retries' => $max_retries,
					),
					'email'
				);

				// Wait before retry (except for first attempt)
				sleep( $retry_delay );
			}

			// Attempt to send email
			$email_result = wp_mail( $recipient, $subject, $message );

			if ( $email_result ) {
				// Success - log and break
				$file_logger->log(
					'SUCCESS',
					'Email sent successfully',
					array(
						'type'      => $email_type,
						'recipient' => $recipient,
					),
					'email'
				);

				if ( $attempt > 1 ) {
					$file_logger->log(
						'SUCCESS',
						'Email sent successfully after retry',
						array(
							'type'               => $email_type,
							'recipient'          => $recipient,
							'successful_attempt' => $attempt,
							'total_attempts'     => $attempt,
						),
						'email'
					);
				}

				break;
			} else {
				// Failed attempt - log the failure
				$file_logger->log(
					'WARNING',
					'Email sending attempt failed',
					array(
						'type'        => $email_type,
						'recipient'   => $recipient,
						'attempt'     => $attempt,
						'max_retries' => $max_retries,
						'will_retry'  => $attempt < $max_retries,
					),
					'email'
				);

				++$attempt;
			}
		}

		// Log final result if all attempts failed
		if ( ! $email_result ) {
			$file_logger->log(
				'ERROR',
				'Email sending failed',
				array(
					'type'      => $email_type,
					'recipient' => $recipient,
					'error'     => sprintf( 'All %d email sending attempts failed', $max_retries ),
				),
				'email'
			);

			$file_logger->log(
				'ERROR',
				'Email sending completely failed after all retries',
				array(
					'type'           => $email_type,
					'recipient'      => $recipient,
					'total_attempts' => $max_retries,
					'retry_delay'    => $retry_delay,
				),
				'email'
			);
		}

		return $email_result;
	}

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
