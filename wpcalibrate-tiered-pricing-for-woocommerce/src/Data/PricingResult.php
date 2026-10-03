<?php
/**
 * Pricing Result Value Object.
 *
 * @package WPCalibrate\TieredPricing\Data
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Data;

/**
 * Class PricingResult
 */
final class PricingResult {

	/**
	 * Constructor.
	 *
	 * @param int         $product_id Product ID.
	 * @param int         $variation_id Variation ID (0 for simple).
	 * @param string|int  $rule_id Rule ID.
	 * @param string      $rule_source Source ('variation', 'product', 'category', 'global', 'none').
	 * @param string      $calculation_scope Scope ('individual', 'variable_combined', 'category', 'cart_wide').
	 * @param string      $pricing_type Pricing type ('fixed', 'percentage', 'none').
	 * @param ?Tier       $matched_tier Matched tier object if any.
	 * @param float|int   $aggregate_quantity Evaluated quantity.
	 * @param float       $base_price Configured base price before tier adjustment.
	 * @param float       $adjusted_unit_price Unit price after tier adjustment.
	 * @param float       $discount_amount Unit monetary discount amount.
	 * @param float       $discount_percentage Percentage discount (0-100).
	 * @param bool        $is_applied Whether tier pricing was applied.
	 */
	public function __construct(
		private readonly int $product_id,
		private readonly int $variation_id,
		private readonly string|int $rule_id,
		private readonly string $rule_source,
		private readonly string $calculation_scope,
		private readonly string $pricing_type,
		private readonly ?Tier $matched_tier,
		private readonly float|int $aggregate_quantity,
		private readonly float $base_price,
		private readonly float $adjusted_unit_price,
		private readonly float $discount_amount,
		private readonly float $discount_percentage,
		private readonly bool $is_applied
	) {}

	/**
	 * Get product ID.
	 *
	 * @return int
	 */
	public function get_product_id(): int {
		return $this->product_id;
	}

	/**
	 * Get variation ID.
	 *
	 * @return int
	 */
	public function get_variation_id(): int {
		return $this->variation_id;
	}

	/**
	 * Get rule ID.
	 *
	 * @return string|int
	 */
	public function get_rule_id(): string|int {
		return $this->rule_id;
	}

	/**
	 * Get rule source.
	 *
	 * @return string
	 */
	public function get_rule_source(): string {
		return $this->rule_source;
	}

	/**
	 * Get calculation scope.
	 *
	 * @return string
	 */
	public function get_calculation_scope(): string {
		return $this->calculation_scope;
	}

	/**
	 * Get pricing type.
	 *
	 * @return string
	 */
	public function get_pricing_type(): string {
		return $this->pricing_type;
	}

	/**
	 * Get matched tier.
	 *
	 * @return ?Tier
	 */
	public function get_matched_tier(): ?Tier {
		return $this->matched_tier;
	}

	/**
	 * Get aggregate quantity.
	 *
	 * @return float|int
	 */
	public function get_aggregate_quantity(): float|int {
		return $this->aggregate_quantity;
	}

	/**
	 * Get base price.
	 *
	 * @return float
	 */
	public function get_base_price(): float {
		return $this->base_price;
	}

	/**
	 * Get adjusted unit price.
	 *
	 * @return float
	 */
	public function get_adjusted_unit_price(): float {
		return $this->adjusted_unit_price;
	}

	/**
	 * Get discount amount per unit.
	 *
	 * @return float
	 */
	public function get_discount_amount(): float {
		return $this->discount_amount;
	}

	/**
	 * Get discount percentage.
	 *
	 * @return float
	 */
	public function get_discount_percentage(): float {
		return $this->discount_percentage;
	}

	/**
	 * Whether tier pricing was applied.
	 *
	 * @return bool
	 */
	public function is_applied(): bool {
		return $this->is_applied;
	}

	/**
	 * Calculate total line savings.
	 *
	 * @param float|int $line_qty Line quantity.
	 * @return float
	 */
	public function get_line_savings( float|int $line_qty ): float {
		if ( ! $this->is_applied ) {
			return 0.0;
		}
		return max( 0.0, (float) ( $this->discount_amount * $line_qty ) );
	}

	/**
	 * Convert to array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'product_id'          => $this->product_id,
			'variation_id'        => $this->variation_id,
			'rule_id'             => $this->rule_id,
			'rule_source'         => $this->rule_source,
			'calculation_scope'   => $this->calculation_scope,
			'pricing_type'        => $this->pricing_type,
			'matched_tier'        => $this->matched_tier ? $this->matched_tier->to_array() : null,
			'aggregate_quantity'  => $this->aggregate_quantity,
			'base_price'          => $this->base_price,
			'adjusted_unit_price' => $this->adjusted_unit_price,
			'discount_amount'     => $this->discount_amount,
			'discount_percentage' => $this->discount_percentage,
			'is_applied'          => $this->is_applied,
		);
	}
}
