<?php
/**
 * Quantity Resolver Unit Tests.
 *
 * @package WPCalibrate\TieredPricing\Tests\Unit
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WC_Product;
use WC_Product_Variation;
use WPCalibrate\TieredPricing\Data\PricingRule;
use WPCalibrate\TieredPricing\Data\Tier;
use WPCalibrate\TieredPricing\Rules\QuantityResolver;

/**
 * Class QuantityResolverTest
 */
final class QuantityResolverTest extends TestCase {

	private QuantityResolver $resolver;

	protected function setUp(): void {
		parent::setUp();
		$this->resolver = new QuantityResolver();
	}

	public function test_individual_scope(): void {
		$product = $this->createMock( WC_Product::class );
		$rule    = new PricingRule( '1', PricingRule::SOURCE_PRODUCT, 10, 'Rule', true, PricingRule::SCOPE_INDIVIDUAL, 10, array() );

		$qty = $this->resolver->resolve_quantity( $product, $rule, 7 );
		$this->assertSame( 7.0, (float) $qty );
	}

	public function test_variable_combined_scope_aggregates_sibling_variations(): void {
		// Mock variation 1 (parent 100, id 101, qty 3)
		$var1 = $this->createMock( WC_Product_Variation::class );
		$var1->method( 'is_type' )->with( 'variation' )->willReturn( true );
		$var1->method( 'get_parent_id' )->willReturn( 100 );
		$var1->method( 'get_id' )->willReturn( 101 );

		// Mock variation 2 (parent 100, id 102, qty 4)
		$var2 = $this->createMock( WC_Product_Variation::class );
		$var2->method( 'is_type' )->with( 'variation' )->willReturn( true );
		$var2->method( 'get_parent_id' )->willReturn( 100 );
		$var2->method( 'get_id' )->willReturn( 102 );

		// Mock variation 3 from different parent 200 (id 201, qty 10)
		$var3 = $this->createMock( WC_Product_Variation::class );
		$var3->method( 'is_type' )->with( 'variation' )->willReturn( true );
		$var3->method( 'get_parent_id' )->willReturn( 200 );
		$var3->method( 'get_id' )->willReturn( 201 );

		$cart_items = array(
			'item1' => array( 'data' => $var1, 'quantity' => 3 ),
			'item2' => array( 'data' => $var2, 'quantity' => 4 ),
			'item3' => array( 'data' => $var3, 'quantity' => 10 ),
		);

		$rule = new PricingRule( '2', PricingRule::SOURCE_PRODUCT, 100, 'Var Rule', true, PricingRule::SCOPE_VARIABLE_COMBINED, 10, array() );

		// Resolving for var1 should sum var1 (3) + var2 (4) = 7, excluding var3 (parent 200)
		$aggregate = $this->resolver->resolve_quantity( $var1, $rule, 3, $cart_items );
		$this->assertSame( 7.0, (float) $aggregate );
	}

	public function test_cart_wide_scope_aggregates_all_items(): void {
		$prod1 = $this->createMock( WC_Product::class );
		$prod2 = $this->createMock( WC_Product::class );

		$cart_items = array(
			'a' => array( 'data' => $prod1, 'quantity' => 5 ),
			'b' => array( 'data' => $prod2, 'quantity' => 8 ),
		);

		$rule = new PricingRule( 'global', PricingRule::SOURCE_GLOBAL, 0, 'Global', true, PricingRule::SCOPE_CART_WIDE, 10, array() );

		$aggregate = $this->resolver->resolve_quantity( $prod1, $rule, 5, $cart_items );
		$this->assertSame( 13.0, (float) $aggregate );
	}
}
