<?php
/**
 * Rule Validator and Normalizer Unit Tests.
 *
 * @package WPCalibrate\TieredPricing\Tests\Unit
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPCalibrate\TieredPricing\Data\Tier;
use WPCalibrate\TieredPricing\Rules\RuleValidator;

/**
 * Class RuleValidatorTest
 */
final class RuleValidatorTest extends TestCase {

	public function test_normalizes_unordered_tiers_ascending(): void {
		$raw = array(
			array(
				'min_qty' => 10,
				'max_qty' => '',
				'type'    => 'percentage',
				'value'   => 20,
			),
			array(
				'min_qty' => 5,
				'max_qty' => 9,
				'type'    => 'percentage',
				'value'   => 10,
			),
			array(
				'min_qty' => 2,
				'max_qty' => 4,
				'type'    => 'percentage',
				'value'   => 5,
			),
		);

		$normalized = RuleValidator::normalize_tiers( $raw );

		$this->assertCount( 3, $normalized );
		$this->assertSame( 2.0, $normalized[0]->get_min_qty() );
		$this->assertSame( 4.0, $normalized[0]->get_max_qty() );

		$this->assertSame( 5.0, $normalized[1]->get_min_qty() );
		$this->assertSame( 9.0, $normalized[1]->get_max_qty() );

		$this->assertSame( 10.0, $normalized[2]->get_min_qty() );
		$this->assertNull( $normalized[2]->get_max_qty() );
	}

	public function test_resolves_overlapping_ranges(): void {
		// Tier 1 specifies 2-10, but Tier 2 starts at 6.
		// Normalizer clamps Tier 1 max to (6 - 1) = 5.
		$raw = array(
			array(
				'min_qty' => 2,
				'max_qty' => 10,
				'type'    => 'fixed',
				'value'   => 40,
			),
			array(
				'min_qty' => 6,
				'max_qty' => null,
				'type'    => 'fixed',
				'value'   => 30,
			),
		);

		$normalized = RuleValidator::normalize_tiers( $raw );

		$this->assertCount( 2, $normalized );
		$this->assertSame( 2.0, $normalized[0]->get_min_qty() );
		$this->assertSame( 5.0, $normalized[0]->get_max_qty() );
		$this->assertSame( 6.0, $normalized[1]->get_min_qty() );
		$this->assertNull( $normalized[1]->get_max_qty() );
	}

	public function test_rejects_or_clamps_negative_and_excess_percentages(): void {
		$raw = array(
			array(
				'min_qty' => 5,
				'max_qty' => 10,
				'type'    => 'percentage',
				'value'   => 150, // exceeds 100
			),
			array(
				'min_qty' => 11,
				'max_qty' => null,
				'type'    => 'fixed',
				'value'   => -5, // negative fixed
			),
		);

		$normalized = RuleValidator::normalize_tiers( $raw );

		$this->assertSame( 100.0, $normalized[0]->get_value() );
		$this->assertSame( 0.0, $normalized[1]->get_value() );
	}

	public function test_skips_disabled_tiers(): void {
		$raw = array(
			array(
				'min_qty' => 5,
				'max_qty' => 10,
				'type'    => 'fixed',
				'value'   => 20,
				'enabled' => false,
			),
		);

		$normalized = RuleValidator::normalize_tiers( $raw );
		$this->assertEmpty( $normalized );
	}
}
