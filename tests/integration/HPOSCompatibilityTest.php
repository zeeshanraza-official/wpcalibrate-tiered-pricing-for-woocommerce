<?php
/**
 * HPOS & Cart Blocks Compatibility Declaration Tests.
 *
 * @package WPCalibrate\TieredPricing\Tests\Integration
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Class HPOSCompatibilityTest
 */
final class HPOSCompatibilityTest extends TestCase {

	public function test_compatibility_declared_in_main_plugin(): void {
		$main_file = file_get_contents( __DIR__ . '/../../wpcalibrate-tiered-pricing-for-woocommerce.php' );

		$this->assertStringContainsString( 'custom_order_tables', $main_file, 'Must declare custom_order_tables compatibility' );
		$this->assertStringContainsString( 'cart_checkout_blocks', $main_file, 'Must declare cart_checkout_blocks compatibility' );
		$this->assertStringContainsString( 'declare_compatibility', $main_file, 'Must use FeaturesUtil::declare_compatibility' );
	}
}
