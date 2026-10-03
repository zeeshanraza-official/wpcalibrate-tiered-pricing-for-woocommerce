<?php
/**
 * Pricing Rule Value Object.
 *
 * @package WPCalibrate\TieredPricing\Data
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Data;

/**
 * Class PricingRule
 */
final class PricingRule {

	/**
	 * Source constants.
	 */
	public const SOURCE_VARIATION = 'variation';
	public const SOURCE_PRODUCT   = 'product';
	public const SOURCE_CATEGORY  = 'category';
	public const SOURCE_GLOBAL    = 'global';

	/**
	 * Calculation scope constants.
	 */
	public const SCOPE_INDIVIDUAL        = 'individual';
	public const SCOPE_VARIABLE_COMBINED = 'variable_combined';
	public const SCOPE_CATEGORY          = 'category';
	public const SCOPE_CART_WIDE         = 'cart_wide';

	/**
	 * Constructor.
	 *
	 * @param string|int  $id Identifier.
	 * @param string      $source Source type: 'variation', 'product', 'category', 'global'.
	 * @param int         $source_id Object ID (post ID, term ID, or 0).
	 * @param string      $name Human readable name/label.
	 * @param bool        $enabled Whether rule is active.
	 * @param string      $calculation_scope Calculation scope.
	 * @param int         $priority Priority for tie-breaking.
	 * @param array<Tier> $tiers List of tiers.
	 * @param bool        $override_parent Variation specific: whether it overrides parent rule.
	 */
	public function __construct(
		private readonly string|int $id,
		private readonly string $source,
		private readonly int $source_id,
		private readonly string $name,
		private readonly bool $enabled,
		private readonly string $calculation_scope,
		private readonly int $priority,
		private readonly array $tiers,
		private readonly bool $override_parent = true
	) {}

	/**
	 * Get rule ID.
	 *
	 * @return string|int
	 */
	public function get_id(): string|int {
		return $this->id;
	}

	/**
	 * Get source type.
	 *
	 * @return string
	 */
	public function get_source(): string {
		return $this->source;
	}

	/**
	 * Get source ID.
	 *
	 * @return int
	 */
	public function get_source_id(): int {
		return $this->source_id;
	}

	/**
	 * Get rule name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Whether rule is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Get calculation scope.
	 *
	 * @return string
	 */
	public function get_calculation_scope(): string {
		return $this->calculation_scope;
	}

	/**
	 * Get rule priority.
	 *
	 * @return int
	 */
	public function get_priority(): int {
		return $this->priority;
	}

	/**
	 * Get tiers.
	 *
	 * @return array<Tier>
	 */
	public function get_tiers(): array {
		return $this->tiers;
	}

	/**
	 * Whether this variation explicitly overrides parent rule.
	 *
	 * @return bool
	 */
	public function does_override_parent(): bool {
		return $this->override_parent;
	}

	/**
	 * Find matching tier for a given quantity.
	 *
	 * @param float|int $quantity Quantity.
	 * @return ?Tier
	 */
	public function find_matching_tier( float|int $quantity ): ?Tier {
		if ( ! $this->enabled ) {
			return null;
		}

		foreach ( $this->tiers as $tier ) {
			if ( $tier->matches( $quantity ) ) {
				return $tier;
			}
		}

		return null;
	}

	/**
	 * Convert to array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$tiers_array = array();
		foreach ( $this->tiers as $tier ) {
			$tiers_array[] = $tier->to_array();
		}

		return array(
			'id'                => $this->id,
			'source'            => $this->source,
			'source_id'         => $this->source_id,
			'name'              => $this->name,
			'enabled'           => $this->enabled,
			'calculation_scope' => $this->calculation_scope,
			'priority'          => $this->priority,
			'override_parent'   => $this->override_parent,
			'tiers'             => $tiers_array,
		);
	}

	/**
	 * Create instance from array.
	 *
	 * @param array<string, mixed> $data Serialized/stored data.
	 * @param string               $default_source Default source if not set.
	 * @param int                  $default_source_id Default source ID if not set.
	 * @return self
	 */
	public static function from_array(
		array $data,
		string $default_source = self::SOURCE_PRODUCT,
		int $default_source_id = 0
	): self {
		$id        = $data['id'] ?? (string) $default_source_id;
		$source    = $data['source'] ?? $default_source;
		$source_id = isset( $data['source_id'] ) ? (int) $data['source_id'] : $default_source_id;
		$name      = isset( $data['name'] ) ? (string) $data['name'] : '';
		$enabled   = ! isset( $data['enabled'] ) || (bool) $data['enabled'] || 'yes' === $data['enabled'] || '1' === $data['enabled'];

		$scope = $data['calculation_scope'] ?? self::SCOPE_INDIVIDUAL;
		if ( ! in_array( $scope, array( self::SCOPE_INDIVIDUAL, self::SCOPE_VARIABLE_COMBINED, self::SCOPE_CATEGORY, self::SCOPE_CART_WIDE ), true ) ) {
			$scope = self::SCOPE_INDIVIDUAL;
		}

		$priority = isset( $data['priority'] ) ? (int) $data['priority'] : 10;
		$override = ! isset( $data['override_parent'] ) || (bool) $data['override_parent'] || 'yes' === $data['override_parent'] || '1' === $data['override_parent'];

		$tiers = array();
		if ( isset( $data['tiers'] ) && is_array( $data['tiers'] ) ) {
			foreach ( $data['tiers'] as $tier_data ) {
				if ( is_array( $tier_data ) ) {
					$tiers[] = Tier::from_array( $tier_data );
				}
			}
		}

		return new self(
			$id,
			$source,
			$source_id,
			$name,
			$enabled,
			$scope,
			$priority,
			$tiers,
			$override
		);
	}
}
