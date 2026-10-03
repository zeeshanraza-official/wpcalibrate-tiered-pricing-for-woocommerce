<?php
/**
 * Plugin Deactivator.
 *
 * @package WPCalibrate\TieredPricing\Activation
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Activation;

/**
 * Class Deactivator
 */
final class Deactivator {

	/**
	 * Run deactivation routine.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Clear plugin transient caches.
		delete_transient( 'wpcttp_global_rule_cache' );
		delete_transient( 'wpcttp_category_rules_cache' );

		// Do not delete merchant configuration or rules.
	}
}
