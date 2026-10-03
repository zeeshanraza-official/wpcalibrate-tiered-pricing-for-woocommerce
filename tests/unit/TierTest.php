<?php
/**
 * Tier Unit Tests.
 *
 * @package WPCalibrate\TieredPricing\Tests\Unit
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPCalibrate\TieredPricing\Data\Tier;

/**
 * Class TierTest
 */
final class TierTest extends TestCase {

	public function test_tier_instantiation_and_matching(): void {
		$tier = new Tier( 5.0, 9.0, Tier::TYPE_FIXED, 25.0, true );

		$this->assertSame( 5.0, $tier->get_min_qty() );
		$this->assertSame( 9.0, $tier->get_max_qty() );
		$this->assertFalse( $tier->is_open_ended() );
		$this->assertSame( 'fixed', $tier->get_type() );
		$this->assertSame( 25.0, $tier->get_value() );
		$this->assertTrue( $tier->is_enabled() );

		// Boundaries.
		$this->assertFalse( $tier->matches( 1 ) );
		$this->assertFalse( $tier->matches( 4.9 ) );
		$this->assertTrue( $tier->matches( 5 ) );
		$this->assertTrue( $tier->matches( 7 ) );
		$this->assertTrue( $tier->matches( 9 ) );
		$this->assertFalse( $tier->matches( 9.1 ) );
		$this->assertFalse( $tier->matches( 10 ) );
	}

	public function test_open_ended_tier(): void {
		$tier = new Tier( 10.0, null, Tier::TYPE_PERCENTAGE, 15.0, true );

		$this->assertTrue( $tier->is_open_ended() );
		$this->assertNull( $tier->get_max_qty() );
		$this->assertFalse( $tier->matches( 9.99 ) );
		$this->assertTrue( $tier->matches( 10 ) );
		$this->assertTrue( $tier->matches( 100 ) );
		$this->assertTrue( $tier->matches( 99999 ) );
	}

	public function test_disabled_tier_never_matches(): void {
		$tier = new Tier( 2.0, 10.0, Tier::TYPE_FIXED, 10.0, false );
		$this->assertFalse( $tier->matches( 5 ) );
	}

	public function test_array_serialization_and_deserialization(): void {
		$original = new Tier( 3.0, 8.0, Tier::TYPE_PERCENTAGE, 20.0, true );
		$array    = $original->to_array();
		$from_arr = Tier::from_array( $array );

		$this->assertSame( $original->get_min_qty(), $from_arr->get_min_qty() );
		$this->assertSame( $original->get_max_qty(), $from_arr->get_max_qty() );
		$this->assertSame( $original->get_type(), $from_arr->get_type() );
		$this->assertSame( $original->get_value(), $from_arr->get_value() );
		$this->assertSame( $original->is_enabled(), $from_arr->is_enabled() );
	}
}
