<?php
/**
 * Pricing Calculator Unit Tests.
 *
 * @package WPCalibrate\TieredPricing\Tests\Unit
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WC_Product;
use WPCalibrate\TieredPricing\Data\Tier;
use WPCalibrate\TieredPricing\Pricing\PricingCalculator;

/**
 * Class PricingCalculatorTest
 */
final class PricingCalculatorTest extends TestCase {

	private WC_Product $product_mock;

	protected function setUp(): void {
		parent::setUp();
		$this->product_mock = $this->createMock( WC_Product::class );
	}

	public function test_fixed_price_tier_calculation(): void {
		$base_price = 100.0;
		$tier       = new Tier( 5.0, 9.0, Tier::TYPE_FIXED, 85.0 );

		$res = PricingCalculator::calculate_tier_price( $base_price, $tier, $this->product_mock, 6 );

		$this->assertTrue( $res['is_applied'] );
		$this->assertSame( 85.0, $res['adjusted_unit_price'] );
		$this->assertSame( 15.0, $res['discount_amount'] );
		$this->assertSame( 15.0, $res['discount_percentage'] );
	}

	public function test_percentage_tier_calculation(): void {
		$base_price = 100.0;
		$tier       = new Tier( 10.0, null, Tier::TYPE_PERCENTAGE, 25.0 );

		$res = PricingCalculator::calculate_tier_price( $base_price, $tier, $this->product_mock, 10 );

		$this->assertTrue( $res['is_applied'] );
		$this->assertSame( 75.0, $res['adjusted_unit_price'] );
		$this->assertSame( 25.0, $res['discount_amount'] );
		$this->assertSame( 25.0, $res['discount_percentage'] );
	}

	public function test_null_tier_leaves_price_unaltered(): void {
		$base_price = 49.99;
		$res        = PricingCalculator::calculate_tier_price( $base_price, null, $this->product_mock, 2 );

		$this->assertFalse( $res['is_applied'] );
		$this->assertSame( 49.99, $res['adjusted_unit_price'] );
		$this->assertSame( 0.0, $res['discount_amount'] );
		$this->assertSame( 0.0, $res['discount_percentage'] );
	}

	public function test_full_percentage_discount_is_zero(): void {
		$base_price = 80.0;
		$tier       = new Tier( 20.0, null, Tier::TYPE_PERCENTAGE, 100.0 );

		$res = PricingCalculator::calculate_tier_price( $base_price, $tier, $this->product_mock, 25 );

		$this->assertTrue( $res['is_applied'] );
		$this->assertSame( 0.0, $res['adjusted_unit_price'] );
		$this->assertSame( 80.0, $res['discount_amount'] );
		$this->assertSame( 100.0, $res['discount_percentage'] );
	}
}
