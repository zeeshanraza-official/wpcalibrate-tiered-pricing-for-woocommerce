<?php
/**
 * Rule Precedence Resolver Unit Tests.
 *
 * @package WPCalibrate\TieredPricing\Tests\Unit
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WC_Product;
use WC_Product_Variation;
use WPCalibrate\TieredPricing\Contracts\RuleRepositoryInterface;
use WPCalibrate\TieredPricing\Data\PricingRule;
use WPCalibrate\TieredPricing\Data\Tier;
use WPCalibrate\TieredPricing\Rules\RuleResolver;

/**
 * Class RuleResolverTest
 */
final class RuleResolverTest extends TestCase {

	public function test_variation_rule_takes_precedence_over_parent_rule(): void {
		$repo = $this->createMock( RuleRepositoryInterface::class );

		$parent_rule = new PricingRule( 'p1', PricingRule::SOURCE_PRODUCT, 10, 'Parent Rule', true, PricingRule::SCOPE_INDIVIDUAL, 10, array() );
		$var_rule    = new PricingRule( 'v1', PricingRule::SOURCE_VARIATION, 11, 'Var Rule', true, PricingRule::SCOPE_INDIVIDUAL, 10, array(), true );

		$variation = $this->createMock( WC_Product_Variation::class );
		$variation->method( 'is_type' )->with( 'variation' )->willReturn( true );
		$variation->method( 'get_id' )->willReturn( 11 );
		$variation->method( 'get_parent_id' )->willReturn( 10 );

		$repo->method( 'get_for_variation' )->with( 11 )->willReturn( $var_rule );
		$repo->method( 'get_for_product' )->with( 10 )->willReturn( $parent_rule );

		$resolver = new RuleResolver( $repo );
		$winning  = $resolver->resolve( $variation );

		$this->assertNotNull( $winning );
		$this->assertSame( 'v1', $winning->get_id() );
		$this->assertSame( PricingRule::SOURCE_VARIATION, $winning->get_source() );
	}

	public function test_parent_rule_inherited_when_variation_has_no_rule(): void {
		$repo = $this->createMock( RuleRepositoryInterface::class );

		$parent_rule = new PricingRule( 'p1', PricingRule::SOURCE_PRODUCT, 10, 'Parent Rule', true, PricingRule::SCOPE_INDIVIDUAL, 10, array() );

		$variation = $this->createMock( WC_Product_Variation::class );
		$variation->method( 'is_type' )->with( 'variation' )->willReturn( true );
		$variation->method( 'get_id' )->willReturn( 12 );
		$variation->method( 'get_parent_id' )->willReturn( 10 );

		$repo->method( 'get_for_variation' )->with( 12 )->willReturn( null );
		$repo->method( 'get_for_product' )->with( 10 )->willReturn( $parent_rule );

		$resolver = new RuleResolver( $repo );
		$winning  = $resolver->resolve( $variation );

		$this->assertNotNull( $winning );
		$this->assertSame( 'p1', $winning->get_id() );
		$this->assertSame( PricingRule::SOURCE_PRODUCT, $winning->get_source() );
	}
}
