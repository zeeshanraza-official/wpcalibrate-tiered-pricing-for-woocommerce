<?php
/**
 * Migration Manager.
 *
 * @package WPCalibrate\TieredPricing\Data
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Data;

/**
 * Class MigrationManager
 */
final class MigrationManager {

	/**
	 * Current internal schema version.
	 *
	 * @var string
	 */
	public const CURRENT_SCHEMA_VERSION = '1.0.0';

	/**
	 * Run migrations if needed.
	 *
	 * @return void
	 */
	public static function run_migrations(): void {
		$installed_schema = (string) get_option( 'wpcttp_schema_version', '0.0.0' );

		if ( version_compare( $installed_schema, self::CURRENT_SCHEMA_VERSION, '<' ) ) {
			self::migrate( $installed_schema );
			update_option( 'wpcttp_schema_version', self::CURRENT_SCHEMA_VERSION, false );
		}

		$installed_version = (string) get_option( 'wpcttp_version', '0.0.0' );
		if ( version_compare( $installed_version, WPCALIBRATE_TIERED_PRICING_VERSION, '<' ) ) {
			update_option( 'wpcttp_version', WPCALIBRATE_TIERED_PRICING_VERSION, false );
		}
	}

	/**
	 * Run step-by-step schema migrations.
	 *
	 * @param string $from_version Installed version.
	 * @return void
	 */
	private static function migrate( string $from_version ): void {
		if ( version_compare( $from_version, '1.0.0', '<' ) ) {
			// Version 1.0.0 initial baseline setup.
			delete_transient( 'wpcttp_global_rule_cache' );
			delete_transient( 'wpcttp_category_rules_cache' );
		}
	}
}
