<?php
/**
 * Rule Validator and Normalizer.
 *
 * @package WPCalibrate\TieredPricing\Rules
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Rules;

use WPCalibrate\TieredPricing\Data\Tier;

/**
 * Class RuleValidator
 */
final class RuleValidator {

	/**
	 * Validate a raw rule array from request/form input.
	 *
	 * @param array<string, mixed> $rule_data Raw input data.
	 * @return array{valid: bool, errors: string[]}
	 */
	public static function validate( array $rule_data ): array {
		$errors = array();

		if ( empty( $rule_data['tiers'] ) || ! is_array( $rule_data['tiers'] ) ) {
			return array(
				'valid'  => true,
				'errors' => array(),
			);
		}

		foreach ( $rule_data['tiers'] as $index => $tier_raw ) {
			$line_num = $index + 1;

			if ( ! is_array( $tier_raw ) ) {
				$errors[] = sprintf(
					/* translators: %d: Tier index number */
					__( 'Tier #%d is malformed.', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
					$line_num
				);
				continue;
			}

			$min_qty = isset( $tier_raw['min_qty'] ) ? (float) $tier_raw['min_qty'] : 0.0;
			if ( $min_qty < 1.0 ) {
				$errors[] = sprintf(
					/* translators: %d: Tier index number */
					__( 'Tier #%d: Minimum quantity must be at least 1.', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
					$line_num
				);
			}

			if ( isset( $tier_raw['max_qty'] ) && '' !== $tier_raw['max_qty'] && null !== $tier_raw['max_qty'] ) {
				$max_qty = (float) $tier_raw['max_qty'];
				if ( $max_qty < $min_qty ) {
					$errors[] = sprintf(
						/* translators: %d: Tier index number */
						__( 'Tier #%d: Maximum quantity cannot be lower than minimum quantity.', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
						$line_num
					);
				}
			}

			$type  = $tier_raw['type'] ?? Tier::TYPE_FIXED;
			$value = isset( $tier_raw['value'] ) ? (float) $tier_raw['value'] : 0.0;

			if ( $value < 0.0 ) {
				$errors[] = sprintf(
					/* translators: %d: Tier index number */
					__( 'Tier #%d: Price or discount value cannot be negative.', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
					$line_num
				);
			}

			if ( Tier::TYPE_PERCENTAGE === $type && $value > 100.0 ) {
				$errors[] = sprintf(
					/* translators: %d: Tier index number */
					__( 'Tier #%d: Percentage discount cannot exceed 100%%.', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
					$line_num
				);
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Normalize raw tier input into a clean, deterministically sorted, non-overlapping array of Tier objects.
	 *
	 * @param array<int, array<string, mixed>> $raw_tiers List of raw tier data arrays.
	 * @return array<Tier>
	 */
	public static function normalize_tiers( array $raw_tiers ): array {
		if ( empty( $raw_tiers ) ) {
			return array();
		}

		$parsed = array();

		foreach ( $raw_tiers as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}

			$min_qty = isset( $raw['min_qty'] ) ? (float) $raw['min_qty'] : 1.0;
			if ( $min_qty < 1.0 ) {
				$min_qty = 1.0;
			}

			$max_qty = null;
			if ( isset( $raw['max_qty'] ) && '' !== $raw['max_qty'] && null !== $raw['max_qty'] ) {
				$max_val = (float) $raw['max_qty'];
				if ( $max_val >= $min_qty ) {
					$max_qty = $max_val;
				}
			}

			$type = isset( $raw['type'] ) && Tier::TYPE_PERCENTAGE === $raw['type']
				? Tier::TYPE_PERCENTAGE
				: Tier::TYPE_FIXED;

			$value = isset( $raw['value'] ) ? (float) $raw['value'] : 0.0;
			if ( $value < 0.0 ) {
				$value = 0.0;
			}
			if ( Tier::TYPE_PERCENTAGE === $type && $value > 100.0 ) {
				$value = 100.0;
			}

			$enabled = ! isset( $raw['enabled'] ) || (bool) $raw['enabled'] || 'yes' === $raw['enabled'] || '1' === $raw['enabled'];

			if ( ! $enabled ) {
				continue;
			}

			$parsed[] = array(
				'min_qty' => $min_qty,
				'max_qty' => $max_qty,
				'type'    => $type,
				'value'   => $value,
				'enabled' => true,
			);
		}

		if ( empty( $parsed ) ) {
			return array();
		}

		// Sort deterministically ascending by min_qty.
		usort(
			$parsed,
			static function ( array $a, array $b ): int {
				if ( $a['min_qty'] === $b['min_qty'] ) {
					if ( null === $a['max_qty'] ) {
						return 1;
					}
					if ( null === $b['max_qty'] ) {
						return -1;
					}
					return $a['max_qty'] <=> $b['max_qty'];
				}
				return $a['min_qty'] <=> $b['min_qty'];
			}
		);

		// Resolve overlaps:
		// When tier i has a max_qty that extends into or beyond tier i+1's min_qty,
		// clamp tier i's max_qty to tier i+1's min_qty - 1.
		// If tier i has null max_qty (open-ended), no subsequent tier can start higher unless we cut tier i,
		// or clamp tier i's max to next tier's min - 1.
		$normalized = array();
		$count      = count( $parsed );

		for ( $i = 0; $i < $count; $i++ ) {
			$current = $parsed[ $i ];

			if ( $i < $count - 1 ) {
				$next_min = $parsed[ $i + 1 ]['min_qty'];

				// If next tier has the exact same min_qty, current tier is superseded by next.
				if ( $next_min <= $current['min_qty'] ) {
					continue;
				}

				// Clamp current tier's max to (next_min - 1) if open-ended or overlapping.
				if ( null === $current['max_qty'] || $current['max_qty'] >= $next_min ) {
					$clamped_max = $next_min - 1.0;
					if ( $clamped_max >= $current['min_qty'] ) {
						$current['max_qty'] = $clamped_max;
					} else {
						// Edge case: fractional or impossible range, skip
						continue;
					}
				}
			}

			$normalized[] = new Tier(
				$current['min_qty'],
				$current['max_qty'],
				$current['type'],
				$current['value'],
				true
			);
		}

		return $normalized;
	}
}
