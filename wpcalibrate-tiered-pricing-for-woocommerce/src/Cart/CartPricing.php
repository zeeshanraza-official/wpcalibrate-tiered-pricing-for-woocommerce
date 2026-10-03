<?php
/**
 * Cart and Checkout Pricing Integration.
 *
 * @package WPCalibrate\TieredPricing\Cart
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Cart;

use WC_Cart;
use WC_Product;
use WPCalibrate\TieredPricing\Contracts\PricingEngineInterface;
use WPCalibrate\TieredPricing\Data\PricingResult;

/**
 * Class CartPricing
 */
final class CartPricing {

	/**
	 * Recursion protection flag.
	 *
	 * @var bool
	 */
	private bool $is_calculating = false;

	/**
	 * Constructor.
	 *
	 * @param PricingEngineInterface $pricing_engine Pricing engine instance.
	 */
	public function __construct(
		private readonly PricingEngineInterface $pricing_engine
	) {}

	/**
	 * Register cart hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Authoritative server-side price recalculation.
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_cart_totals' ), 10, 1 );

		// Preserve custom item data during session restoration.
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'restore_cart_item_session' ), 10, 3 );

		// Display formatted price with discount indication on cart line items.
		add_filter( 'woocommerce_cart_item_price', array( $this, 'format_cart_item_price' ), 10, 3 );
	}

	/**
	 * Recalculate cart item prices server-side.
	 *
	 * @param WC_Cart $cart Cart instance.
	 * @return void
	 */
	public function calculate_cart_totals( WC_Cart $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( $this->is_calculating ) {
			return;
		}

		$this->is_calculating = true;

		$cart_contents = $cart->get_cart();
		if ( empty( $cart_contents ) ) {
			$this->is_calculating = false;
			return;
		}

		// First pass: Capture and reset each item back to its true unadjusted base price.
		// This strictly prevents compounding on repeated cart recalculation passes.
		foreach ( $cart_contents as $cart_item_key => &$item ) {
			if ( ! isset( $item['data'] ) || ! ( $item['data'] instanceof WC_Product ) ) {
				continue;
			}

			/** @var WC_Product $product */
			$product = $item['data'];

			if ( ! isset( $item['_wpcttp_original_price'] ) ) {
				$item['_wpcttp_original_price'] = (float) $product->get_price();
			} else {
				// Always restore original base price before evaluating.
				$product->set_price( $item['_wpcttp_original_price'] );
			}
		}
		unset( $item );

		// Second pass: Calculate aggregate tiered price using current complete cart context.
		foreach ( $cart_contents as $cart_item_key => &$cart_item ) {
			if ( ! isset( $cart_item['data'] ) || ! ( $cart_item['data'] instanceof WC_Product ) ) {
				continue;
			}

			/** @var WC_Product $product */
			$product  = $cart_item['data'];
			$quantity = isset( $cart_item['quantity'] ) ? (float) $cart_item['quantity'] : 1.0;

			// Run pricing engine with full cart context for cross-variation / category / cart-wide scopes.
			$result = $this->pricing_engine->calculate( $product, $quantity, $cart_contents );

			if ( $result->is_applied() ) {
				$product->set_price( $result->get_adjusted_unit_price() );
				$cart_item['_wpcttp_tiered_price_applied'] = true;
				$cart_item['_wpcttp_result']               = $result->to_array();
			} else {
				$product->set_price( $cart_item['_wpcttp_original_price'] );
				$cart_item['_wpcttp_tiered_price_applied'] = false;
				unset( $cart_item['_wpcttp_result'] );
			}
		}
		unset( $cart_item );

		$this->is_calculating = false;
	}

	/**
	 * Restore original base price and pricing metadata when session is restored.
	 *
	 * @param array<string, mixed> $cart_item Cart item data.
	 * @param array<string, mixed> $values Session values.
	 * @param string               $key Cart item key.
	 * @return array<string, mixed>
	 */
	public function restore_cart_item_session( array $cart_item, array $values, string $key ): array {
		if ( isset( $values['_wpcttp_original_price'] ) ) {
			$cart_item['_wpcttp_original_price'] = (float) $values['_wpcttp_original_price'];
		}
		if ( isset( $values['_wpcttp_tiered_price_applied'] ) ) {
			$cart_item['_wpcttp_tiered_price_applied'] = (bool) $values['_wpcttp_tiered_price_applied'];
		}
		if ( isset( $values['_wpcttp_result'] ) ) {
			$cart_item['_wpcttp_result'] = $values['_wpcttp_result'];
		}

		return $cart_item;
	}

	/**
	 * Format cart item price display to show savings or original price strikethrough if applied.
	 *
	 * @param string               $price_html Formatted price HTML.
	 * @param array<string, mixed> $cart_item Cart item.
	 * @param string               $cart_item_key Item key.
	 * @return string
	 */
	public function format_cart_item_price( string $price_html, array $cart_item, string $cart_item_key ): string {
		if ( empty( $cart_item['_wpcttp_tiered_price_applied'] ) || ! isset( $cart_item['_wpcttp_original_price'], $cart_item['data'] ) ) {
			return $price_html;
		}

		/** @var WC_Product $product */
		$product        = $cart_item['data'];
		$original_price = (float) $cart_item['_wpcttp_original_price'];
		$current_price  = (float) $product->get_price();

		if ( $current_price >= $original_price ) {
			return $price_html;
		}

		$formatted_orig    = wc_price( wc_get_price_to_display( $product, array( 'price' => $original_price ) ) );
		$formatted_current = wc_price( wc_get_price_to_display( $product, array( 'price' => $current_price ) ) );

		return sprintf(
			'<del class="wpcttp-cart-original-price" aria-hidden="true">%1$s</del> <ins class="wpcttp-cart-discounted-price">%2$s</ins>',
			$formatted_orig,
			$formatted_current
		);
	}
}
