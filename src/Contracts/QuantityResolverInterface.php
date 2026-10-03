<?php
/**
 * Quantity Resolver Interface.
 *
 * @package WPCalibrate\TieredPricing\Contracts
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Contracts;

use WC_Product;
use WPCalibrate\TieredPricing\Data\PricingRule;

/**
 * Interface QuantityResolverInterface
 */
interface QuantityResolverInterface {

	/**
	 * Calculate the aggregate quantity for a product given a rule scope and cart context.
	 *
	 * Scopes supported:
	 * - individual: exact line quantity.
	 * - variable_combined: aggregate quantity of all variations of this parent product in cart.
	 * - category: aggregate quantity of all cart items belonging to the rule category.
	 * - cart_wide: aggregate quantity of all eligible cart items.
	 *
	 * @param WC_Product           $product Product or variation.
	 * @param PricingRule          $rule Matched rule.
	 * @param float|int            $line_quantity Initial or direct line quantity.
	 * @param array<string, mixed> $cart_items Cart contents array if in cart context.
	 * @return float|int
	 */
	public function resolve_quantity(
		WC_Product $product,
		PricingRule $rule,
		float|int $line_quantity,
		array $cart_items = array()
	): float|int;
}
