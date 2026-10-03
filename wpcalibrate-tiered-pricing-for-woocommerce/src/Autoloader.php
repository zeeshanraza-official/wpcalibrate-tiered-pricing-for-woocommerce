<?php
/**
 * Autoloader for WPCalibrate Tiered Pricing.
 *
 * @package WPCalibrate\TieredPricing
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing;

/**
 * Class Autoloader
 */
final class Autoloader {

	/**
	 * Prefix.
	 *
	 * @var string
	 */
	private const PREFIX = 'WPCalibrate\\TieredPricing\\';

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload callback.
	 *
	 * @param string $class Class name to load.
	 * @return void
	 */
	public static function autoload( string $class ): void {
		if ( ! str_starts_with( $class, self::PREFIX ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( self::PREFIX ) );
		$file           = __DIR__ . '/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
