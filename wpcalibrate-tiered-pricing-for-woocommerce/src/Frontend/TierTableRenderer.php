<?php
/**
 * Product Page Tiered Pricing Table Renderer.
 *
 * @package WPCalibrate\TieredPricing\Frontend
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Frontend;

use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;
use WPCalibrate\TieredPricing\Contracts\RuleResolverInterface;
use WPCalibrate\TieredPricing\Data\PricingRule;
use WPCalibrate\TieredPricing\Data\Tier;
use WPCalibrate\TieredPricing\Pricing\PricingCalculator;
use WPCalibrate\TieredPricing\WooCommerce\TaxDisplay;

/**
 * Class TierTableRenderer
 */
final class TierTableRenderer {

	/**
	 * Constructor.
	 *
	 * @param RuleResolverInterface $rule_resolver Rule precedence resolver.
	 */
	public function __construct(
		private readonly RuleResolverInterface $rule_resolver
	) {}

	/**
	 * Register frontend display hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		$table_enabled = get_option( 'wpcttp_table_enabled', 'yes' );
		if ( 'yes' !== $table_enabled ) {
			return;
		}

		$position = get_option( 'wpcttp_table_position', 'woocommerce_before_add_to_cart_form' );
		$priority = 15;

		if ( 'woocommerce_before_add_to_cart_button' === $position ) {
			$priority = 5;
		}

		add_action( $position, array( $this, 'render_table' ), $priority );

		// Pass tiered pricing data directly to variable product variation scripts.
		add_filter( 'woocommerce_available_variation', array( $this, 'attach_variation_tiered_data' ), 10, 3 );
	}

	/**
	 * Attach tiered pricing data to WooCommerce available variation payload.
	 *
	 * @param array<string, mixed> $data Variation data.
	 * @param WC_Product_Variable  $product Parent variable product.
	 * @param WC_Product_Variation $variation Variation product.
	 * @return array<string, mixed>
	 */
	public function attach_variation_tiered_data( array $data, WC_Product_Variable $product, WC_Product_Variation $variation ): array {
		$rule = $this->rule_resolver->resolve( $variation );
		if ( null === $rule || ! $rule->is_enabled() || empty( $rule->get_tiers() ) ) {
			$data['wpcttp_has_tiers'] = false;
			return $data;
		}

		$base_info = PricingCalculator::get_base_price( $variation );
		if ( ! $base_info['is_eligible'] ) {
			$data['wpcttp_has_tiers'] = false;
			return $data;
		}

		$tiers_output = $this->build_tiers_display_data( $variation, $rule, $base_info['base_price'] );

		$data['wpcttp_has_tiers'] = ! empty( $tiers_output );
		$data['wpcttp_tiers']     = $tiers_output;
		$data['wpcttp_rule']      = array(
			'source' => $rule->get_source(),
			'scope'  => $rule->get_calculation_scope(),
		);

		return $data;
	}

	/**
	 * Render the pricing table on single product page.
	 *
	 * @return void
	 */
	public function render_table(): void {
		global $product;
		if ( ! ( $product instanceof WC_Product ) ) {
			return;
		}

		// For variable products, render the dynamic container that updates on variation selection.
		if ( $product->is_type( 'variable' ) ) {
			$this->render_variable_container( $product );
			return;
		}

		// Simple / other products.
		$rule = $this->rule_resolver->resolve( $product );
		if ( null === $rule || ! $rule->is_enabled() || empty( $rule->get_tiers() ) ) {
			return;
		}

		$base_info = PricingCalculator::get_base_price( $product );
		if ( ! $base_info['is_eligible'] ) {
			return;
		}

		$tiers_data = $this->build_tiers_display_data( $product, $rule, $base_info['base_price'] );
		if ( empty( $tiers_data ) ) {
			return;
		}

		$this->render_table_html( $product, $tiers_data );
	}

	/**
	 * Render container for variable product tables.
	 *
	 * @param WC_Product $product Parent variable product.
	 * @return void
	 */
	private function render_variable_container( WC_Product $product ): void {
		$title = get_option( 'wpcttp_table_title', __( 'Tiered Bulk Pricing', 'wpcalibrate-tiered-pricing-for-woocommerce' ) );

		echo '<div class="wpcttp-variable-table-wrapper" id="wpcttp-variable-pricing-wrapper" data-product-id="' . esc_attr( (string) $product->get_id() ) . '" aria-live="polite">';
		echo '<div class="wpcttp-table-content" style="display:none;"></div>';
		echo '</div>';
	}

	/**
	 * Render semantic, accessible pricing table HTML.
	 *
	 * @param WC_Product           $product Product.
	 * @param array<int, array<string, mixed>> $tiers_data Tiers display data.
	 * @return void
	 */
	public function render_table_html( WC_Product $product, array $tiers_data ): void {
		$title             = get_option( 'wpcttp_table_title', __( 'Tiered Bulk Pricing', 'wpcalibrate-tiered-pricing-for-woocommerce' ) );
		$qty_label         = get_option( 'wpcttp_col_qty_label', __( 'Quantity', 'wpcalibrate-tiered-pricing-for-woocommerce' ) );
		$price_label       = get_option( 'wpcttp_col_price_label', __( 'Price per Unit', 'wpcalibrate-tiered-pricing-for-woocommerce' ) );
		$savings_label     = get_option( 'wpcttp_col_savings_label', __( 'Savings', 'wpcalibrate-tiered-pricing-for-woocommerce' ) );
		$show_savings      = 'yes' === get_option( 'wpcttp_show_savings', 'yes' );
		$highlight_active  = 'yes' === get_option( 'wpcttp_highlight_active', 'yes' );

		?>
		<div class="wpcttp-pricing-table-container" data-product-id="<?php echo esc_attr( (string) $product->get_id() ); ?>" data-highlight-active="<?php echo esc_attr( $highlight_active ? '1' : '0' ); ?>">
			<?php if ( ! empty( $title ) ) : ?>
				<h3 class="wpcttp-table-title"><?php echo esc_html( $title ); ?></h3>
			<?php endif; ?>

			<table class="wpcttp-pricing-table" role="table" aria-label="<?php echo esc_attr( $title ); ?>">
				<thead>
					<tr role="row">
						<th scope="col" class="wpcttp-col-qty"><?php echo esc_html( $qty_label ); ?></th>
						<th scope="col" class="wpcttp-col-price"><?php echo esc_html( $price_label ); ?></th>
						<?php if ( $show_savings ) : ?>
							<th scope="col" class="wpcttp-col-savings"><?php echo esc_html( $savings_label ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $tiers_data as $tier_item ) : ?>
						<tr
							class="wpcttp-tier-row"
							data-min="<?php echo esc_attr( (string) $tier_item['min_qty'] ); ?>"
							data-max="<?php echo esc_attr( null !== $tier_item['max_qty'] ? (string) $tier_item['max_qty'] : '' ); ?>"
							data-unit-price="<?php echo esc_attr( (string) $tier_item['unit_price'] ); ?>"
							role="row"
						>
							<td class="wpcttp-col-qty" role="cell">
								<?php echo esc_html( $tier_item['qty_display'] ); ?>
							</td>
							<td class="wpcttp-col-price" role="cell">
								<?php echo wp_kses_post( $tier_item['price_display'] ); ?>
							</td>
							<?php if ( $show_savings ) : ?>
								<td class="wpcttp-col-savings" role="cell">
									<?php echo esc_html( $tier_item['savings_display'] ); ?>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="wpcttp-live-summary" aria-live="polite" style="display:none;"></div>
		</div>
		<?php
	}

	/**
	 * Build display data for table rows with tax-aware calculations.
	 *
	 * @param WC_Product  $product Product.
	 * @param PricingRule $rule Matched rule.
	 * @param float       $base_price Configured base price.
	 * @return array<int, array<string, mixed>>
	 */
	public function build_tiers_display_data( WC_Product $product, PricingRule $rule, float $base_price ): array {
		$tiers  = $rule->get_tiers();
		$output = array();

		foreach ( $tiers as $tier ) {
			if ( ! $tier->is_enabled() ) {
				continue;
			}

			$calc = PricingCalculator::calculate_tier_price( $base_price, $tier, $product, $tier->get_min_qty() );

			// Format quantity range.
			if ( $tier->is_open_ended() ) {
				$qty_display = sprintf( '%s+', $tier->get_min_qty() );
			} elseif ( $tier->get_min_qty() === $tier->get_max_qty() ) {
				$qty_display = (string) $tier->get_min_qty();
			} else {
				$qty_display = sprintf( '%s – %s', $tier->get_min_qty(), $tier->get_max_qty() );
			}

			// Format display price with WooCommerce tax display.
			$display_price_html = TaxDisplay::format_display_price( $product, $calc['adjusted_unit_price'] );

			// Savings label.
			if ( $calc['discount_percentage'] > 0.0 ) {
				$savings_display = sprintf( '%s%%', $calc['discount_percentage'] );
			} elseif ( $calc['discount_amount'] > 0.0 ) {
				$savings_display = sprintf( '-%s', TaxDisplay::format_display_price( $product, $calc['discount_amount'], 1, false ) );
			} else {
				$savings_display = '—';
			}

			$output[] = array(
				'min_qty'         => $tier->get_min_qty(),
				'max_qty'         => $tier->get_max_qty(),
				'unit_price'      => $calc['adjusted_unit_price'],
				'qty_display'     => $qty_display,
				'price_display'   => $display_price_html,
				'savings_display' => $savings_display,
			);
		}

		return $output;
	}
}
