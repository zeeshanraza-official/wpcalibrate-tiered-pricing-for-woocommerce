<?php
/**
 * Rule Precedence Resolver.
 *
 * @package WPCalibrate\TieredPricing\Rules
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Rules;

use WC_Product;
use WPCalibrate\TieredPricing\Contracts\RuleRepositoryInterface;
use WPCalibrate\TieredPricing\Contracts\RuleResolverInterface;
use WPCalibrate\TieredPricing\Data\PricingRule;

/**
 * Class RuleResolver
 */
final class RuleResolver implements RuleResolverInterface {

	/**
	 * Constructor.
	 *
	 * @param RuleRepositoryInterface $repository Rule repository instance.
	 */
	public function __construct(
		private readonly RuleRepositoryInterface $repository
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function resolve( WC_Product $product ): ?PricingRule {
		$plugin_enabled = get_option( 'wpcttp_enabled', 'yes' );
		if ( 'yes' !== $plugin_enabled ) {
			return null;
		}

		$rule = null;

		// 1. Variation level rule (if this is a variation).
		if ( $product->is_type( 'variation' ) ) {
			$variation_rule = $this->repository->get_for_variation( $product->get_id() );
			if ( null !== $variation_rule && $variation_rule->is_enabled() && $variation_rule->does_override_parent() ) {
				$rule = $variation_rule;
			} else {
				// 2. Parent product rule.
				$parent_id = $product->get_parent_id();
				if ( $parent_id > 0 ) {
					$parent_rule = $this->repository->get_for_product( $parent_id );
					if ( null !== $parent_rule && $parent_rule->is_enabled() ) {
						$rule = $parent_rule;
					}
				}
			}
		} else {
			// Simple / other product rule.
			$product_rule = $this->repository->get_for_product( $product->get_id() );
			if ( null !== $product_rule && $product_rule->is_enabled() ) {
				$rule = $product_rule;
			}
		}

		// 3. Category rules if no variation/product rule won.
		if ( null === $rule ) {
			$cat_ids = $product->get_category_ids();
			if ( empty( $cat_ids ) && $product->is_type( 'variation' ) ) {
				$parent = wc_get_product( $product->get_parent_id() );
				if ( $parent ) {
					$cat_ids = $parent->get_category_ids();
				}
			}

			if ( ! empty( $cat_ids ) ) {
				$matching_cat_rules = array();
				foreach ( $cat_ids as $term_id ) {
					$cat_rule = $this->repository->get_for_category( (int) $term_id );
					if ( null !== $cat_rule && $cat_rule->is_enabled() ) {
						$matching_cat_rules[] = $cat_rule;
					}
				}

				if ( ! empty( $matching_cat_rules ) ) {
					// Sort by numeric priority (highest first), then term ID (lowest first) for deterministic tie-breaking.
					usort(
						$matching_cat_rules,
						static function ( PricingRule $a, PricingRule $b ): int {
							if ( $a->get_priority() === $b->get_priority() ) {
								return $a->get_source_id() <=> $b->get_source_id();
							}
							return $b->get_priority() <=> $a->get_priority();
						}
					);
					$rule = $matching_cat_rules[0];
				}
			}
		}

		// 4. Global fallback rule if no higher rule won.
		if ( null === $rule ) {
			$global_rule = $this->repository->get_global_rule();
			if ( null !== $global_rule && $global_rule->is_enabled() ) {
				$rule = $global_rule;
			}
		}

		/**
		 * Filter the winning resolved pricing rule.
		 *
		 * @param ?PricingRule $rule Winning rule or null.
		 * @param WC_Product   $product WooCommerce product.
		 */
		return apply_filters( 'wpcalibrate_tiered_pricing_resolved_rule', $rule, $product );
	}
}
