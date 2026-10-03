<?php
/**
 * Plugin Name:       WPCalibrate Tiered Pricing for WooCommerce
 * Plugin URI:        https://marketplace.wpcalibrate.com/
 * Description:       Server-authoritative quantity-based tiered pricing rules for WooCommerce products, variations, categories, and global cart items with live frontend updates and block compatibility.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Tested up to:      7.1.2
 * Requires PHP:      8.2
 * Author:            WPCalibrate
 * Author URI:        https://wpcalibrate.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpcalibrate-tiered-pricing-for-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 8.5
 * WC tested up to:   11.1.2
 *
 * @package WPCalibrate\TieredPricing
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'WPCALIBRATE_TIERED_PRICING_VERSION', '1.0.0' );
define( 'WPCALIBRATE_TIERED_PRICING_FILE', __FILE__ );
define( 'WPCALIBRATE_TIERED_PRICING_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPCALIBRATE_TIERED_PRICING_URL', plugin_dir_url( __FILE__ ) );
define( 'WPCALIBRATE_TIERED_PRICING_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPCALIBRATE_TIERED_PRICING_MIN_PHP', '8.2' );
define( 'WPCALIBRATE_TIERED_PRICING_MIN_WC', '8.5' );

/**
 * Declare HPOS and Cart/Checkout Blocks compatibility before WooCommerce initializes.
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

// Register PSR-4 autoloader.
require_once WPCALIBRATE_TIERED_PRICING_PATH . 'src/Autoloader.php';
\WPCalibrate\TieredPricing\Autoloader::register();

/**
 * Register activation and deactivation hooks.
 */
register_activation_hook( __FILE__, array( \WPCalibrate\TieredPricing\Activation\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \WPCalibrate\TieredPricing\Activation\Deactivator::class, 'deactivate' ) );

/**
 * Check PHP version compatibility.
 *
 * @return bool
 */
function wpcttp_check_php_version(): bool {
	if ( version_compare( PHP_VERSION, WPCALIBRATE_TIERED_PRICING_MIN_PHP, '<' ) ) {
		add_action(
			'admin_notices',
			static function (): void {
				printf(
					'<div class="notice notice-error"><p><strong>%1$s:</strong> %2$s</p></div>',
					esc_html__( 'WPCalibrate Tiered Pricing for WooCommerce', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
					sprintf(
						/* translators: 1: Current PHP version, 2: Required PHP version */
						esc_html__( 'Your site is running PHP version %1$s, but this plugin requires PHP %2$s or higher. Please upgrade your PHP version.', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
						esc_html( PHP_VERSION ),
						esc_html( WPCALIBRATE_TIERED_PRICING_MIN_PHP )
					)
				);
			}
		);
		return false;
	}
	return true;
}

/**
 * Check WooCommerce dependency.
 *
 * @return bool
 */
function wpcttp_check_woocommerce(): bool {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			static function (): void {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				printf(
					'<div class="notice notice-warning is-dismissible"><p><strong>%1$s:</strong> %2$s</p></div>',
					esc_html__( 'WPCalibrate Tiered Pricing for WooCommerce', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
					esc_html__( 'WooCommerce is required for this plugin to function. Please install and activate WooCommerce.', 'wpcalibrate-tiered-pricing-for-woocommerce' )
				);
			}
		);
		return false;
	}
	return true;
}

/**
 * Bootstrap the plugin.
 *
 * @return void
 */
function wpcttp_bootstrap(): void {
	if ( ! wpcttp_check_php_version() ) {
		return;
	}

	if ( ! wpcttp_check_woocommerce() ) {
		return;
	}

	\WPCalibrate\TieredPricing\Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'wpcttp_bootstrap' );

/**
 * Get plugin singleton instance.
 *
 * @return \WPCalibrate\TieredPricing\Plugin
 */
function wpcttp(): \WPCalibrate\TieredPricing\Plugin {
	return \WPCalibrate\TieredPricing\Plugin::instance();
}
