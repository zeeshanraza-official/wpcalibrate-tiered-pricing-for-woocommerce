<?php
/**
 * Frontend Live Pricing Script Enqueuer.
 *
 * @package WPCalibrate\TieredPricing\Frontend
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Frontend;

/**
 * Class LivePricing
 */
final class LivePricing {

	/**
	 * Register frontend asset hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue frontend styles and scripts only when relevant.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! is_product() ) {
			return;
		}

		$enabled = get_option( 'wpcttp_enabled', 'yes' );
		if ( 'yes' !== $enabled ) {
			return;
		}

		// Frontend CSS.
		wp_enqueue_style(
			'wpcttp-frontend',
			WPCALIBRATE_TIERED_PRICING_URL . 'assets/frontend/css/tiered-pricing.css',
			array(),
			WPCALIBRATE_TIERED_PRICING_VERSION
		);

		// Frontend JS.
		wp_enqueue_script(
			'wpcttp-frontend',
			WPCALIBRATE_TIERED_PRICING_URL . 'assets/frontend/js/tiered-pricing.js',
			array( 'jquery' ),
			WPCALIBRATE_TIERED_PRICING_VERSION,
			true
		);

		wp_localize_script(
			'wpcttp-frontend',
			'wpcttp_params',
			array(
				'rest_url'         => esc_url_raw( rest_url( 'wpcalibrate-tiered-pricing/v1/calculate-price' ) ),
				'ajax_url'         => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'nonce'            => wp_create_nonce( 'wpcttp_live_pricing_nonce' ),
				'show_line_total'  => 'yes' === get_option( 'wpcttp_show_line_total', 'no' ),
				'highlight_active' => 'yes' === get_option( 'wpcttp_highlight_active', 'yes' ),
				'i18n'             => array(
					'unit_price' => __( 'Unit Price:', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
					'line_total' => __( 'Subtotal:', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
					'savings'    => __( 'You save:', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
				),
			)
		);
	}
}
