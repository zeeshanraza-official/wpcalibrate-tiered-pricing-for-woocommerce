<?php
/**
 * WooCommerce Blocks and Store API Compatibility.
 *
 * @package WPCalibrate\TieredPricing\Compatibility
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Compatibility;

use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;
use Automattic\WooCommerce\StoreApi\StoreApi;

/**
 * Class BlocksStoreApi
 */
final class BlocksStoreApi {

	/**
	 * Identifier for Store API extension data.
	 */
	public const EXTENSION_IDENTIFIER = 'wpcalibrate_tiered_pricing';

	/**
	 * Register Store API hooks and schema extensions.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register Store API endpoint data extension if Store API is available.
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_store_api_extension' ) );
	}

	/**
	 * Register endpoint data extension for Cart/Checkout Blocks.
	 *
	 * @return void
	 */
	public function register_store_api_extension(): void {
		if ( ! class_exists( StoreApi::class ) || ! class_exists( ExtendSchema::class ) ) {
			return;
		}

		try {
			/** @var ExtendSchema $extend_schema */
			$extend_schema = StoreApi::container()->get( ExtendSchema::class );

			$extend_schema->register_endpoint_data(
				array(
					'endpoint'        => CartItemSchema::IDENTIFIER,
					'namespace'       => self::EXTENSION_IDENTIFIER,
					'data_callback'   => array( $this, 'get_cart_item_block_data' ),
					'schema_callback' => array( $this, 'get_cart_item_block_schema' ),
					'schema_type'     => ARRAY_A,
				)
			);
		} catch ( \Throwable $e ) {
			// Fail safely if Store API schema registration throws in non-standard context.
		}
	}

	/**
	 * Provide tiered pricing data for Store API cart item schema.
	 *
	 * @param array<string, mixed> $cart_item Cart item data.
	 * @return array<string, mixed>
	 */
	public function get_cart_item_block_data( array $cart_item ): array {
		$is_applied = ! empty( $cart_item['_wpcttp_tiered_price_applied'] );
		$original   = isset( $cart_item['_wpcttp_original_price'] ) ? (float) $cart_item['_wpcttp_original_price'] : 0.0;
		$result     = $cart_item['_wpcttp_result'] ?? null;

		return array(
			'is_tiered_pricing_applied' => $is_applied,
			'original_unit_price'       => $original,
			'rule_source'               => is_array( $result ) && isset( $result['rule_source'] ) ? (string) $result['rule_source'] : 'none',
			'pricing_type'              => is_array( $result ) && isset( $result['pricing_type'] ) ? (string) $result['pricing_type'] : 'none',
			'discount_amount'           => is_array( $result ) && isset( $result['discount_amount'] ) ? (float) $result['discount_amount'] : 0.0,
			'discount_percentage'       => is_array( $result ) && isset( $result['discount_percentage'] ) ? (float) $result['discount_percentage'] : 0.0,
		);
	}

	/**
	 * Provide schema callback for Store API data extension.
	 *
	 * @return array<string, mixed>
	 */
	public function get_cart_item_block_schema(): array {
		return array(
			'is_tiered_pricing_applied' => array(
				'description' => __( 'Whether tiered bulk pricing was applied to this line item.', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'original_unit_price'       => array(
				'description' => __( 'Original base unit price before tier adjustment.', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
				'type'        => 'number',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'rule_source'               => array(
				'description' => __( 'Winning rule source for the tier adjustment.', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'pricing_type'              => array(
				'description' => __( 'Tier pricing type applied.', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'discount_amount'           => array(
				'description' => __( 'Unit monetary discount amount applied.', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
				'type'        => 'number',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'discount_percentage'       => array(
				'description' => __( 'Percentage discount applied.', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
				'type'        => 'number',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		);
	}
}
