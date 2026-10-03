<?php
/**
 * Cart Pricing Integration Tests.
 *
 * @package WPCalibrate\TieredPricing\Tests\Integration
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WC_Cart;
use WC_Product;
use WPCalibrate\TieredPricing\Cart\CartPricing;
use WPCalibrate\TieredPricing\Contracts\PricingEngineInterface;
use WPCalibrate\TieredPricing\Data\PricingResult;
use WPCalibrate\TieredPricing\Data\PricingRule;
use WPCalibrate\TieredPricing\Data\Tier;

/**
 * Class CartPricingTest
 */
final class CartPricingTest extends TestCase {

	public function test_prevents_compounding_on_repeated_cart_recalculations(): void {
		$engine_mock = $this->createMock( PricingEngineInterface::class );

		// Product base regular price $100
		$product = new class extends WC_Product {
			private float $price = 100.0;
			public function get_price( string $context = 'view' ): string {
				return (string) $this->price;
			}
			public function set_price( $price ): void {
				$this->price = (float) $price;
			}
		};

		// 10% discount gives $90
		$tier   = new Tier( 5.0, null, Tier::TYPE_PERCENTAGE, 10.0, true );
		$result = new PricingResult( 1, 0, 'rule_1', 'product', 'individual', 'percentage', $tier, 5, 100.0, 90.0, 10.0, 10.0, true );

		$engine_mock->method( 'calculate' )->willReturn( $result );

		$cart_mock = $this->createMock( WC_Cart::class );
		$cart_item = array(
			'data'     => $product,
			'quantity' => 5,
		);

		$cart_mock->method( 'get_cart' )->willReturn( array( 'item_1' => $cart_item ) );

		$cart_pricing = new CartPricing( $engine_mock );

		// First calculation pass
		$cart_pricing->calculate_cart_totals( $cart_mock );
		$this->assertSame( 90.0, (float) $product->get_price() );

		// Second calculation pass (simulating page reload or shipping recalculation)
		// Product should NOT compound (e.g. 90 -> 81), but must evaluate against original 100.0 and remain 90.0
		$cart_pricing->calculate_cart_totals( $cart_mock );
		$this->assertSame( 90.0, (float) $product->get_price() );
	}
}
