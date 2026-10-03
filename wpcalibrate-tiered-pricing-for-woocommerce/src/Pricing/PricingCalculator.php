<?php
/**
 * Pricing Calculator.
 *
 * @package WPCalibrate\TieredPricing\Pricing
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Pricing;

use WC_Product;
use WPCalibrate\TieredPricing\Data\Tier;

/**
 * Class PricingCalculator
 */
final class PricingCalculator {

	/**
	 * Base price strategies.
	 */
	public const STRATEGY_CURRENT_EFFECTIVE = 'current_effective';
	public const STRATEGY_REGULAR           = 'regular';
	public const STRATEGY_DISABLE_ON_SALE   = 'disable_on_sale';

	/**
	 * Determine the applicable base price for a product based on configured strategy.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return array{base_price: float, is_eligible: bool}
	 */
	public static function get_base_price( WC_Product $product ): array {
		$strategy = get_option( 'wpcttp_base_price_strategy', self::STRATEGY_CURRENT_EFFECTIVE );

		// Check if tier pricing is disabled when product is on sale.
		if ( self::STRATEGY_DISABLE_ON_SALE === $strategy && $product->is_on_sale() ) {
			$effective_price = (float) ( $product->get_price() ?: 0.0 );
			return array(
				'base_price'  => $effective_price,
				'is_eligible' => false,
			);
		}

		if ( self::STRATEGY_REGULAR === $strategy ) {
			$reg_price = $product->get_regular_price();
			$price     = '' !== $reg_price ? (float) $reg_price : (float) ( $product->get_price() ?: 0.0 );
		} else {
			// Current effective price.
			$price = (float) ( $product->get_price() ?: 0.0 );
		}

		/**
		 * Filter the applicable base price before tier calculation.
		 *
		 * @param float      $price Base price.
		 * @param WC_Product $product Product.
		 * @param string     $strategy Selected strategy.
		 */
		$base_price = (float) apply_filters( 'wpcalibrate_tiered_pricing_base_price', $price, $product, $strategy );

		return array(
			'base_price'  => max( 0.0, $base_price ),
			'is_eligible' => true,
		);
	}

	/**
	 * Calculate adjusted unit price and discounts from base price and matched tier.
	 *
	 * @param float      $base_price Configured base price.
	 * @param ?Tier      $tier Matched tier or null.
	 * @param WC_Product $product WooCommerce product.
	 * @param float|int  $quantity Aggregate quantity evaluated.
	 * @return array{
	 *     adjusted_unit_price: float,
	 *     discount_amount: float,
	 *     discount_percentage: float,
	 *     is_applied: bool
	 * }
	 */
	public static function calculate_tier_price(
		float $base_price,
		?Tier $tier,
		WC_Product $product,
		float|int $quantity
	): array {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;

		if ( null === $tier || ! $tier->is_enabled() ) {
			return array(
				'adjusted_unit_price' => round( $base_price, $decimals ),
				'discount_amount'     => 0.0,
				'discount_percentage' => 0.0,
				'is_applied'          => false,
			);
		}

		if ( Tier::TYPE_FIXED === $tier->get_type() ) {
			$adjusted_unit_price = max( 0.0, (float) $tier->get_value() );
			$discount_amount     = max( 0.0, $base_price - $adjusted_unit_price );
			$discount_percentage = $base_price > 0.0 ? ( $discount_amount / $base_price ) * 100.0 : 0.0;
		} else {
			// Percentage discount.
			$pct                 = min( 100.0, max( 0.0, (float) $tier->get_value() ) );
			$discount_amount     = $base_price * ( $pct / 100.0 );
			$adjusted_unit_price = max( 0.0, $base_price - $discount_amount );
			$discount_percentage = $pct;
		}

		$adjusted_unit_price = round( $adjusted_unit_price, $decimals );
		$discount_amount     = round( $discount_amount, $decimals );
		$discount_percentage = round( $discount_percentage, 2 );

		/**
		 * Filter final calculated unit price.
		 *
		 * @param float      $adjusted_unit_price Adjusted price.
		 * @param WC_Product $product Product.
		 * @param float      $base_price Base price.
		 * @param Tier       $tier Matched tier.
		 * @param float|int  $quantity Quantity.
		 */
		$filtered_price = (float) apply_filters(
			'wpcalibrate_tiered_pricing_final_unit_price',
			$adjusted_unit_price,
			$product,
			$base_price,
			$tier,
			$quantity
		);

		return array(
			'adjusted_unit_price' => max( 0.0, round( $filtered_price, $decimals ) ),
			'discount_amount'     => $discount_amount,
			'discount_percentage' => $discount_percentage,
			'is_applied'          => true,
		);
	}
}
