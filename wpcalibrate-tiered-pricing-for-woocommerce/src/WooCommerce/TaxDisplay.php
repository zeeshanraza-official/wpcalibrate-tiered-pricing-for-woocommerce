<?php
/**
 * Tax-Aware Price Display Helper.
 *
 * @package WPCalibrate\TieredPricing\WooCommerce
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\WooCommerce;

use WC_Product;

/**
 * Class TaxDisplay
 */
final class TaxDisplay {

	/**
	 * Get display price according to store tax settings.
	 *
	 * @param WC_Product $product Product instance.
	 * @param float      $price Unit or raw price.
	 * @param float|int  $qty Quantity.
	 * @return float
	 */
	public static function get_price_to_display( WC_Product $product, float $price, float|int $qty = 1 ): float {
		if ( ! function_exists( 'wc_get_price_to_display' ) ) {
			return $price;
		}

		return (float) wc_get_price_to_display(
			$product,
			array(
				'price' => $price,
				'qty'   => $qty,
			)
		);
	}

	/**
	 * Format price for HTML output including currency formatting and tax suffix.
	 *
	 * @param WC_Product $product Product instance.
	 * @param float      $price Unit price before tax calculation.
	 * @param float|int  $qty Quantity.
	 * @param bool       $include_suffix Whether to include WooCommerce tax suffix.
	 * @return string
	 */
	public static function format_display_price(
		WC_Product $product,
		float $price,
		float|int $qty = 1,
		bool $include_suffix = true
	): string {
		$display_price  = self::get_price_to_display( $product, $price, $qty );
		$formatted_html = function_exists( 'wc_price' ) ? wc_price( $display_price ) : sprintf( '%.2f', $display_price );

		if ( $include_suffix && function_exists( 'wc_tax_enabled' ) && wc_tax_enabled() ) {
			$suffix = $product->get_price_suffix();
			if ( ! empty( $suffix ) ) {
				$formatted_html .= ' ' . $suffix;
			}
		}

		return $formatted_html;
	}
}
