<?php
/**
 * Category Rule Admin Controller.
 *
 * @package WPCalibrate\TieredPricing\Admin
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Admin;

use WPCalibrate\TieredPricing\Contracts\RuleRepositoryInterface;
use WPCalibrate\TieredPricing\Data\PricingRule;
use WPCalibrate\TieredPricing\Rules\RuleValidator;

/**
 * Class CategoryRuleAdmin
 */
final class CategoryRuleAdmin {

	/**
	 * Constructor.
	 *
	 * @param RuleRepositoryInterface $repository Rule repository.
	 */
	public function __construct(
		private readonly RuleRepositoryInterface $repository
	) {}

	/**
	 * Save a category rule from POST input.
	 *
	 * @param int                  $term_id Product category term ID.
	 * @param array<string, mixed> $raw_data Raw input data.
	 * @return bool
	 */
	public function save_rule( int $term_id, array $raw_data ): bool {
		if ( $term_id <= 0 ) {
			return false;
		}

		if ( empty( $raw_data['enabled'] ) ) {
			return $this->repository->delete_for_category( $term_id );
		}

		$raw_tiers  = isset( $raw_data['tiers'] ) && is_array( $raw_data['tiers'] ) ? $raw_data['tiers'] : array();
		$normalized = RuleValidator::normalize_tiers( $raw_tiers );

		$scope    = isset( $raw_data['calculation_scope'] ) ? sanitize_text_field( $raw_data['calculation_scope'] ) : PricingRule::SCOPE_CATEGORY;
		$priority = isset( $raw_data['priority'] ) ? (int) $raw_data['priority'] : 10;

		$term = get_term( $term_id, 'product_cat' );
		$name = ( $term && ! is_wp_error( $term ) ) ? $term->name : "Category #{$term_id}";

		$rule = new PricingRule(
			'category_' . $term_id,
			PricingRule::SOURCE_CATEGORY,
			$term_id,
			$name,
			true,
			$scope,
			$priority,
			$normalized
		);

		return $this->repository->save_for_category( $term_id, $rule );
	}
}
