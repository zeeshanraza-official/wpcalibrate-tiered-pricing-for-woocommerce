<?php
/**
 * Standalone Test Runner.
 *
 * Runs test suites directly without requiring external PHPUnit runner.
 *
 * @package WPCalibrate\TieredPricing\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use WPCalibrate\TieredPricing\Cart\CartPricing;
use WPCalibrate\TieredPricing\Cart\CouponHandler;
use WPCalibrate\TieredPricing\Contracts\RuleRepositoryInterface;
use WPCalibrate\TieredPricing\Data\PricingResult;
use WPCalibrate\TieredPricing\Data\PricingRule;
use WPCalibrate\TieredPricing\Data\Tier;
use WPCalibrate\TieredPricing\Licensing\UnconfiguredLicenseService;
use WPCalibrate\TieredPricing\Pricing\PricingCalculator;
use WPCalibrate\TieredPricing\Pricing\PricingEngine;
use WPCalibrate\TieredPricing\Rules\QuantityResolver;
use WPCalibrate\TieredPricing\Rules\RuleRepository;
use WPCalibrate\TieredPricing\Rules\RuleResolver;
use WPCalibrate\TieredPricing\Rules\RuleValidator;

$passed = 0;
$failed = 0;

function assert_test( bool $condition, string $message ): void {
	global $passed, $failed;
	if ( $condition ) {
		echo "[PASS] {$message}\n";
		$passed++;
	} else {
		echo "[FAIL] {$message}\n";
		$failed++;
	}
}

echo "=================================================================\n";
echo "  WPCalibrate Tiered Pricing for WooCommerce - Test Suite\n";
echo "=================================================================\n\n";

// --- 1. Tier instantiation & matching ---
$t1 = new Tier( 5.0, 9.0, Tier::TYPE_FIXED, 40.0, true );
assert_test( $t1->get_min_qty() === 5.0, 'Tier min qty is 5' );
assert_test( $t1->get_max_qty() === 9.0, 'Tier max qty is 9' );
assert_test( ! $t1->is_open_ended(), 'Tier 5-9 is bounded' );
assert_test( ! $t1->matches( 1 ), 'Tier 5-9 does not match qty 1' );
assert_test( ! $t1->matches( 4 ), 'Tier 5-9 does not match qty 4' );
assert_test( $t1->matches( 5 ), 'Tier 5-9 matches lower boundary qty 5' );
assert_test( $t1->matches( 7 ), 'Tier 5-9 matches middle qty 7' );
assert_test( $t1->matches( 9 ), 'Tier 5-9 matches upper boundary qty 9' );
assert_test( ! $t1->matches( 10 ), 'Tier 5-9 does not match qty 10' );

// --- 2. Open-ended tier ---
$t2 = new Tier( 10.0, null, Tier::TYPE_FIXED, 30.0, true );
assert_test( $t2->is_open_ended(), 'Tier 10+ is open ended' );
assert_test( ! $t2->matches( 9 ), 'Tier 10+ does not match qty 9' );
assert_test( $t2->matches( 10 ), 'Tier 10+ matches boundary qty 10' );
assert_test( $t2->matches( 100 ), 'Tier 10+ matches qty 100' );
assert_test( $t2->matches( 50000 ), 'Tier 10+ matches large qty 50000' );

// --- 3. Rule Normalizer & Ordering ---
$raw_tiers = array(
	array( 'min_qty' => 10, 'max_qty' => null, 'type' => 'percentage', 'value' => 20 ),
	array( 'min_qty' => 2, 'max_qty' => 4, 'type' => 'percentage', 'value' => 5 ),
	array( 'min_qty' => 5, 'max_qty' => 9, 'type' => 'percentage', 'value' => 10 ),
);
$normalized = RuleValidator::normalize_tiers( $raw_tiers );
assert_test( count( $normalized ) === 3, 'Normalizer sorted 3 unordered tiers' );
assert_test( $normalized[0]->get_min_qty() === 2.0 && $normalized[0]->get_max_qty() === 4.0, 'Tier 1 sorted to range 2-4' );
assert_test( $normalized[1]->get_min_qty() === 5.0 && $normalized[1]->get_max_qty() === 9.0, 'Tier 2 sorted to range 5-9' );
assert_test( $normalized[2]->get_min_qty() === 10.0 && $normalized[2]->is_open_ended(), 'Tier 3 sorted to open-ended 10+' );

// --- 4. Overlap Resolution ---
$overlap_raw = array(
	array( 'min_qty' => 2, 'max_qty' => 10, 'type' => 'fixed', 'value' => 45 ),
	array( 'min_qty' => 6, 'max_qty' => null, 'type' => 'fixed', 'value' => 35 ),
);
$norm_overlap = RuleValidator::normalize_tiers( $overlap_raw );
assert_test( count( $norm_overlap ) === 2, 'Overlap resolved into 2 tiers' );
assert_test( $norm_overlap[0]->get_max_qty() === 5.0, 'Overlap clamped tier 1 max to 5' );
assert_test( $norm_overlap[1]->get_min_qty() === 6.0, 'Tier 2 begins at 6' );

// --- 5. Boundaries with Rule Object ---
$boundary_rule = new PricingRule( 'b1', PricingRule::SOURCE_PRODUCT, 10, 'B-Rule', true, PricingRule::SCOPE_INDIVIDUAL, 10, array( $t1, $t2 ) );
assert_test( null === $boundary_rule->find_matching_tier( 1 ), 'Boundary Rule: Qty 1 has no match' );
assert_test( null === $boundary_rule->find_matching_tier( 4 ), 'Boundary Rule: Qty 4 has no match' );
assert_test( $boundary_rule->find_matching_tier( 5 )->get_value() === 40.0, 'Boundary Rule: Qty 5 matches Tier A ($40)' );
assert_test( $boundary_rule->find_matching_tier( 9 )->get_value() === 40.0, 'Boundary Rule: Qty 9 matches Tier A ($40)' );
assert_test( $boundary_rule->find_matching_tier( 10 )->get_value() === 30.0, 'Boundary Rule: Qty 10 matches Tier B ($30)' );
assert_test( $boundary_rule->find_matching_tier( 9999 )->get_value() === 30.0, 'Boundary Rule: Qty 9999 matches Tier B ($30)' );

// --- 6. Pricing Calculator ---
$dummy_prod = new WC_Product();
$calc_fixed = PricingCalculator::calculate_tier_price( 100.0, $t1, $dummy_prod, 5 );
assert_test( $calc_fixed['is_applied'] === true, 'Fixed calculation applied' );
assert_test( $calc_fixed['adjusted_unit_price'] === 40.0, 'Fixed unit price calculated correctly as 40.0' );
assert_test( $calc_fixed['discount_amount'] === 60.0, 'Fixed discount amount calculated as 60.0' );

$pct_tier = new Tier( 5.0, null, Tier::TYPE_PERCENTAGE, 15.0, true );
$calc_pct = PricingCalculator::calculate_tier_price( 100.0, $pct_tier, $dummy_prod, 5 );
assert_test( $calc_pct['is_applied'] === true, 'Percentage calculation applied' );
assert_test( $calc_pct['adjusted_unit_price'] === 85.0, 'Percentage unit price calculated correctly as 85.0' );
assert_test( $calc_pct['discount_amount'] === 15.0, 'Percentage discount amount calculated as 15.0' );

// --- 7. Quantity Resolver Scopes ---
$q_resolver = new QuantityResolver();
$rule_indiv = new PricingRule( 'r1', PricingRule::SOURCE_PRODUCT, 1, 'R1', true, PricingRule::SCOPE_INDIVIDUAL, 10, array() );
assert_test( $q_resolver->resolve_quantity( $dummy_prod, $rule_indiv, 7 ) === 7.0, 'Individual scope returns line quantity 7' );

$var_a = new class extends WC_Product_Variation {
	public function get_parent_id(): int { return 500; }
};
$var_b = new class extends WC_Product_Variation {
	public function get_parent_id(): int { return 500; }
};
$var_c = new class extends WC_Product_Variation {
	public function get_parent_id(): int { return 999; }
};

$cart_items = array(
	'item_a' => array( 'data' => $var_a, 'quantity' => 4 ),
	'item_b' => array( 'data' => $var_b, 'quantity' => 6 ),
	'item_c' => array( 'data' => $var_c, 'quantity' => 10 ),
);
$rule_comb = new PricingRule( 'r2', PricingRule::SOURCE_PRODUCT, 500, 'R2', true, PricingRule::SCOPE_VARIABLE_COMBINED, 10, array() );
$combined_qty = $q_resolver->resolve_quantity( $var_a, $rule_comb, 4, $cart_items );
assert_test( $combined_qty === 10.0, 'Variable combined scope aggregated 4 + 6 = 10, excluding parent 999' );

$rule_cart = new PricingRule( 'r3', PricingRule::SOURCE_GLOBAL, 0, 'R3', true, PricingRule::SCOPE_CART_WIDE, 10, array() );
$cart_wide_qty = $q_resolver->resolve_quantity( $var_a, $rule_cart, 4, $cart_items );
assert_test( $cart_wide_qty === 20.0, 'Cart-wide scope aggregated all lines: 4 + 6 + 10 = 20' );

// --- 8. Rule Precedence ---
$mock_repo = new class implements RuleRepositoryInterface {
	public function get_for_product( int $id ): ?PricingRule {
		return new PricingRule( 'parent_rule', PricingRule::SOURCE_PRODUCT, $id, 'Parent', true, PricingRule::SCOPE_INDIVIDUAL, 10, array() );
	}
	public function get_for_variation( int $id ): ?PricingRule {
		if ( 101 === $id ) {
			return new PricingRule( 'var_override_rule', PricingRule::SOURCE_VARIATION, $id, 'Var', true, PricingRule::SCOPE_INDIVIDUAL, 10, array(), true );
		}
		return null;
	}
	public function get_for_category( int $id ): ?PricingRule { return null; }
	public function get_all_category_rules(): array { return array(); }
	public function get_global_rule(): ?PricingRule { return null; }
	public function save_for_product( int $id, PricingRule $r ): bool { return true; }
	public function save_for_variation( int $id, PricingRule $r ): bool { return true; }
	public function save_for_category( int $id, PricingRule $r ): bool { return true; }
	public function save_global_rule( PricingRule $r ): bool { return true; }
	public function delete_for_product( int $id ): bool { return true; }
	public function delete_for_variation( int $id ): bool { return true; }
};

$resolver = new RuleResolver( $mock_repo );
$variation_overriding = new class extends WC_Product_Variation {
	public function get_id(): int { return 101; }
	public function get_parent_id(): int { return 50; }
};
$variation_inheriting = new class extends WC_Product_Variation {
	public function get_id(): int { return 102; }
	public function get_parent_id(): int { return 50; }
};

$win1 = $resolver->resolve( $variation_overriding );
assert_test( $win1->get_id() === 'var_override_rule', 'Variation rule takes precedence over parent rule' );

$win2 = $resolver->resolve( $variation_inheriting );
assert_test( $win2->get_id() === 'parent_rule', 'Variation inherits parent rule when not overriding' );

// --- 9. Cart Pricing Anti-Compounding Protection ---
$cart_prod = new class extends WC_Product {
	private float $price = 100.0;
	public function get_price( string $context = 'view' ): string { return (string) $this->price; }
	public function set_price( $p ): void { $this->price = (float) $p; }
};
$engine = new PricingEngine( $resolver, $q_resolver );
$cart_pricing = new CartPricing( $engine );

$cart_mock = new class {
	public array $items = array();
	public function get_cart(): array { return $this->items; }
};
$cart_mock->items = array(
	'item_1' => array( 'data' => $cart_prod, 'quantity' => 1 ),
);

$cart_pricing->calculate_cart_totals( $cart_mock );
assert_test( (float) $cart_prod->get_price() === 100.0, 'First cart calculation pass correctly evaluated' );
$cart_pricing->calculate_cart_totals( $cart_mock );
assert_test( (float) $cart_prod->get_price() === 100.0, 'Second cart calculation pass did not compound or alter base price' );

// --- 10. Coupon Exclusion Strategy ---
$coupon_handler = new CouponHandler();
update_option( 'wpcttp_coupon_strategy', CouponHandler::STRATEGY_PREVENT );
$dummy_coupon = new class {};
$tiered_cart_item = array( '_wpcttp_tiered_price_applied' => true );
$normal_cart_item = array( '_wpcttp_tiered_price_applied' => false );

assert_test(
	! $coupon_handler->filter_coupon_valid_for_product( true, $dummy_prod, $dummy_coupon, $tiered_cart_item ),
	'Coupon prevented on tier-priced line item'
);
assert_test(
	$coupon_handler->filter_coupon_valid_for_product( true, $dummy_prod, $dummy_coupon, $normal_cart_item ),
	'Coupon remains allowed on standard non-tiered line item'
);

// --- 11. Replaceable License Service ---
$license_service = new UnconfiguredLicenseService();
assert_test( $license_service->get_status() === 'not_configured', 'License status is unconfigured' );
assert_test( $license_service->is_valid() === true, 'Unconfigured license does not block store functionality' );
assert_test( $license_service->get_expiry() === null, 'No fake expiry date is generated' );
$act_res = $license_service->activate( 'TEST-KEY-XYZ' );
assert_test( $act_res['success'] === false, 'Activation does not fake success' );

// --- 12. Compatibility Declarations ---
$main_content = file_get_contents( dirname( __DIR__ ) . '/wpcalibrate-tiered-pricing-for-woocommerce.php' );
assert_test( str_contains( $main_content, 'custom_order_tables' ), 'HPOS (custom_order_tables) declared in main plugin' );
assert_test( str_contains( $main_content, 'cart_checkout_blocks' ), 'Cart/Checkout Blocks declared in main plugin' );

echo "\n=================================================================\n";
echo "Final Results: {$passed} Passed, {$failed} Failed\n";
echo "=================================================================\n";

exit( $failed > 0 ? 1 : 0 );
