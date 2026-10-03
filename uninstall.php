<?php
/**
 * Uninstall WPCalibrate Tiered Pricing for WooCommerce.
 *
 * Deletes all plugin options and metadata only if the merchant has explicitly enabled
 * the 'Delete plugin data on uninstall' setting.
 *
 * @package WPCalibrate\TieredPricing
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Preserve merchant configuration by default unless explicitly configured.
$delete_data = get_option( 'wpcttp_delete_data_on_uninstall', 'no' );
if ( 'yes' !== $delete_data ) {
	return;
}

global $wpdb;

// 1. Delete plugin-owned options.
$options = array(
	'wpcttp_version',
	'wpcttp_schema_version',
	'wpcttp_enabled',
	'wpcttp_base_price_strategy',
	'wpcttp_coupon_strategy',
	'wpcttp_default_scope',
	'wpcttp_table_enabled',
	'wpcttp_table_position',
	'wpcttp_table_title',
	'wpcttp_col_qty_label',
	'wpcttp_col_price_label',
	'wpcttp_col_savings_label',
	'wpcttp_show_savings',
	'wpcttp_highlight_active',
	'wpcttp_show_line_total',
	'wpcttp_global_rule_enabled',
	'wpcttp_global_rule',
	'wpcttp_category_rules',
	'wpcttp_license_key',
	'wpcttp_delete_data_on_uninstall',
	'wpcttp_debug_logging',
);

foreach ( $options as $option_name ) {
	delete_option( $option_name );
}

// 2. Delete plugin-owned transients.
delete_transient( 'wpcttp_global_rule_cache' );
delete_transient( 'wpcttp_category_rules_cache' );

// 3. Delete plugin-owned post metadata (products and variations).
// Only targeted keys belonging strictly to this plugin are deleted.
$wpdb->delete(
	$wpdb->postmeta,
	array( 'meta_key' => '_wpcttp_pricing_rule' ),
	array( '%s' )
);

$wpdb->delete(
	$wpdb->postmeta,
	array( 'meta_key' => '_wpcttp_original_price' ),
	array( '%s' )
);

// 4. Delete plugin-owned term metadata (product categories).
$wpdb->delete(
	$wpdb->termmeta,
	array( 'meta_key' => '_wpcttp_category_rule' ),
	array( '%s' )
);

// Clear relevant object caches.
wp_cache_flush();
