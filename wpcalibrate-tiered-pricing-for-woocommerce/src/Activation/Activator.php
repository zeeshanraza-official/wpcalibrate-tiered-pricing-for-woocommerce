<?php
/**
 * Plugin Activator.
 *
 * @package WPCalibrate\TieredPricing\Activation
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Activation;

use WPCalibrate\TieredPricing\Data\MigrationManager;

/**
 * Class Activator
 */
final class Activator {

	/**
	 * Default plugin options.
	 *
	 * @var array<string, mixed>
	 */
	public const DEFAULTS = array(
		'wpcttp_version'                  => '1.0.0',
		'wpcttp_schema_version'           => '1.0.0',
		'wpcttp_enabled'                  => 'yes',
		'wpcttp_base_price_strategy'      => 'current_effective',
		'wpcttp_coupon_strategy'          => 'allow',
		'wpcttp_default_scope'            => 'individual',
		'wpcttp_table_enabled'            => 'yes',
		'wpcttp_table_position'           => 'woocommerce_before_add_to_cart_form',
		'wpcttp_table_title'              => 'Tiered Bulk Pricing',
		'wpcttp_col_qty_label'            => 'Quantity',
		'wpcttp_col_price_label'          => 'Price per Unit',
		'wpcttp_col_savings_label'        => 'Savings',
		'wpcttp_show_savings'             => 'yes',
		'wpcttp_highlight_active'         => 'yes',
		'wpcttp_show_line_total'          => 'no',
		'wpcttp_global_rule_enabled'      => 'no',
		'wpcttp_global_rule'              => array(),
		'wpcttp_category_rules'           => array(),
		'wpcttp_delete_data_on_uninstall' => 'no',
		'wpcttp_debug_logging'            => 'no',
	);

	/**
	 * Run activation routine.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Initialize default options without overwriting existing settings.
		foreach ( self::DEFAULTS as $key => $default_value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $default_value, '', 'no' );
			}
		}

		// Run schema migrations safely.
		MigrationManager::run_migrations();
	}
}
