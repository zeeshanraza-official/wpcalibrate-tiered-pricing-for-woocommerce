<?php
/**
 * Tier Value Object.
 *
 * @package WPCalibrate\TieredPricing\Data
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Data;

/**
 * Class Tier
 */
final class Tier {

	/**
	 * Pricing type constants.
	 */
	public const TYPE_FIXED      = 'fixed';
	public const TYPE_PERCENTAGE = 'percentage';

	/**
	 * Constructor.
	 *
	 * @param float       $min_qty Minimum quantity (>= 1).
	 * @param ?float      $max_qty Optional maximum quantity. Null indicates open-ended (e.g. 10+).
	 * @param string      $type Adjustment type: 'fixed' or 'percentage'.
	 * @param float       $value Amount (fixed unit price or percentage discount 0-100).
	 * @param bool        $enabled Whether tier is enabled.
	 */
	public function __construct(
		private readonly float $min_qty,
		private readonly ?float $max_qty,
		private readonly string $type,
		private readonly float $value,
		private readonly bool $enabled = true
	) {}

	/**
	 * Get minimum quantity.
	 *
	 * @return float
	 */
	public function get_min_qty(): float {
		return $this->min_qty;
	}

	/**
	 * Get maximum quantity or null if open-ended.
	 *
	 * @return ?float
	 */
	public function get_max_qty(): ?float {
		return $this->max_qty;
	}

	/**
	 * Whether this tier has no upper limit.
	 *
	 * @return bool
	 */
	public function is_open_ended(): bool {
		return null === $this->max_qty;
	}

	/**
	 * Get adjustment type ('fixed' or 'percentage').
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Get value.
	 *
	 * @return float
	 */
	public function get_value(): float {
		return $this->value;
	}

	/**
	 * Whether tier is active/enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Check if a given quantity falls into this tier range.
	 *
	 * @param float|int $qty Quantity to test.
	 * @return bool
	 */
	public function matches( float|int $qty ): bool {
		if ( ! $this->enabled ) {
			return false;
		}

		$qty = (float) $qty;

		if ( $qty < $this->min_qty ) {
			return false;
		}

		if ( null !== $this->max_qty && $qty > $this->max_qty ) {
			return false;
		}

		return true;
	}

	/**
	 * Convert to array for storage or serialization.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'min_qty' => $this->min_qty,
			'max_qty' => $this->max_qty,
			'type'    => $this->type,
			'value'   => $this->value,
			'enabled' => $this->enabled,
		);
	}

	/**
	 * Instantiate from array.
	 *
	 * @param array<string, mixed> $data Array representation.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$min_qty = isset( $data['min_qty'] ) ? (float) $data['min_qty'] : 1.0;
		if ( $min_qty < 1.0 ) {
			$min_qty = 1.0;
		}

		$max_qty = null;
		if ( isset( $data['max_qty'] ) && '' !== $data['max_qty'] && null !== $data['max_qty'] ) {
			$max_val = (float) $data['max_qty'];
			if ( $max_val >= $min_qty ) {
				$max_qty = $max_val;
			}
		}

		$type = isset( $data['type'] ) && self::TYPE_PERCENTAGE === $data['type']
			? self::TYPE_PERCENTAGE
			: self::TYPE_FIXED;

		$value = isset( $data['value'] ) ? (float) $data['value'] : 0.0;
		if ( $value < 0.0 ) {
			$value = 0.0;
		}
		if ( self::TYPE_PERCENTAGE === $type && $value > 100.0 ) {
			$value = 100.0;
		}

		$enabled = ! isset( $data['enabled'] ) || (bool) $data['enabled'] || 'yes' === $data['enabled'] || '1' === $data['enabled'];

		return new self( $min_qty, $max_qty, $type, $value, $enabled );
	}
}
