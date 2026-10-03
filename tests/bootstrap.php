<?php
/**
 * PHPUnit Test Bootstrap.
 *
 * @package WPCalibrate\TieredPricing\Tests
 */

declare(strict_types=1);

// Define ABSPATH if not defined.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! defined( 'WPCALIBRATE_TIERED_PRICING_VERSION' ) ) {
	define( 'WPCALIBRATE_TIERED_PRICING_VERSION', '1.0.0' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

// In-memory option & meta store for testing.
$GLOBALS['_wp_mock_options']   = array();
$GLOBALS['_wp_mock_post_meta'] = array();
$GLOBALS['_wp_mock_term_meta'] = array();
$GLOBALS['_wp_mock_transients']= array();

// Stub translation functions.
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

// Stub hooks.
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
		return $value;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $hook, mixed ...$args ): void {}
}

// Stub options.
if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, mixed $default = false ): mixed {
		return $GLOBALS['_wp_mock_options'][ $option ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, mixed $value, mixed $autoload = null ): bool {
		$GLOBALS['_wp_mock_options'][ $option ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $option ): bool {
		unset( $GLOBALS['_wp_mock_options'][ $option ] );
		return true;
	}
}

// Stub post meta.
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( int $post_id, string $key = '', bool $single = false ): mixed {
		$val = $GLOBALS['_wp_mock_post_meta'][ $post_id ][ $key ] ?? null;
		return $single ? $val : ( null !== $val ? array( $val ) : array() );
	}
}
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( int $post_id, string $key, mixed $value ): bool {
		$GLOBALS['_wp_mock_post_meta'][ $post_id ][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( int $post_id, string $key ): bool {
		unset( $GLOBALS['_wp_mock_post_meta'][ $post_id ][ $key ] );
		return true;
	}
}

// Stub term meta.
if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( int $term_id, string $key = '', bool $single = false ): mixed {
		$val = $GLOBALS['_wp_mock_term_meta'][ $term_id ][ $key ] ?? null;
		return $single ? $val : ( null !== $val ? array( $val ) : array() );
	}
}
if ( ! function_exists( 'update_term_meta' ) ) {
	function update_term_meta( int $term_id, string $key, mixed $value ): bool {
		$GLOBALS['_wp_mock_term_meta'][ $term_id ][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_term_meta' ) ) {
	function delete_term_meta( int $term_id, string $key ): bool {
		unset( $GLOBALS['_wp_mock_term_meta'][ $term_id ][ $key ] );
		return true;
	}
}

// Stub transients.
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $transient ): mixed {
		return $GLOBALS['_wp_mock_transients'][ $transient ] ?? false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $transient, mixed $value, int $expiration = 0 ): bool {
		$GLOBALS['_wp_mock_transients'][ $transient ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $transient ): bool {
		unset( $GLOBALS['_wp_mock_transients'][ $transient ] );
		return true;
	}
}

// Stub WooCommerce functions.
if ( ! function_exists( 'wc_get_price_decimals' ) ) {
	function wc_get_price_decimals(): int {
		return 2;
	}
}
if ( ! function_exists( 'wc_format_decimal' ) ) {
	function wc_format_decimal( mixed $number, mixed $dp = false, bool $trim_zeros = false ): string {
		return sprintf( '%.2f', (float) $number );
	}
}
if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( mixed $the_product = false ): ?WC_Product {
		return null;
	}
}

// Stub WC classes if not present.
if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
		public function get_id(): int { return 1; }
		public function get_parent_id(): int { return 0; }
		public function is_type( string $type ): bool { return 'simple' === $type; }
		public function get_price( string $context = 'view' ): string { return '100'; }
		public function get_regular_price( string $context = 'view' ): string { return '100'; }
		public function get_sale_price( string $context = 'view' ): string { return ''; }
		public function is_on_sale( string $context = 'view' ): bool { return false; }
		public function get_category_ids(): array { return array(); }
	}
}

if ( ! class_exists( 'WC_Product_Variation' ) ) {
	class WC_Product_Variation extends WC_Product {
		public function is_type( string $type ): bool { return 'variation' === $type; }
	}
}

// Register Autoloader.
require_once __DIR__ . '/../src/Autoloader.php';
\WPCalibrate\TieredPricing\Autoloader::register();
