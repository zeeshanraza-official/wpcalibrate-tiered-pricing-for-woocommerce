<?php
/**
 * Core Pricing Engine.
 *
 * @package WPCalibrate\TieredPricing\Pricing
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Pricing;

use WC_Product;
use WPCalibrate\TieredPricing\Contracts\PricingEngineInterface;
use WPCalibrate\TieredPricing\Contracts\QuantityResolverInterface;
use WPCalibrate\TieredPricing\Contracts\RuleResolverInterface;
use WPCalibrate\TieredPricing\Data\PricingResult;
use WPCalibrate\TieredPricing\Data\PricingRule;
use WPCalibrate\TieredPricing\Support\Logger;

/**
 * Class PricingEngine
 */
final class PricingEngine implements PricingEngineInterface {

	/**
	 * Constructor.
	 *
	 * @param RuleResolverInterface     $rule_resolver Rule precedence resolver.
	 * @param QuantityResolverInterface $quantity_resolver Quantity scope resolver.
	 */
	public function __construct(
		private readonly RuleResolverInterface $rule_resolver,
		private readonly QuantityResolverInterface $quantity_resolver
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function calculate( WC_Product $product, float|int $quantity, array $cart_context = array() ): PricingResult {
		$product_id   = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$variation_id = $product->is_type( 'variation' ) ? $product->get_id() : 0;
		$quantity     = max( 1.0, (float) $quantity );

		// 1. Determine applicable winning rule.
		$rule = $this->rule_resolver->resolve( $product );
		if ( null === $rule || ! $rule->is_enabled() ) {
			$base_info = PricingCalculator::get_base_price( $product );
			return new PricingResult(
				$product_id,
				$variation_id,
				'',
				'none',
				PricingRule::SCOPE_INDIVIDUAL,
				'none',
				null,
				$quantity,
				$base_info['base_price'],
				$base_info['base_price'],
				0.0,
				0.0,
				false
			);
		}

		// 2. Check base price eligibility (sale price rules).
		$base_info = PricingCalculator::get_base_price( $product );
		if ( ! $base_info['is_eligible'] ) {
			return new PricingResult(
				$product_id,
				$variation_id,
				$rule->get_id(),
				$rule->get_source(),
				$rule->get_calculation_scope(),
				'none',
				null,
				$quantity,
				$base_info['base_price'],
				$base_info['base_price'],
				0.0,
				0.0,
				false
			);
		}

		$base_price = $base_info['base_price'];

		// 3. Resolve aggregate quantity according to scope.
		$aggregate_qty = $this->quantity_resolver->resolve_quantity( $product, $rule, $quantity, $cart_context );

		// 4. Find matched tier.
		$matched_tier = $rule->find_matching_tier( $aggregate_qty );

		// 5. Calculate adjusted price.
		$calculation = PricingCalculator::calculate_tier_price( $base_price, $matched_tier, $product, $aggregate_qty );

		$result = new PricingResult(
			$product_id,
			$variation_id,
			$rule->get_id(),
			$rule->get_source(),
			$rule->get_calculation_scope(),
			$matched_tier ? $matched_tier->get_type() : 'none',
			$matched_tier,
			$aggregate_qty,
			$base_price,
			$calculation['adjusted_unit_price'],
			$calculation['discount_amount'],
			$calculation['discount_percentage'],
			$calculation['is_applied']
		);

		// 6. Optional diagnostic logging.
		if ( Logger::is_debug_enabled() ) {
			Logger::debug(
				'Pricing calculation evaluated',
				array(
					'product_id'          => $product_id,
					'variation_id'        => $variation_id,
					'rule_source'         => $rule->get_source(),
					'scope'               => $rule->get_calculation_scope(),
					'aggregate_quantity'  => $aggregate_qty,
					'base_price'          => $base_price,
					'adjusted_unit_price' => $calculation['adjusted_unit_price'],
					'is_applied'          => $calculation['is_applied'],
				)
			);
		}

		return $result;
	}
}
