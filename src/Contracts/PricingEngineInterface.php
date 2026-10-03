<?php
/**
 * Pricing Engine Interface.
 *
 * @package WPCalibrate\TieredPricing\Contracts
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Contracts;

use WC_Product;
use WPCalibrate\TieredPricing\Data\PricingResult;

/**
 * Interface PricingEngineInterface
 */
interface PricingEngineInterface {

	/**
	 * Calculate pricing for a product given quantity and cart context.
	 *
	 * @param WC_Product          $product Product instance.
	 * @param float|int           $quantity Quantity to evaluate.
	 * @param array<string, mixed> $cart_context Optional cart context items.
	 * @return PricingResult
	 */
	public function calculate( WC_Product $product, float|int $quantity, array $cart_context = array() ): PricingResult;
}
