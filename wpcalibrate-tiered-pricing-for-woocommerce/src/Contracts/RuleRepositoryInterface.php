<?php
/**
 * Rule Repository Interface.
 *
 * @package WPCalibrate\TieredPricing\Contracts
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Contracts;

use WPCalibrate\TieredPricing\Data\PricingRule;

/**
 * Interface RuleRepositoryInterface
 */
interface RuleRepositoryInterface {

	/**
	 * Get pricing rule for a specific product ID (simple or parent variable).
	 *
	 * @param int $product_id Product ID.
	 * @return ?PricingRule
	 */
	public function get_for_product( int $product_id ): ?PricingRule;

	/**
	 * Get pricing rule for a specific variation ID.
	 *
	 * @param int $variation_id Variation ID.
	 * @return ?PricingRule
	 */
	public function get_for_variation( int $variation_id ): ?PricingRule;

	/**
	 * Get pricing rules for a category term ID.
	 *
	 * @param int $term_id Product category term ID.
	 * @return ?PricingRule
	 */
	public function get_for_category( int $term_id ): ?PricingRule;

	/**
	 * Get all configured category rules.
	 *
	 * @return array<int, PricingRule> Keyed by category term ID.
	 */
	public function get_all_category_rules(): array;

	/**
	 * Get global fallback rule.
	 *
	 * @return ?PricingRule
	 */
	public function get_global_rule(): ?PricingRule;

	/**
	 * Save product rule.
	 *
	 * @param int         $product_id Product ID.
	 * @param PricingRule $rule Pricing rule.
	 * @return bool
	 */
	public function save_for_product( int $product_id, PricingRule $rule ): bool;

	/**
	 * Save variation rule.
	 *
	 * @param int         $variation_id Variation ID.
	 * @param PricingRule $rule Pricing rule.
	 * @return bool
	 */
	public function save_for_variation( int $variation_id, PricingRule $rule ): bool;

	/**
	 * Save category rule.
	 *
	 * @param int         $term_id Term ID.
	 * @param PricingRule $rule Pricing rule.
	 * @return bool
	 */
	public function save_for_category( int $term_id, PricingRule $rule ): bool;

	/**
	 * Save global fallback rule.
	 *
	 * @param PricingRule $rule Pricing rule.
	 * @return bool
	 */
	public function save_global_rule( PricingRule $rule ): bool;

	/**
	 * Delete product rule.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function delete_for_product( int $product_id ): bool;

	/**
	 * Delete variation rule.
	 *
	 * @param int $variation_id Variation ID.
	 * @return bool
	 */
	public function delete_for_variation( int $variation_id ): bool;
}
