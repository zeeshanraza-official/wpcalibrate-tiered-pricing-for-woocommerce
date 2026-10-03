<?php
/**
 * Boundary Unit Tests.
 *
 * @package WPCalibrate\TieredPricing\Tests\Unit
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPCalibrate\TieredPricing\Data\PricingRule;
use WPCalibrate\TieredPricing\Data\Tier;

/**
 * Class BoundaryTest
 */
final class BoundaryTest extends TestCase {

	/**
	 * Configuration:
	 * Tier A: 5 - 9 (Fixed $40)
	 * Tier B: 10+   (Fixed $30)
	 * Base price: $50
	 */
	private PricingRule $rule;

	protected function setUp(): void {
		parent::setUp();
		$tiers = array(
			new Tier( 5.0, 9.0, Tier::TYPE_FIXED, 40.0, true ),
			new Tier( 10.0, null, Tier::TYPE_FIXED, 30.0, true ),
		);

		$this->rule = new PricingRule(
			'test_rule',
			PricingRule::SOURCE_PRODUCT,
			101,
			'Test Product Rule',
			true,
			PricingRule::SCOPE_INDIVIDUAL,
			10,
			$tiers
		);
	}

	public function test_boundary_qty_1(): void {
		$matched = $this->rule->find_matching_tier( 1 );
		$this->assertNull( $matched );
	}

	public function test_boundary_qty_4(): void {
		$matched = $this->rule->find_matching_tier( 4 );
		$this->assertNull( $matched );
	}

	public function test_boundary_qty_5(): void {
		$matched = $this->rule->find_matching_tier( 5 );
		$this->assertNotNull( $matched );
		$this->assertSame( 40.0, $matched->get_value() );
		$this->assertSame( 5.0, $matched->get_min_qty() );
		$this->assertSame( 9.0, $matched->get_max_qty() );
	}

	public function test_boundary_qty_9(): void {
		$matched = $this->rule->find_matching_tier( 9 );
		$this->assertNotNull( $matched );
		$this->assertSame( 40.0, $matched->get_value() );
	}

	public function test_boundary_qty_10(): void {
		$matched = $this->rule->find_matching_tier( 10 );
		$this->assertNotNull( $matched );
		$this->assertSame( 30.0, $matched->get_value() );
		$this->assertSame( 10.0, $matched->get_min_qty() );
		$this->assertTrue( $matched->is_open_ended() );
	}

	public function test_boundary_large_quantities(): void {
		$quantities = array( 50, 100, 1000, 50000 );
		foreach ( $quantities as $qty ) {
			$matched = $this->rule->find_matching_tier( $qty );
			$this->assertNotNull( $matched, "Failed matching for large qty $qty" );
			$this->assertSame( 30.0, $matched->get_value() );
		}
	}
}
