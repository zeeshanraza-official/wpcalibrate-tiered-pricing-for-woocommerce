<?php
/**
 * REST and AJAX Live Pricing Controller.
 *
 * @package WPCalibrate\TieredPricing\REST
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\REST;

use WC_Product;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WPCalibrate\TieredPricing\Contracts\PricingEngineInterface;
use WPCalibrate\TieredPricing\WooCommerce\TaxDisplay;

/**
 * Class PricingController
 */
final class PricingController {

	/**
	 * Route namespace.
	 */
	public const REST_NAMESPACE = 'wpcalibrate-tiered-pricing/v1';

	/**
	 * Route path.
	 */
	public const REST_ROUTE = '/calculate-price';

	/**
	 * Constructor.
	 *
	 * @param PricingEngineInterface $pricing_engine Pricing engine instance.
	 */
	public function __construct(
		private readonly PricingEngineInterface $pricing_engine
	) {}

	/**
	 * Register REST and AJAX hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_ajax_wpcttp_calculate_price', array( $this, 'handle_ajax_calculate' ) );
		add_action( 'wp_ajax_nopriv_wpcttp_calculate_price', array( $this, 'handle_ajax_calculate' ) );
	}

	/**
	 * Register REST API route.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				array(
					'methods'             => array( WP_REST_Server::READABLE, WP_REST_Server::CREATABLE ),
					'callback'            => array( $this, 'handle_rest_calculate' ),
					'permission_callback' => '__return_true', // Public price calculations for shoppers.
					'args'                => array(
						'product_id'   => array(
							'required'          => true,
							'type'              => 'integer',
							'validate_callback' => static fn( $param ) => is_numeric( $param ) && (int) $param > 0,
							'sanitize_callback' => 'absint',
						),
						'variation_id' => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 0,
							'validate_callback' => static fn( $param ) => is_numeric( $param ),
							'sanitize_callback' => 'absint',
						),
						'quantity'     => array(
							'required'          => false,
							'type'              => 'number',
							'default'           => 1,
							'validate_callback' => static fn( $param ) => is_numeric( $param ) && (float) $param > 0,
							'sanitize_callback' => static fn( $param ) => max( 1.0, (float) $param ),
						),
					),
				),
			)
		);
	}

	/**
	 * Handle REST calculation request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_rest_calculate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$product_id   = (int) $request->get_param( 'product_id' );
		$variation_id = (int) $request->get_param( 'variation_id' );
		$quantity     = (float) $request->get_param( 'quantity' );

		$response_data = $this->calculate_payload( $product_id, $variation_id, $quantity );
		if ( is_wp_error( $response_data ) ) {
			return $response_data;
		}

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Handle AJAX calculation fallback.
	 *
	 * @return void
	 */
	public function handle_ajax_calculate(): void {
		// Nonce check if present; public fallback allows browsing.
		if ( isset( $_REQUEST['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'wpcttp_live_pricing_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security verification failed.', 'wpcalibrate-tiered-pricing-for-woocommerce' ) ), 403 );
		}

		$product_id   = isset( $_REQUEST['product_id'] ) ? absint( $_REQUEST['product_id'] ) : 0;
		$variation_id = isset( $_REQUEST['variation_id'] ) ? absint( $_REQUEST['variation_id'] ) : 0;
		$quantity     = isset( $_REQUEST['quantity'] ) ? max( 1.0, (float) $_REQUEST['quantity'] ) : 1.0;

		$response_data = $this->calculate_payload( $product_id, $variation_id, $quantity );
		if ( is_wp_error( $response_data ) ) {
			wp_send_json_error( array( 'message' => $response_data->get_error_message() ), 400 );
		}

		wp_send_json_success( $response_data );
	}

	/**
	 * Compute pricing payload for REST / AJAX.
	 *
	 * @param int   $product_id Product ID.
	 * @param int   $variation_id Variation ID.
	 * @param float $quantity Quantity.
	 * @return array<string, mixed>|WP_Error
	 */
	private function calculate_payload( int $product_id, int $variation_id, float $quantity ): array|WP_Error {
		$target_id = $variation_id > 0 ? $variation_id : $product_id;
		$product   = wc_get_product( $target_id );

		if ( ! $product instanceof WC_Product ) {
			return new WP_Error(
				'wpcttp_product_not_found',
				__( 'Product or variation not found.', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
				array( 'status' => 404 )
			);
		}

		$result = $this->pricing_engine->calculate( $product, $quantity );

		$unit_price     = $result->get_adjusted_unit_price();
		$base_price     = $result->get_base_price();
		$line_total_raw = $unit_price * $quantity;

		$unit_price_html = TaxDisplay::format_display_price( $product, $unit_price );
		$base_price_html = TaxDisplay::format_display_price( $product, $base_price );
		$line_total_html = TaxDisplay::format_display_price( $product, $line_total_raw, $quantity );

		$matched_tier = $result->get_matched_tier();

		return array(
			'product_id'                => $product_id,
			'variation_id'              => $variation_id,
			'quantity'                  => $quantity,
			'unit_price'                => $unit_price,
			'unit_price_html'           => $unit_price_html,
			'original_unit_price'       => $base_price,
			'original_unit_price_html'  => $base_price_html,
			'line_total'                => round( $line_total_raw, wc_get_price_decimals() ),
			'line_total_html'           => $line_total_html,
			'discount_amount'           => $result->get_discount_amount(),
			'discount_percentage'       => $result->get_discount_percentage(),
			'is_tiered_pricing_applied' => $result->is_applied(),
			'matched_tier_min'          => $matched_tier ? $matched_tier->get_min_qty() : null,
			'matched_tier_max'          => $matched_tier ? $matched_tier->get_max_qty() : null,
		);
	}
}
