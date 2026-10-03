<?php
/**
 * Rule Repository.
 *
 * @package WPCalibrate\TieredPricing\Rules
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Rules;

use WPCalibrate\TieredPricing\Contracts\RuleRepositoryInterface;
use WPCalibrate\TieredPricing\Data\PricingRule;

/**
 * Class RuleRepository
 */
final class RuleRepository implements RuleRepositoryInterface {

	/**
	 * Post meta key for product and variation rules.
	 */
	public const META_RULE_KEY = '_wpcttp_pricing_rule';

	/**
	 * Term meta key for category rules.
	 */
	public const TERM_META_KEY = '_wpcttp_category_rule';

	/**
	 * Option key for global fallback rule.
	 */
	public const OPTION_GLOBAL_RULE = 'wpcttp_global_rule';

	/**
	 * Option key for central category rules map.
	 */
	public const OPTION_CATEGORY_RULES = 'wpcttp_category_rules';

	/**
	 * In-memory runtime cache for the current request.
	 *
	 * @var array<string, ?PricingRule>
	 */
	private array $runtime_cache = array();

	/**
	 * Constructor. Registers cache invalidation hooks.
	 */
	public function __construct() {
		add_action( 'clean_post_cache', array( $this, 'invalidate_post_cache' ) );
		add_action( 'edited_product_cat', array( $this, 'invalidate_category_cache' ) );
		add_action( 'delete_product_cat', array( $this, 'invalidate_category_cache' ) );
		add_action( 'update_option_' . self::OPTION_GLOBAL_RULE, array( $this, 'invalidate_global_cache' ) );
		add_action( 'update_option_' . self::OPTION_CATEGORY_RULES, array( $this, 'invalidate_category_cache' ) );
	}

	/**
	 * Invalidate in-memory post cache.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function invalidate_post_cache( int $post_id ): void {
		unset( $this->runtime_cache[ 'product_' . $post_id ] );
		unset( $this->runtime_cache[ 'variation_' . $post_id ] );
	}

	/**
	 * Invalidate category cache.
	 *
	 * @return void
	 */
	public function invalidate_category_cache(): void {
		delete_transient( 'wpcttp_category_rules_cache' );
		foreach ( array_keys( $this->runtime_cache ) as $key ) {
			if ( str_starts_with( $key, 'category_' ) ) {
				unset( $this->runtime_cache[ $key ] );
			}
		}
	}

	/**
	 * Invalidate global cache.
	 *
	 * @return void
	 */
	public function invalidate_global_cache(): void {
		delete_transient( 'wpcttp_global_rule_cache' );
		unset( $this->runtime_cache['global'] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_for_product( int $product_id ): ?PricingRule {
		$cache_key = 'product_' . $product_id;
		if ( array_key_exists( $cache_key, $this->runtime_cache ) ) {
			return $this->runtime_cache[ $cache_key ];
		}

		$raw = get_post_meta( $product_id, self::META_RULE_KEY, true );
		if ( empty( $raw ) || ! is_array( $raw ) || empty( $raw['enabled'] ) ) {
			$this->runtime_cache[ $cache_key ] = null;
			return null;
		}

		$rule = PricingRule::from_array( $raw, PricingRule::SOURCE_PRODUCT, $product_id );
		$this->runtime_cache[ $cache_key ] = $rule;
		return $rule;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_for_variation( int $variation_id ): ?PricingRule {
		$cache_key = 'variation_' . $variation_id;
		if ( array_key_exists( $cache_key, $this->runtime_cache ) ) {
			return $this->runtime_cache[ $cache_key ];
		}

		$raw = get_post_meta( $variation_id, self::META_RULE_KEY, true );
		if ( empty( $raw ) || ! is_array( $raw ) ) {
			$this->runtime_cache[ $cache_key ] = null;
			return null;
		}

		// If variation explicitly disables override or is not enabled.
		if ( empty( $raw['enabled'] ) || empty( $raw['override_parent'] ) ) {
			$this->runtime_cache[ $cache_key ] = null;
			return null;
		}

		$rule = PricingRule::from_array( $raw, PricingRule::SOURCE_VARIATION, $variation_id );
		$this->runtime_cache[ $cache_key ] = $rule;
		return $rule;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_for_category( int $term_id ): ?PricingRule {
		$cache_key = 'category_' . $term_id;
		if ( array_key_exists( $cache_key, $this->runtime_cache ) ) {
			return $this->runtime_cache[ $cache_key ];
		}

		// First check term meta.
		$raw = get_term_meta( $term_id, self::TERM_META_KEY, true );
		if ( ! empty( $raw ) && is_array( $raw ) && ! empty( $raw['enabled'] ) ) {
			$rule = PricingRule::from_array( $raw, PricingRule::SOURCE_CATEGORY, $term_id );
			$this->runtime_cache[ $cache_key ] = $rule;
			return $rule;
		}

		// Fallback check central category rules option.
		$all_central = $this->get_all_category_rules();
		$rule        = $all_central[ $term_id ] ?? null;

		$this->runtime_cache[ $cache_key ] = $rule;
		return $rule;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_all_category_rules(): array {
		$cached = get_transient( 'wpcttp_category_rules_cache' );
		if ( false !== $cached && is_array( $cached ) ) {
			$rules = array();
			foreach ( $cached as $term_id => $raw ) {
				if ( is_array( $raw ) && ! empty( $raw['enabled'] ) ) {
					$rules[ (int) $term_id ] = PricingRule::from_array( $raw, PricingRule::SOURCE_CATEGORY, (int) $term_id );
				}
			}
			return $rules;
		}

		$raw_central = get_option( self::OPTION_CATEGORY_RULES, array() );
		if ( ! is_array( $raw_central ) ) {
			$raw_central = array();
		}

		$rules = array();
		foreach ( $raw_central as $term_id => $raw ) {
			if ( is_array( $raw ) && ! empty( $raw['enabled'] ) ) {
				$rules[ (int) $term_id ] = PricingRule::from_array( $raw, PricingRule::SOURCE_CATEGORY, (int) $term_id );
			}
		}

		set_transient( 'wpcttp_category_rules_cache', $raw_central, HOUR_IN_SECONDS * 12 );
		return $rules;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_global_rule(): ?PricingRule {
		if ( array_key_exists( 'global', $this->runtime_cache ) ) {
			return $this->runtime_cache['global'];
		}

		$global_enabled = get_option( 'wpcttp_global_rule_enabled', 'no' );
		if ( 'yes' !== $global_enabled ) {
			$this->runtime_cache['global'] = null;
			return null;
		}

		$raw = get_option( self::OPTION_GLOBAL_RULE, array() );
		if ( empty( $raw ) || ! is_array( $raw ) || empty( $raw['enabled'] ) ) {
			$this->runtime_cache['global'] = null;
			return null;
		}

		$rule = PricingRule::from_array( $raw, PricingRule::SOURCE_GLOBAL, 0 );
		$this->runtime_cache['global'] = $rule;
		return $rule;
	}

	/**
	 * {@inheritDoc}
	 */
	public function save_for_product( int $product_id, PricingRule $rule ): bool {
		$this->invalidate_post_cache( $product_id );
		return (bool) update_post_meta( $product_id, self::META_RULE_KEY, $rule->to_array() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function save_for_variation( int $variation_id, PricingRule $rule ): bool {
		$this->invalidate_post_cache( $variation_id );
		return (bool) update_post_meta( $variation_id, self::META_RULE_KEY, $rule->to_array() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function save_for_category( int $term_id, PricingRule $rule ): bool {
		$this->invalidate_category_cache();
		update_term_meta( $term_id, self::TERM_META_KEY, $rule->to_array() );

		// Also update central option map.
		$all = get_option( self::OPTION_CATEGORY_RULES, array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		$all[ $term_id ] = $rule->to_array();
		return update_option( self::OPTION_CATEGORY_RULES, $all );
	}

	/**
	 * {@inheritDoc}
	 */
	public function save_global_rule( PricingRule $rule ): bool {
		$this->invalidate_global_cache();
		return update_option( self::OPTION_GLOBAL_RULE, $rule->to_array() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_for_product( int $product_id ): bool {
		$this->invalidate_post_cache( $product_id );
		return delete_post_meta( $product_id, self::META_RULE_KEY );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_for_variation( int $variation_id ): bool {
		$this->invalidate_post_cache( $variation_id );
		return delete_post_meta( $variation_id, self::META_RULE_KEY );
	}

	/**
	 * Delete category rule.
	 *
	 * @param int $term_id Term ID.
	 * @return bool
	 */
	public function delete_for_category( int $term_id ): bool {
		$this->invalidate_category_cache();
		delete_term_meta( $term_id, self::TERM_META_KEY );

		$all = get_option( self::OPTION_CATEGORY_RULES, array() );
		if ( is_array( $all ) && isset( $all[ $term_id ] ) ) {
			unset( $all[ $term_id ] );
			update_option( self::OPTION_CATEGORY_RULES, $all );
		}
		return true;
	}
}
