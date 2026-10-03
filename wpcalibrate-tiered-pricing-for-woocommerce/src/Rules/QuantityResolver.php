<?php
/**
 * Quantity Resolver.
 *
 * @package WPCalibrate\TieredPricing\Rules
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Rules;

use WC_Product;
use WPCalibrate\TieredPricing\Contracts\QuantityResolverInterface;
use WPCalibrate\TieredPricing\Data\PricingRule;

/**
 * Class QuantityResolver
 */
final class QuantityResolver implements QuantityResolverInterface {

	/**
	 * {@inheritDoc}
	 */
	public function resolve_quantity(
		WC_Product $product,
		PricingRule $rule,
		float|int $line_quantity,
		array $cart_items = array()
	): float|int {
		$scope = $rule->get_calculation_scope();

		// If no cart context is available (e.g. single product page live display before adding to cart),
		// individual quantity is the fallback.
		if ( empty( $cart_items ) ) {
			return max( 1.0, (float) $line_quantity );
		}

		$calculated_qty = match ( $scope ) {
			PricingRule::SCOPE_VARIABLE_COMBINED => $this->calculate_variable_combined_qty( $product, $cart_items, $line_quantity ),
			PricingRule::SCOPE_CATEGORY          => $this->calculate_category_qty( $product, $rule, $cart_items, $line_quantity ),
			PricingRule::SCOPE_CART_WIDE         => $this->calculate_cart_wide_qty( $cart_items, $line_quantity ),
			default                              => (float) $line_quantity,
		};

		/**
		 * Filter the resolved aggregate quantity.
		 *
		 * @param float|int   $calculated_qty Aggregate quantity.
		 * @param WC_Product  $product WooCommerce product.
		 * @param PricingRule $rule Matched rule.
		 * @param float|int   $line_quantity Base line quantity.
		 * @param array       $cart_items Cart items map.
		 */
		return apply_filters(
			'wpcalibrate_tiered_pricing_aggregate_quantity',
			max( 1.0, $calculated_qty ),
			$product,
			$rule,
			$line_quantity,
			$cart_items
		);
	}

	/**
	 * Aggregate quantities across all variations belonging to the same parent variable product in the cart.
	 *
	 * @param WC_Product           $product Current product or variation.
	 * @param array<string, mixed> $cart_items Cart contents.
	 * @param float|int            $fallback Line quantity fallback.
	 * @return float
	 */
	private function calculate_variable_combined_qty( WC_Product $product, array $cart_items, float|int $fallback ): float {
		$parent_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		if ( $parent_id <= 0 ) {
			return (float) $fallback;
		}

		$total_qty = 0.0;
		$found_any = false;

		foreach ( $cart_items as $item ) {
			if ( ! isset( $item['data'] ) || ! ( $item['data'] instanceof WC_Product ) ) {
				continue;
			}

			/** @var WC_Product $item_product */
			$item_product = $item['data'];
			$item_parent_id = $item_product->is_type( 'variation' ) ? $item_product->get_parent_id() : $item_product->get_id();

			if ( $item_parent_id === $parent_id ) {
				$total_qty += isset( $item['quantity'] ) ? (float) $item['quantity'] : 1.0;
				$found_any = true;
			}
		}

		return $found_any ? $total_qty : (float) $fallback;
	}

	/**
	 * Aggregate quantities for all cart items belonging to the matched category rule.
	 *
	 * @param WC_Product           $product Product.
	 * @param PricingRule          $rule Matched rule.
	 * @param array<string, mixed> $cart_items Cart contents.
	 * @param float|int            $fallback Line quantity fallback.
	 * @return float
	 */
	private function calculate_category_qty(
		WC_Product $product,
		PricingRule $rule,
		array $cart_items,
		float|int $fallback
	): float {
		// Identify eligible category IDs.
		$eligible_term_id = $rule->get_source() === PricingRule::SOURCE_CATEGORY ? (int) $rule->get_source_id() : 0;

		$total_qty = 0.0;
		$found_any = false;

		foreach ( $cart_items as $item ) {
			if ( ! isset( $item['data'] ) || ! ( $item['data'] instanceof WC_Product ) ) {
				continue;
			}

			/** @var WC_Product $item_product */
			$item_product = $item['data'];
			$item_cats    = $item_product->get_category_ids();

			if ( empty( $item_cats ) && $item_product->is_type( 'variation' ) ) {
				$parent = wc_get_product( $item_product->get_parent_id() );
				if ( $parent ) {
					$item_cats = $parent->get_category_ids();
				}
			}

			$is_match = false;
			if ( $eligible_term_id > 0 ) {
				$is_match = in_array( $eligible_term_id, $item_cats, true );
			} else {
				// If not a specific category rule source, check if any category intersects.
				$current_cats = $product->get_category_ids();
				$is_match     = ! empty( array_intersect( $current_cats, $item_cats ) );
			}

			if ( $is_match ) {
				$total_qty += isset( $item['quantity'] ) ? (float) $item['quantity'] : 1.0;
				$found_any = true;
			}
		}

		return $found_any ? $total_qty : (float) $fallback;
	}

	/**
	 * Aggregate quantities for all items in the cart.
	 *
	 * @param array<string, mixed> $cart_items Cart contents.
	 * @param float|int            $fallback Line quantity fallback.
	 * @return float
	 */
	private function calculate_cart_wide_qty( array $cart_items, float|int $fallback ): float {
		$total_qty = 0.0;
		$found_any = false;

		foreach ( $cart_items as $item ) {
			if ( isset( $item['quantity'] ) ) {
				$total_qty += (float) $item['quantity'];
				$found_any = true;
			}
		}

		return $found_any ? $total_qty : (float) $fallback;
	}
}
