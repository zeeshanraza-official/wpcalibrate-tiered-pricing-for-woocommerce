<?php
/**
 * Coupon Integration Tests.
 *
 * @package WPCalibrate\TieredPricing\Tests\Integration
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WC_Coupon;
use WC_Product;
use WPCalibrate\TieredPricing\Cart\CouponHandler;

/**
 * Class CouponIntegrationTest
 */
final class CouponIntegrationTest extends TestCase {

	private CouponHandler $handler;

	protected function setUp(): void {
		parent::setUp();
		$this->handler = new CouponHandler();
	}

	public function test_coupon_allowed_when_strategy_is_allow(): void {
		update_option( 'wpcttp_coupon_strategy', CouponHandler::STRATEGY_ALLOW );

		$product = $this->createMock( WC_Product::class );
		$coupon  = $this->createMock( WC_Coupon::class );
		$values  = array( '_wpcttp_tiered_price_applied' => true );

		$valid = $this->handler->filter_coupon_valid_for_product( true, $product, $coupon, $values );
		$this->assertTrue( $valid );
	}

	public function test_coupon_prevented_only_for_tiered_items_when_strategy_is_prevent(): void {
		update_option( 'wpcttp_coupon_strategy', CouponHandler::STRATEGY_PREVENT );

		$product = $this->createMock( WC_Product::class );
		$coupon  = $this->createMock( WC_Coupon::class );

		// 1. Tiered line item -> should return false
		$tiered_values = array( '_wpcttp_tiered_price_applied' => true );
		$this->assertFalse( $this->handler->filter_coupon_valid_for_product( true, $product, $coupon, $tiered_values ) );

		// 2. Normal non-tiered line item -> should remain true
		$normal_values = array( '_wpcttp_tiered_price_applied' => false );
		$this->assertTrue( $this->handler->filter_coupon_valid_for_product( true, $product, $coupon, $normal_values ) );
	}
}
