<?php
/**
 * WooCommerce Diagnostic Logger.
 *
 * @package WPCalibrate\TieredPricing\Support
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Support;

/**
 * Class Logger
 */
final class Logger {

	/**
	 * Log source identifier.
	 */
	public const SOURCE = 'wpcalibrate-tiered-pricing';

	/**
	 * Check if debug logging is enabled in settings.
	 *
	 * @return bool
	 */
	public static function is_debug_enabled(): bool {
		return 'yes' === get_option( 'wpcttp_debug_logging', 'no' );
	}

	/**
	 * Log debug diagnostic message.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Context data (non-sensitive).
	 * @return void
	 */
	public static function debug( string $message, array $context = array() ): void {
		if ( ! self::is_debug_enabled() ) {
			return;
		}

		self::log( 'debug', $message, $context );
	}

	/**
	 * Log info message.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Context data.
	 * @return void
	 */
	public static function info( string $message, array $context = array() ): void {
		self::log( 'info', $message, $context );
	}

	/**
	 * Log warning message.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Context data.
	 * @return void
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::log( 'warning', $message, $context );
	}

	/**
	 * Log error message.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Context data.
	 * @return void
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( 'error', $message, $context );
	}

	/**
	 * Dispatch log entry to WooCommerce logger.
	 *
	 * @param string               $level Log level.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	private static function log( string $level, string $message, array $context = array() ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		// Ensure sensitive fields are stripped.
		unset( $context['license_key'], $context['password'], $context['credit_card'], $context['token'], $context['cookie'], $context['nonce'] );

		$logger = wc_get_logger();
		$context['source'] = self::SOURCE;

		$logger->log( $level, $message, $context );
	}
}
