<?php
/**
 * Coupon Interaction Handler.
 *
 * @package WPCalibrate\TieredPricing\Cart
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Cart;

use WC_Coupon;
use WC_Product;

/**
 * Class CouponHandler
 */
final class CouponHandler {

	/**
	 * Coupon strategy constants.
	 */
	public const STRATEGY_ALLOW   = 'allow';
	public const STRATEGY_PREVENT = 'prevent';

	/**
	 * Register coupon filter hook.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( $this, 'filter_coupon_valid_for_product' ), 10, 4 );
	}

	/**
	 * Filter whether a coupon is valid for a specific product line item.
	 *
	 * If the merchant has selected 'prevent' coupons on tier-priced items,
	 * return false if this line item has an active tiered pricing rule applied.
	 *
	 * @param bool       $valid Current validity.
	 * @param WC_Product $product Product instance.
	 * @param WC_Coupon  $coupon Coupon instance.
	 * @param array      $values Cart item values.
	 * @return bool
	 */
	public function filter_coupon_valid_for_product( bool $valid, WC_Product $product, WC_Coupon $coupon, array $values ): bool {
		if ( ! $valid ) {
			return false;
		}

		$strategy = get_option( 'wpcttp_coupon_strategy', self::STRATEGY_ALLOW );
		if ( self::STRATEGY_PREVENT !== $strategy ) {
			return $valid;
		}

		// Check if this cart item has tiered pricing applied.
		if ( ! empty( $values['_wpcttp_tiered_price_applied'] ) ) {
			return false;
		}

		return $valid;
	}
}
