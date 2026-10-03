<?php
/**
 * Rule Resolver Interface.
 *
 * @package WPCalibrate\TieredPricing\Contracts
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Contracts;

use WC_Product;
use WPCalibrate\TieredPricing\Data\PricingRule;

/**
 * Interface RuleResolverInterface
 */
interface RuleResolverInterface {

	/**
	 * Resolve the single winning pricing rule for a product or variation.
	 *
	 * Precedence:
	 * 1. Variation rule
	 * 2. Product parent rule
	 * 3. Product category rule (by priority or deterministic term order)
	 * 4. Global rule
	 *
	 * @param WC_Product $product WooCommerce Product.
	 * @return ?PricingRule Returns the winning rule or null if none apply.
	 */
	public function resolve( WC_Product $product ): ?PricingRule;
}
