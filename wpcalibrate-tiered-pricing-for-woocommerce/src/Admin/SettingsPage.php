<?php
/**
 * Plugin Settings Page Controller.
 *
 * @package WPCalibrate\TieredPricing\Admin
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Admin;

use WPCalibrate\TieredPricing\Contracts\LicenseServiceInterface;
use WPCalibrate\TieredPricing\Contracts\RuleRepositoryInterface;
use WPCalibrate\TieredPricing\Data\PricingRule;
use WPCalibrate\TieredPricing\Pricing\PricingCalculator;
use WPCalibrate\TieredPricing\Cart\CouponHandler;
use WPCalibrate\TieredPricing\Rules\RuleValidator;

/**
 * Class SettingsPage
 */
final class SettingsPage {

	/**
	 * Constructor.
	 *
	 * @param RuleRepositoryInterface $repository Rule repository.
	 * @param LicenseServiceInterface $license_service License service.
	 * @param CategoryRuleAdmin       $category_admin Category rule admin.
	 * @param ProductAdmin            $product_admin Product admin for repeater rendering.
	 */
	public function __construct(
		private readonly RuleRepositoryInterface $repository,
		private readonly LicenseServiceInterface $license_service,
		private readonly CategoryRuleAdmin $category_admin,
		private readonly ProductAdmin $product_admin
	) {}

	/**
	 * Initialize settings hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'handle_save' ) );
	}

	/**
	 * Handle form submission for settings tabs.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		if ( ! isset( $_POST['wpcttp_save_settings'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to modify these settings.', 'wpcalibrate-tiered-pricing-for-woocommerce' ) );
		}

		check_admin_referer( 'wpcttp_save_settings_nonce', 'wpcttp_nonce' );

		$tab = isset( $_POST['current_tab'] ) ? sanitize_key( $_POST['current_tab'] ) : 'general';

		switch ( $tab ) {
			case 'general':
				update_option( 'wpcttp_enabled', isset( $_POST['wpcttp_enabled'] ) ? 'yes' : 'no' );
				update_option( 'wpcttp_debug_logging', isset( $_POST['wpcttp_debug_logging'] ) ? 'yes' : 'no' );
				update_option( 'wpcttp_delete_data_on_uninstall', isset( $_POST['wpcttp_delete_data_on_uninstall'] ) ? 'yes' : 'no' );
				break;

			case 'behaviour':
				$base_strat = isset( $_POST['wpcttp_base_price_strategy'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcttp_base_price_strategy'] ) ) : PricingCalculator::STRATEGY_CURRENT_EFFECTIVE;
				if ( in_array( $base_strat, array( PricingCalculator::STRATEGY_CURRENT_EFFECTIVE, PricingCalculator::STRATEGY_REGULAR, PricingCalculator::STRATEGY_DISABLE_ON_SALE ), true ) ) {
					update_option( 'wpcttp_base_price_strategy', $base_strat );
				}
				$def_scope = isset( $_POST['wpcttp_default_scope'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcttp_default_scope'] ) ) : PricingRule::SCOPE_INDIVIDUAL;
				update_option( 'wpcttp_default_scope', $def_scope );
				break;

			case 'display':
				update_option( 'wpcttp_table_enabled', isset( $_POST['wpcttp_table_enabled'] ) ? 'yes' : 'no' );
				update_option( 'wpcttp_table_position', isset( $_POST['wpcttp_table_position'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcttp_table_position'] ) ) : 'woocommerce_before_add_to_cart_form' );
				update_option( 'wpcttp_table_title', isset( $_POST['wpcttp_table_title'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcttp_table_title'] ) ) : '' );
				update_option( 'wpcttp_col_qty_label', isset( $_POST['wpcttp_col_qty_label'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcttp_col_qty_label'] ) ) : '' );
				update_option( 'wpcttp_col_price_label', isset( $_POST['wpcttp_col_price_label'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcttp_col_price_label'] ) ) : '' );
				update_option( 'wpcttp_col_savings_label', isset( $_POST['wpcttp_col_savings_label'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcttp_col_savings_label'] ) ) : '' );
				update_option( 'wpcttp_show_savings', isset( $_POST['wpcttp_show_savings'] ) ? 'yes' : 'no' );
				update_option( 'wpcttp_highlight_active', isset( $_POST['wpcttp_highlight_active'] ) ? 'yes' : 'no' );
				update_option( 'wpcttp_show_line_total', isset( $_POST['wpcttp_show_line_total'] ) ? 'yes' : 'no' );
				break;

			case 'coupons_sales':
				$coupon_strat = isset( $_POST['wpcttp_coupon_strategy'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcttp_coupon_strategy'] ) ) : CouponHandler::STRATEGY_ALLOW;
				if ( in_array( $coupon_strat, array( CouponHandler::STRATEGY_ALLOW, CouponHandler::STRATEGY_PREVENT ), true ) ) {
					update_option( 'wpcttp_coupon_strategy', $coupon_strat );
				}
				$base_strat = isset( $_POST['wpcttp_base_price_strategy'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcttp_base_price_strategy'] ) ) : PricingCalculator::STRATEGY_CURRENT_EFFECTIVE;
				if ( in_array( $base_strat, array( PricingCalculator::STRATEGY_CURRENT_EFFECTIVE, PricingCalculator::STRATEGY_REGULAR, PricingCalculator::STRATEGY_DISABLE_ON_SALE ), true ) ) {
					update_option( 'wpcttp_base_price_strategy', $base_strat );
				}
				break;

			case 'rules':
				// 1. Global rule
				$global_enabled = isset( $_POST['wpcttp_global_rule_enabled'] ) ? 'yes' : 'no';
				update_option( 'wpcttp_global_rule_enabled', $global_enabled );

				if ( isset( $_POST['wpcttp_global_rule'] ) && is_array( $_POST['wpcttp_global_rule'] ) ) {
					$raw_global = wp_unslash( $_POST['wpcttp_global_rule'] );
					$norm_tiers = RuleValidator::normalize_tiers( $raw_global['tiers'] ?? array() );
					$scope      = sanitize_text_field( $raw_global['calculation_scope'] ?? PricingRule::SCOPE_CART_WIDE );

					$global_rule = new PricingRule(
						'global',
						PricingRule::SOURCE_GLOBAL,
						0,
						__( 'Global Fallback Rule', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
						'yes' === $global_enabled,
						$scope,
						1,
						$norm_tiers
					);
					$this->repository->save_global_rule( $global_rule );
				}

				// 2. Category rule add/update
				if ( ! empty( $_POST['new_category_rule']['term_id'] ) ) {
					$term_id  = absint( $_POST['new_category_rule']['term_id'] );
					$raw_cat  = wp_unslash( $_POST['new_category_rule'] );
					$this->category_admin->save_rule( $term_id, $raw_cat );
				}
				break;

			case 'license':
				if ( isset( $_POST['wpcttp_license_action'] ) ) {
					$action = sanitize_key( $_POST['wpcttp_license_action'] );
					if ( 'activate' === $action && ! empty( $_POST['wpcttp_license_key'] ) ) {
						$res = $this->license_service->activate( sanitize_text_field( wp_unslash( $_POST['wpcttp_license_key'] ) ) );
						add_settings_error( 'wpcttp_notices', 'wpcttp_license_msg', $res['message'], $res['success'] ? 'updated' : 'notice-warning' );
					} elseif ( 'deactivate' === $action ) {
						$res = $this->license_service->deactivate();
						add_settings_error( 'wpcttp_notices', 'wpcttp_license_msg', $res['message'], 'updated' );
					}
				}
				break;
		}

		add_settings_error( 'wpcttp_notices', 'wpcttp_saved', __( 'Settings successfully saved.', 'wpcalibrate-tiered-pricing-for-woocommerce' ), 'updated' );
	}

	/**
	 * Render settings screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wpcalibrate-tiered-pricing-for-woocommerce' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

		// Determine the active admin page slug so tab links stay on the exact page slug accessed.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page_slug = isset( $_GET['page'] ) && in_array( $_GET['page'], array( AdminMenu::PARENT_SLUG, AdminMenu::SUBMENU_SLUG ), true )
			? sanitize_key( $_GET['page'] )
			: AdminMenu::PARENT_SLUG;

		$tabs = array(
			'general'       => __( 'General', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
			'behaviour'     => __( 'Pricing Behaviour', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
			'display'       => __( 'Display', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
			'coupons_sales' => __( 'Coupons & Sales', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
			'rules'         => __( 'Rules', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
			'license'       => __( 'License', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
			'support'       => __( 'Support', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
		);

		?>
		<div class="wrap wpcttp-settings-wrap">
			<!-- Branded Header -->
			<div class="wpcttp-admin-header">
				<div class="wpcttp-admin-header-brand">
					<img
						src="<?php echo esc_url( WPCALIBRATE_TIERED_PRICING_URL . 'assets/branding/icon-dark.png' ); ?>"
						alt="<?php esc_attr_e( 'WPCalibrate', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>"
						class="wpcttp-logo"
					/>
					<div>
						<h1>
							<?php esc_html_e( 'Tiered Pricing for WooCommerce', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
							<span class="wpcttp-version-badge">v<?php echo esc_html( WPCALIBRATE_TIERED_PRICING_VERSION ); ?></span>
						</h1>
					</div>
				</div>
			</div>

			<?php settings_errors( 'wpcttp_notices' ); ?>

			<!-- Navigation Tabs -->
			<nav class="nav-tab-wrapper wpcttp-nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings Navigation', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>">
				<?php foreach ( $tabs as $tab_id => $tab_title ) : ?>
					<a
						href="<?php echo esc_url( add_query_arg( array( 'page' => $page_slug, 'tab' => $tab_id ), admin_url( 'admin.php' ) ) ); ?>"
						class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>"
					>
						<?php echo esc_html( $tab_title ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<!-- Settings Panel Content -->
			<div class="wpcttp-settings-panel">
				<form method="post" action="<?php echo esc_url( add_query_arg( array( 'page' => $page_slug, 'tab' => $current_tab ), admin_url( 'admin.php' ) ) ); ?>">
					<?php wp_nonce_field( 'wpcttp_save_settings_nonce', 'wpcttp_nonce' ); ?>
					<input type="hidden" name="current_tab" value="<?php echo esc_attr( $current_tab ); ?>" />

					<?php
					switch ( $current_tab ) {
						case 'behaviour':
							$this->render_tab_behaviour();
							break;
						case 'display':
							$this->render_tab_display();
							break;
						case 'coupons_sales':
							$this->render_tab_coupons_sales();
							break;
						case 'rules':
							$this->render_tab_rules();
							break;
						case 'license':
							$this->render_tab_license();
							break;
						case 'support':
							$this->render_tab_support();
							break;
						case 'general':
						default:
							$this->render_tab_general();
							break;
					}
					?>

					<?php if ( 'support' !== $current_tab ) : ?>
						<p class="submit" style="margin-top: 1.5rem;">
							<input
								type="submit"
								name="wpcttp_save_settings"
								id="submit"
								class="button button-primary"
								value="<?php esc_attr_e( 'Save Changes', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>"
							/>
						</p>
					<?php endif; ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Tab: General.
	 *
	 * @return void
	 */
	private function render_tab_general(): void {
		$enabled     = 'yes' === get_option( 'wpcttp_enabled', 'yes' );
		$debug       = 'yes' === get_option( 'wpcttp_debug_logging', 'no' );
		$delete_data = 'yes' === get_option( 'wpcttp_delete_data_on_uninstall', 'no' );

		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="wpcttp_enabled"><?php esc_html_e( 'Enable Tiered Pricing', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="wpcttp_enabled" id="wpcttp_enabled" value="1" <?php checked( $enabled, true ); ?> />
						<?php esc_html_e( 'Globally enable tiered bulk pricing calculations and tables across the store.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wpcttp_debug_logging"><?php esc_html_e( 'Debug Logging', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="wpcttp_debug_logging" id="wpcttp_debug_logging" value="1" <?php checked( $debug, true ); ?> />
						<?php esc_html_e( 'Log detailed pricing evaluation diagnostics to WooCommerce > Status > Logs (wpcalibrate-tiered-pricing).', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wpcttp_delete_data_on_uninstall"><?php esc_html_e( 'Delete Data on Uninstall', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="wpcttp_delete_data_on_uninstall" id="wpcttp_delete_data_on_uninstall" value="1" <?php checked( $delete_data, true ); ?> />
						<span style="color: #b91c1c;">
							<?php esc_html_e( 'Opt-in to delete all plugin options, transients, and rule metadata upon plugin uninstallation.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</span>
					</label>
					<p class="description">
						<?php esc_html_e( 'When unchecked (default), your pricing rules and settings are safely preserved if you deactivate or uninstall the plugin.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Tab: Pricing Behaviour.
	 *
	 * @return void
	 */
	private function render_tab_behaviour(): void {
		$strategy  = get_option( 'wpcttp_base_price_strategy', PricingCalculator::STRATEGY_CURRENT_EFFECTIVE );
		$def_scope = get_option( 'wpcttp_default_scope', PricingRule::SCOPE_INDIVIDUAL );

		?>
		<div class="wpcttp-card">
			<h3 style="margin-top: 0; font-size: 1.05rem;"><?php esc_html_e( 'Deterministic Rule Precedence Order', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></h3>
			<p style="margin-bottom: 0;">
				<?php esc_html_e( 'When calculating the tiered price for any line item, the plugin evaluates rules in this exact order until a winning rule is found:', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
			</p>
			<ol style="margin-top: 8px; margin-bottom: 0; padding-left: 20px;">
				<li><strong><?php esc_html_e( 'Variation Rule:', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Explicit rule configured on the variation.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></li>
				<li><strong><?php esc_html_e( 'Product Parent Rule:', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Rule set on the simple product or parent variable product.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></li>
				<li><strong><?php esc_html_e( 'Product Category Rule:', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Rule matching the product’s categories (sorted by priority).', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></li>
				<li><strong><?php esc_html_e( 'Global Rule:', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Store-wide fallback rule if enabled.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></li>
			</ol>
		</div>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="wpcttp_base_price_strategy"><?php esc_html_e( 'Base Price & Sale Price Strategy', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
				</th>
				<td>
					<select name="wpcttp_base_price_strategy" id="wpcttp_base_price_strategy">
						<option value="<?php echo esc_attr( PricingCalculator::STRATEGY_CURRENT_EFFECTIVE ); ?>" <?php selected( $strategy, PricingCalculator::STRATEGY_CURRENT_EFFECTIVE ); ?>>
							<?php esc_html_e( 'Current Effective Price (uses active sale price where applicable)', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
						<option value="<?php echo esc_attr( PricingCalculator::STRATEGY_REGULAR ); ?>" <?php selected( $strategy, PricingCalculator::STRATEGY_REGULAR ); ?>>
							<?php esc_html_e( 'Regular Price (calculates tiers from regular price)', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
						<option value="<?php echo esc_attr( PricingCalculator::STRATEGY_DISABLE_ON_SALE ); ?>" <?php selected( $strategy, PricingCalculator::STRATEGY_DISABLE_ON_SALE ); ?>>
							<?php esc_html_e( 'Disable Tier Pricing While On Sale (skips tiers if product has an active sale price)', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wpcttp_default_scope"><?php esc_html_e( 'Default Calculation Scope', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
				</th>
				<td>
					<select name="wpcttp_default_scope" id="wpcttp_default_scope">
						<option value="<?php echo esc_attr( PricingRule::SCOPE_INDIVIDUAL ); ?>" <?php selected( $def_scope, PricingRule::SCOPE_INDIVIDUAL ); ?>>
							<?php esc_html_e( 'Individual Product Quantity', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
						<option value="<?php echo esc_attr( PricingRule::SCOPE_VARIABLE_COMBINED ); ?>" <?php selected( $def_scope, PricingRule::SCOPE_VARIABLE_COMBINED ); ?>>
							<?php esc_html_e( 'Variable Product Combined Quantity', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
						<option value="<?php echo esc_attr( PricingRule::SCOPE_CATEGORY ); ?>" <?php selected( $def_scope, PricingRule::SCOPE_CATEGORY ); ?>>
							<?php esc_html_e( 'Category Quantity', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
						<option value="<?php echo esc_attr( PricingRule::SCOPE_CART_WIDE ); ?>" <?php selected( $def_scope, PricingRule::SCOPE_CART_WIDE ); ?>>
							<?php esc_html_e( 'Cart-Wide Quantity', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Tab: Display.
	 *
	 * @return void
	 */
	private function render_tab_display(): void {
		$table_enabled = 'yes' === get_option( 'wpcttp_table_enabled', 'yes' );
		$position      = get_option( 'wpcttp_table_position', 'woocommerce_before_add_to_cart_form' );
		$title         = get_option( 'wpcttp_table_title', __( 'Tiered Bulk Pricing', 'wpcalibrate-tiered-pricing-for-woocommerce' ) );
		$col_qty       = get_option( 'wpcttp_col_qty_label', __( 'Quantity', 'wpcalibrate-tiered-pricing-for-woocommerce' ) );
		$col_price     = get_option( 'wpcttp_col_price_label', __( 'Price per Unit', 'wpcalibrate-tiered-pricing-for-woocommerce' ) );
		$col_savings   = get_option( 'wpcttp_col_savings_label', __( 'Savings', 'wpcalibrate-tiered-pricing-for-woocommerce' ) );
		$show_savings  = 'yes' === get_option( 'wpcttp_show_savings', 'yes' );
		$highlight     = 'yes' === get_option( 'wpcttp_highlight_active', 'yes' );
		$show_total    = 'yes' === get_option( 'wpcttp_show_line_total', 'no' );

		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="wpcttp_table_enabled"><?php esc_html_e( 'Enable Frontend Table', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="wpcttp_table_enabled" id="wpcttp_table_enabled" value="1" <?php checked( $table_enabled, true ); ?> />
						<?php esc_html_e( 'Display the tiered pricing table on single product pages.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wpcttp_table_position"><?php esc_html_e( 'Display Position', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
				</th>
				<td>
					<select name="wpcttp_table_position" id="wpcttp_table_position">
						<option value="woocommerce_before_add_to_cart_form" <?php selected( $position, 'woocommerce_before_add_to_cart_form' ); ?>>
							<?php esc_html_e( 'Before add-to-cart form', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
						<option value="woocommerce_before_add_to_cart_button" <?php selected( $position, 'woocommerce_before_add_to_cart_button' ); ?>>
							<?php esc_html_e( 'Before add-to-cart button', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
						<option value="woocommerce_after_add_to_cart_form" <?php selected( $position, 'woocommerce_after_add_to_cart_form' ); ?>>
							<?php esc_html_e( 'After add-to-cart form', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
						<option value="woocommerce_after_single_product_summary" <?php selected( $position, 'woocommerce_after_single_product_summary' ); ?>>
							<?php esc_html_e( 'After single product summary', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wpcttp_table_title"><?php esc_html_e( 'Table Title', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="wpcttp_table_title" id="wpcttp_table_title" value="<?php echo esc_attr( $title ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wpcttp_col_qty_label"><?php esc_html_e( 'Quantity Column Label', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="wpcttp_col_qty_label" id="wpcttp_col_qty_label" value="<?php echo esc_attr( $col_qty ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wpcttp_col_price_label"><?php esc_html_e( 'Price Column Label', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="wpcttp_col_price_label" id="wpcttp_col_price_label" value="<?php echo esc_attr( $col_price ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wpcttp_show_savings"><?php esc_html_e( 'Savings Column', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="wpcttp_show_savings" id="wpcttp_show_savings" value="1" <?php checked( $show_savings, true ); ?> />
						<?php esc_html_e( 'Display a column showing customer savings (percentage or monetary).', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wpcttp_col_savings_label"><?php esc_html_e( 'Savings Column Label', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="wpcttp_col_savings_label" id="wpcttp_col_savings_label" value="<?php echo esc_attr( $col_savings ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wpcttp_highlight_active"><?php esc_html_e( 'Highlight Active Tier', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="wpcttp_highlight_active" id="wpcttp_highlight_active" value="1" <?php checked( $highlight, true ); ?> />
						<?php esc_html_e( 'Dynamically highlight table row corresponding to the quantity entered by customer.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wpcttp_show_line_total"><?php esc_html_e( 'Live Total Preview', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="wpcttp_show_line_total" id="wpcttp_show_line_total" value="1" <?php checked( $show_total, true ); ?> />
						<?php esc_html_e( 'Display dynamic live unit price and subtotal preview under the table as quantity changes.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Tab: Coupons & Sales.
	 *
	 * @return void
	 */
	private function render_tab_coupons_sales(): void {
		$coupon_strat = get_option( 'wpcttp_coupon_strategy', CouponHandler::STRATEGY_ALLOW );
		$base_strat   = get_option( 'wpcttp_base_price_strategy', PricingCalculator::STRATEGY_CURRENT_EFFECTIVE );

		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="wpcttp_coupon_strategy"><?php esc_html_e( 'Coupon Interaction', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
				</th>
				<td>
					<fieldset>
						<label style="display: block; margin-bottom: 8px;">
							<input type="radio" name="wpcttp_coupon_strategy" value="<?php echo esc_attr( CouponHandler::STRATEGY_ALLOW ); ?>" <?php checked( $coupon_strat, CouponHandler::STRATEGY_ALLOW ); ?> />
							<strong><?php esc_html_e( 'Allow WooCommerce coupons on tier-priced products (Default)', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></strong>
							<span class="description" style="display: block; margin-left: 20px;">
								<?php esc_html_e( 'Coupons can apply discounts on top of tiered prices.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
							</span>
						</label>
						<label style="display: block;">
							<input type="radio" name="wpcttp_coupon_strategy" value="<?php echo esc_attr( CouponHandler::STRATEGY_PREVENT ); ?>" <?php checked( $coupon_strat, CouponHandler::STRATEGY_PREVENT ); ?> />
							<strong><?php esc_html_e( 'Prevent coupons from applying to tier-priced products', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></strong>
							<span class="description" style="display: block; margin-left: 20px;">
								<?php esc_html_e( 'Item-level coupon exclusion: items that receive tiered pricing will not be discounted by coupons, while non-tiered items remain eligible.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
							</span>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wpcttp_base_price_strategy_cs"><?php esc_html_e( 'Sale Price Behaviour', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
				</th>
				<td>
					<select name="wpcttp_base_price_strategy" id="wpcttp_base_price_strategy_cs">
						<option value="<?php echo esc_attr( PricingCalculator::STRATEGY_CURRENT_EFFECTIVE ); ?>" <?php selected( $base_strat, PricingCalculator::STRATEGY_CURRENT_EFFECTIVE ); ?>>
							<?php esc_html_e( 'Current Effective Price (discounts applied to sale price)', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
						<option value="<?php echo esc_attr( PricingCalculator::STRATEGY_REGULAR ); ?>" <?php selected( $base_strat, PricingCalculator::STRATEGY_REGULAR ); ?>>
							<?php esc_html_e( 'Regular Price (discounts calculated from regular price)', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
						<option value="<?php echo esc_attr( PricingCalculator::STRATEGY_DISABLE_ON_SALE ); ?>" <?php selected( $base_strat, PricingCalculator::STRATEGY_DISABLE_ON_SALE ); ?>>
							<?php esc_html_e( 'Disable Tier Pricing While On Sale (no bulk discounts on active sales)', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Tab: Rules (Category Rules & Global Fallback Rule).
	 *
	 * @return void
	 */
	private function render_tab_rules(): void {
		$global_enabled = 'yes' === get_option( 'wpcttp_global_rule_enabled', 'no' );
		$global_rule    = $this->repository->get_global_rule();
		$global_scope   = $global_rule ? $global_rule->get_calculation_scope() : PricingRule::SCOPE_CART_WIDE;
		$global_tiers   = $global_rule ? $global_rule->get_tiers() : array();

		$all_cats = $this->repository->get_all_category_rules();

		?>
		<!-- Global Fallback Rule Section -->
		<div class="wpcttp-card">
			<h3 style="margin-top: 0; font-size: 1.1rem;"><?php esc_html_e( 'Global Fallback Rule', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Global rules apply to products that do not have a higher-priority product or category rule assigned.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
			</p>

			<p>
				<label>
					<input type="checkbox" name="wpcttp_global_rule_enabled" value="1" <?php checked( $global_enabled, true ); ?> />
					<strong><?php esc_html_e( 'Enable Global Fallback Pricing Rule', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></strong>
				</label>
			</p>

			<p>
				<label>
					<?php esc_html_e( 'Calculation Scope:', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
					<select name="wpcttp_global_rule[calculation_scope]">
						<option value="<?php echo esc_attr( PricingRule::SCOPE_CART_WIDE ); ?>" <?php selected( $global_scope, PricingRule::SCOPE_CART_WIDE ); ?>>
							<?php esc_html_e( 'Cart-Wide Quantity', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
						<option value="<?php echo esc_attr( PricingRule::SCOPE_INDIVIDUAL ); ?>" <?php selected( $global_scope, PricingRule::SCOPE_INDIVIDUAL ); ?>>
							<?php esc_html_e( 'Individual Product Quantity', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</option>
					</select>
				</label>
			</p>

			<div class="wpcttp-tiers-manager">
				<?php $this->product_admin->render_tiers_editor_table( 'wpcttp_global_rule', $global_tiers ); ?>
			</div>
		</div>

		<!-- Category Rules Section -->
		<div class="wpcttp-card" style="margin-top: 1.5rem;">
			<h3 style="margin-top: 0; font-size: 1.1rem;"><?php esc_html_e( 'Category Pricing Rules', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Configure quantity pricing rules for entire product categories. If multiple categories match a product, highest priority wins.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
			</p>

			<?php if ( ! empty( $all_cats ) ) : ?>
				<table class="widefat striped" style="margin-bottom: 1.5rem;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Category', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Scope', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Priority', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Tiers Count', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $all_cats as $term_id => $cat_rule ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $cat_rule->get_name() ); ?></strong> (ID: <?php echo esc_html( (string) $term_id ); ?>)</td>
								<td><?php echo esc_html( $cat_rule->get_calculation_scope() ); ?></td>
								<td><?php echo esc_html( (string) $cat_rule->get_priority() ); ?></td>
								<td><?php echo esc_html( (string) count( $cat_rule->get_tiers() ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<!-- Add/Edit Category Rule Form -->
			<h4 style="margin-bottom: 8px;"><?php esc_html_e( 'Add or Update Category Rule', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></h4>
			<p>
				<label><?php esc_html_e( 'Select Product Category:', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
				<?php
				wp_dropdown_categories(
					array(
						'taxonomy'         => 'product_cat',
						'name'             => 'new_category_rule[term_id]',
						'show_option_none' => __( '— Select Category —', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
						'hierarchical'     => 1,
						'hide_empty'       => 0,
					)
				);
				?>
			</p>
			<p>
				<label>
					<input type="checkbox" name="new_category_rule[enabled]" value="1" checked />
					<?php esc_html_e( 'Enable Rule', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
				</label>
				&nbsp;&nbsp;
				<label>
					<?php esc_html_e( 'Priority (Higher wins):', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
					<input type="number" name="new_category_rule[priority]" value="10" step="1" style="width: 70px;" />
				</label>
				&nbsp;&nbsp;
				<label>
					<?php esc_html_e( 'Calculation Scope:', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
					<select name="new_category_rule[calculation_scope]">
						<option value="<?php echo esc_attr( PricingRule::SCOPE_CATEGORY ); ?>"><?php esc_html_e( 'Category Quantity (Aggregate across category)', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></option>
						<option value="<?php echo esc_attr( PricingRule::SCOPE_INDIVIDUAL ); ?>"><?php esc_html_e( 'Individual Product Line Quantity', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></option>
					</select>
				</label>
			</p>

			<div class="wpcttp-tiers-manager">
				<?php $this->product_admin->render_tiers_editor_table( 'new_category_rule', array() ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Tab: License.
	 *
	 * @return void
	 */
	private function render_tab_license(): void {
		$masked_key = $this->license_service->get_masked_license_key();
		$status     = $this->license_service->get_status_label();

		?>
		<div class="wpcttp-card">
			<h3 style="margin-top: 0; font-size: 1.1rem;"><?php esc_html_e( 'Plugin License Configuration', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></h3>
			<p>
				<?php esc_html_e( 'Enter your license key below. A replaceable licensing architecture is implemented without blocking core store functionality.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'License Status', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></th>
					<td>
						<span class="badge" style="display: inline-block; padding: 4px 10px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; font-weight: 600;">
							<?php echo esc_html( $status ); ?>
						</span>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wpcttp_license_key"><?php esc_html_e( 'License Key', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							class="regular-text"
							name="wpcttp_license_key"
							id="wpcttp_license_key"
							value="<?php echo esc_attr( $masked_key ); ?>"
							placeholder="<?php esc_attr_e( 'Enter your license key', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'Your license key is masked for security.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p>
				<button type="submit" name="wpcttp_license_action" value="activate" class="button button-primary">
					<?php esc_html_e( 'Save License Key', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
				</button>
				<?php if ( ! empty( $masked_key ) ) : ?>
					<button type="submit" name="wpcttp_license_action" value="deactivate" class="button button-secondary">
						<?php esc_html_e( 'Remove License Key', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
					</button>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Tab: Support.
	 *
	 * @return void
	 */
	private function render_tab_support(): void {
		?>
		<div class="wpcttp-card">
			<h3 style="margin-top: 0; font-size: 1.15rem;"><?php esc_html_e( 'Need Assistance with WPCalibrate Tiered Pricing?', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></h3>
			<p>
				<?php esc_html_e( 'Our dedicated engineering support team is ready to help you with configuration, custom integration, and troubleshooting.', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?>
			</p>

			<div class="wpcttp-support-grid">
				<div class="wpcttp-support-card">
					<h4><?php esc_html_e( 'Direct Support Email', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></h4>
					<p><a href="mailto:support@wpcalibrate.com">support@wpcalibrate.com</a></p>
				</div>
				<div class="wpcttp-support-card">
					<h4><?php esc_html_e( 'Phone / WhatsApp', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></h4>
					<p><a href="https://wa.me/447474795976" target="_blank" rel="noopener noreferrer">+447474795976</a></p>
				</div>
				<div class="wpcttp-support-card">
					<h4><?php esc_html_e( 'Official Website', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></h4>
					<p><a href="https://wpcalibrate.com" target="_blank" rel="noopener noreferrer">https://wpcalibrate.com</a></p>
				</div>
				<div class="wpcttp-support-card">
					<h4><?php esc_html_e( 'Plugin Marketplace', 'wpcalibrate-tiered-pricing-for-woocommerce' ); ?></h4>
					<p><a href="https://marketplace.wpcalibrate.com/" target="_blank" rel="noopener noreferrer">marketplace.wpcalibrate.com</a></p>
				</div>
			</div>
		</div>
		<?php
	}
}
