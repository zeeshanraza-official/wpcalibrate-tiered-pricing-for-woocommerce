<?php
/**
 * Product and Variation Admin Controller.
 *
 * @package WPCalibrate\TieredPricing\Admin
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Admin;

use WC_Product;
use WPCalibrate\TieredPricing\Contracts\RuleRepositoryInterface;
use WPCalibrate\TieredPricing\Data\PricingRule;
use WPCalibrate\TieredPricing\Data\Tier;
use WPCalibrate\TieredPricing\Rules\RuleValidator;

/**
 * Class ProductAdmin
 */
final class ProductAdmin {

	/**
	 * Nonce action.
	 */
	public const NONCE_ACTION = 'wpcttp_save_product_rule';

	/**
	 * Nonce name.
	 */
	public const NONCE_NAME = 'wpcttp_product_rule_nonce';

	/**
	 * Constructor.
	 *
	 * @param RuleRepositoryInterface $repository Rule repository.
	 */
	public function __construct(
		private readonly RuleRepositoryInterface $repository
	) {}

	/**
	 * Initialize product admin hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Single product data tab.
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ), 20, 1 );

		// Variable product variations hooks.
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'render_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );

		// Enqueue admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add "Tiered Pricing" tab to WooCommerce product data tabs.
	 *
	 * @param array<string, mixed> $tabs Product tabs.
	 * @return array<string, mixed>
	 */
	public function add_product_data_tab( array $tabs ): array {
		$tabs['wpcttp_tiered_pricing'] = array(
			'label'    => __( 'Tiered Pricing', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
			'target'   => 'wpcttp_product_data_panel',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 65,
		);
		return $tabs;
	}

	/**
	 * Render product data panel content.
	 *
	 * @return void
	 */
	public function render_product_data_panel(): void {
		global $post;
		if ( ! $post ) {
			return;
		}

		$product_id = $post->ID;
		$rule       = $this->repository->get_for_product( $product_id );

		$enabled  = $rule ? $rule->is_enabled() : false;
		$scope    = $rule ? $rule->get_calculation_scope() : PricingRule::SCOPE_INDIVIDUAL;
		$tiers    = $rule ? $rule->get_tiers() : array();

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		?>
		<div id="wpcttp_product_data_panel" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<p class="form-field">
					<label for="wpcttp_enable_pricing">
						<?php esc_html_e( 'Enable Tiered Pricing', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
					</label>
					<input
						type="checkbox"
						class="checkbox"
						name="wpcttp_rule[enabled]"
						id="wpcttp_enable_pricing"
						value="1"
						<?php checked( $enabled, true ); ?>
					/>
					<span class="description">
						<?php esc_html_e( 'Enable quantity-based tiered bulk pricing for this product.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
					</span>
				</p>

				<p class="form-field">
					<label for="wpcttp_calc_scope">
						<?php esc_html_e( 'Calculation Scope', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
					</label>
					<select name="wpcttp_rule[calculation_scope]" id="wpcttp_calc_scope">
						<option value="<?php echo esc_attr( PricingRule::SCOPE_INDIVIDUAL ); ?>" <?php selected( $scope, PricingRule::SCOPE_INDIVIDUAL ); ?>>
							<?php esc_html_e( 'Individual Product Quantity', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
						<option value="<?php echo esc_attr( PricingRule::SCOPE_VARIABLE_COMBINED ); ?>" <?php selected( $scope, PricingRule::SCOPE_VARIABLE_COMBINED ); ?>>
							<?php esc_html_e( 'Variable Product Combined Quantity', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
						<option value="<?php echo esc_attr( PricingRule::SCOPE_CATEGORY ); ?>" <?php selected( $scope, PricingRule::SCOPE_CATEGORY ); ?>>
							<?php esc_html_e( 'Category Quantity', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
						<option value="<?php echo esc_attr( PricingRule::SCOPE_CART_WIDE ); ?>" <?php selected( $scope, PricingRule::SCOPE_CART_WIDE ); ?>>
							<?php esc_html_e( 'Cart-Wide Quantity', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
					</select>
					<span class="description">
						<?php esc_html_e( 'Determines how quantities are aggregated before matching tier thresholds.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
					</span>
				</p>
			</div>

			<div class="options_group wpcttp-tiers-manager">
				<h4 style="margin: 15px 0 5px 12px; font-weight: 600;">
					<?php esc_html_e( 'Quantity Pricing Tiers', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
				</h4>
				<p class="description" style="margin-left: 12px; margin-bottom: 12px;">
					<?php esc_html_e( 'Define minimum and optional maximum quantities. Leave maximum blank for open-ended tiers (e.g., 10+).', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
				</p>

				<?php $this->render_tiers_editor_table( 'wpcttp_rule', $tiers ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render variation custom fields.
	 *
	 * @param int     $loop Loop index.
	 * @param array   $variation_data Variation post data.
	 * @param \WP_Post $variation Variation post.
	 * @return void
	 */
	public function render_variation_fields( int $loop, array $variation_data, \WP_Post $variation ): void {
		$variation_id = $variation->ID;
		$rule         = $this->repository->get_for_variation( $variation_id );

		$override_parent = $rule ? $rule->does_override_parent() : false;
		$enabled         = $rule ? $rule->is_enabled() : false;
		$scope           = $rule ? $rule->get_calculation_scope() : PricingRule::SCOPE_INDIVIDUAL;
		$tiers           = $rule ? $rule->get_tiers() : array();

		$field_prefix = "wpcttp_variation_rule[{$variation_id}]";

		?>
		<div class="wpcttp-variation-tiered-pricing-section" style="border-top: 1px solid #e5e7eb; margin-top: 15px; padding-top: 15px;">
			<h4 style="font-weight: 600; margin-bottom: 10px;">
				<?php esc_html_e( 'WPCalibrate Tiered Pricing (Variation)', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
			</h4>

			<p class="form-row form-row-full">
				<label>
					<input
						type="checkbox"
						class="checkbox wpcttp-variation-override-toggle"
						name="<?php echo esc_attr( "{$field_prefix}[override_parent]" ); ?>"
						value="1"
						<?php checked( $override_parent, true ); ?>
					/>
					<strong><?php esc_html_e( 'Override parent product tiered pricing rules', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></strong>
				</label>
				<span class="description" style="display: block; margin-top: 4px;">
					<?php esc_html_e( 'If unchecked, this variation inherits the parent product pricing rule automatically.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
				</span>
			</p>

			<div class="wpcttp-variation-rule-body" style="<?php echo $override_parent ? '' : 'display:none;'; ?>">
				<p class="form-row form-row-first">
					<label>
						<input
							type="checkbox"
							class="checkbox"
							name="<?php echo esc_attr( "{$field_prefix}[enabled]" ); ?>"
							value="1"
							<?php checked( $enabled, true ); ?>
						/>
						<?php esc_html_e( 'Enable rule for this variation', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
					</label>
				</p>

				<p class="form-row form-row-last">
					<label><?php esc_html_e( 'Calculation Scope', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
					<select name="<?php echo esc_attr( "{$field_prefix}[calculation_scope]" ); ?>">
						<option value="<?php echo esc_attr( PricingRule::SCOPE_INDIVIDUAL ); ?>" <?php selected( $scope, PricingRule::SCOPE_INDIVIDUAL ); ?>>
							<?php esc_html_e( 'Individual Variation Quantity', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
						<option value="<?php echo esc_attr( PricingRule::SCOPE_VARIABLE_COMBINED ); ?>" <?php selected( $scope, PricingRule::SCOPE_VARIABLE_COMBINED ); ?>>
							<?php esc_html_e( 'Variable Product Combined Quantity', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
					</select>
				</p>

				<div class="form-row form-row-full wpcttp-tiers-manager">
					<?php $this->render_tiers_editor_table( $field_prefix, $tiers ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render reusable table for adding, editing, and deleting tiers.
	 *
	 * @param string      $field_prefix HTML field name prefix.
	 * @param array<Tier> $tiers Existing tiers.
	 * @return void
	 */
	public function render_tiers_editor_table( string $field_prefix, array $tiers ): void {
		?>
		<table class="widefat striped wpcttp-admin-tiers-table" style="margin: 0 12px 12px 12px; width: calc(100% - 24px);">
			<thead>
				<tr>
					<th style="width: 20%;"><?php esc_html_e( 'Min Quantity', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></th>
					<th style="width: 20%;"><?php esc_html_e( 'Max Quantity', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></th>
					<th style="width: 25%;"><?php esc_html_e( 'Type', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></th>
					<th style="width: 25%;"><?php esc_html_e( 'Amount / Value', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></th>
					<th style="width: 10%; text-align: center;"><?php esc_html_e( 'Remove', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody class="wpcttp-tiers-tbody">
				<?php
				if ( ! empty( $tiers ) ) :
					foreach ( $tiers as $index => $tier ) :
						?>
						<tr class="wpcttp-tier-editor-row">
							<td>
								<input
									type="number"
									step="1"
									min="1"
									class="short"
									name="<?php echo esc_attr( "{$field_prefix}[tiers][{$index}][min_qty]" ); ?>"
									value="<?php echo esc_attr( (string) $tier->get_min_qty() ); ?>"
									required
								/>
							</td>
							<td>
								<input
									type="number"
									step="1"
									min="1"
									class="short"
									name="<?php echo esc_attr( "{$field_prefix}[tiers][{$index}][max_qty]" ); ?>"
									value="<?php echo esc_attr( null !== $tier->get_max_qty() ? (string) $tier->get_max_qty() : '' ); ?>"
									placeholder="<?php esc_attr_e( 'Open (e.g. 10+)', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>"
								/>
							</td>
							<td>
								<select name="<?php echo esc_attr( "{$field_prefix}[tiers][{$index}][type]" ); ?>">
									<option value="<?php echo esc_attr( Tier::TYPE_FIXED ); ?>" <?php selected( $tier->get_type(), Tier::TYPE_FIXED ); ?>>
										<?php esc_html_e( 'Fixed Unit Price', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
									</option>
									<option value="<?php echo esc_attr( Tier::TYPE_PERCENTAGE ); ?>" <?php selected( $tier->get_type(), Tier::TYPE_PERCENTAGE ); ?>>
										<?php esc_html_e( 'Percentage Discount (%)', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
									</option>
								</select>
							</td>
							<td>
								<input
									type="number"
									step="0.01"
									min="0"
									class="short"
									name="<?php echo esc_attr( "{$field_prefix}[tiers][{$index}][value]" ); ?>"
									value="<?php echo esc_attr( (string) $tier->get_value() ); ?>"
									required
								/>
							</td>
							<td style="text-align: center;">
								<button type="button" class="button button-link-delete wpcttp-remove-tier-btn" aria-label="<?php esc_attr_e( 'Remove tier', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>">
									&times;
								</button>
							</td>
						</tr>
						<?php
					endforeach;
				endif;
				?>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="5">
						<button
							type="button"
							class="button button-secondary wpcttp-add-tier-btn"
							data-prefix="<?php echo esc_attr( $field_prefix ); ?>"
						>
							+ <?php esc_html_e( 'Add Tier', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</button>
					</td>
				</tr>
			</tfoot>
		</table>
		<?php
	}

	/**
	 * Save product metadata for simple and variable parent products.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function save_product_meta( int $product_id ): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return;
		}

		$raw_data = isset( $_POST['wpcttp_rule'] ) && is_array( $_POST['wpcttp_rule'] )
			? wp_unslash( $_POST['wpcttp_rule'] )
			: array();

		if ( empty( $raw_data['enabled'] ) ) {
			$this->repository->delete_for_product( $product_id );
			return;
		}

		$raw_tiers  = isset( $raw_data['tiers'] ) && is_array( $raw_data['tiers'] ) ? $raw_data['tiers'] : array();
		$normalized = RuleValidator::normalize_tiers( $raw_tiers );

		$scope = isset( $raw_data['calculation_scope'] ) ? sanitize_text_field( $raw_data['calculation_scope'] ) : PricingRule::SCOPE_INDIVIDUAL;

		$rule = new PricingRule(
			'product_' . $product_id,
			PricingRule::SOURCE_PRODUCT,
			$product_id,
			get_the_title( $product_id ) ?: '',
			true,
			$scope,
			10,
			$normalized
		);

		$this->repository->save_for_product( $product_id, $rule );
	}

	/**
	 * Save variation specific metadata.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $i Loop index.
	 * @return void
	 */
	public function save_variation_fields( int $variation_id, int $i ): void {
		if ( ! current_user_can( 'edit_post', $variation_id ) ) {
			return;
		}

		if ( ! isset( $_POST['wpcttp_variation_rule'][ $variation_id ] ) || ! is_array( $_POST['wpcttp_variation_rule'][ $variation_id ] ) ) {
			return;
		}

		$raw_data = wp_unslash( $_POST['wpcttp_variation_rule'][ $variation_id ] );

		if ( empty( $raw_data['override_parent'] ) || empty( $raw_data['enabled'] ) ) {
			$this->repository->delete_for_variation( $variation_id );
			return;
		}

		$raw_tiers  = isset( $raw_data['tiers'] ) && is_array( $raw_data['tiers'] ) ? $raw_data['tiers'] : array();
		$normalized = RuleValidator::normalize_tiers( $raw_tiers );
		$scope      = isset( $raw_data['calculation_scope'] ) ? sanitize_text_field( $raw_data['calculation_scope'] ) : PricingRule::SCOPE_INDIVIDUAL;

		$rule = new PricingRule(
			'variation_' . $variation_id,
			PricingRule::SOURCE_VARIATION,
			$variation_id,
			'Variation #' . $variation_id,
			true,
			$scope,
			10,
			$normalized,
			true
		);

		$this->repository->save_for_variation( $variation_id, $rule );
	}

	/**
	 * Enqueue admin scripts and styles for product and settings screens.
	 *
	 * @param string $hook Admin screen hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$is_product_screen  = in_array( $screen->id, array( 'product', 'edit-product' ), true );
		$is_settings_screen = str_contains( $screen->id, 'wpcalibrate' );

		if ( ! $is_product_screen && ! $is_settings_screen ) {
			return;
		}

		wp_enqueue_style(
			'wpcttp-admin',
			WPCALIBRATE_TIERED_PRICING_URL . 'assets/admin/css/admin.css',
			array(),
			WPCALIBRATE_TIERED_PRICING_VERSION
		);

		wp_enqueue_script(
			'wpcttp-admin',
			WPCALIBRATE_TIERED_PRICING_URL . 'assets/admin/js/admin.js',
			array( 'jquery' ),
			WPCALIBRATE_TIERED_PRICING_VERSION,
			true
		);

		wp_localize_script(
			'wpcttp-admin',
			'wpcttp_admin',
			array(
				'i18n' => array(
					'open'       => __( 'Open (e.g. 10+)', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
					'fixed'      => __( 'Fixed Unit Price', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
					'percentage' => __( 'Percentage Discount (%)', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
					'remove'     => __( 'Remove tier', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
				),
			)
		);
	}
}
