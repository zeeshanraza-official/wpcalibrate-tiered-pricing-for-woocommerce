<?php
/**
 * Main Plugin Orchestration Container.
 *
 * @package WPCalibrate\TieredPricing
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing;

use WPCalibrate\TieredPricing\Admin\AdminMenu;
use WPCalibrate\TieredPricing\Admin\CategoryRuleAdmin;
use WPCalibrate\TieredPricing\Admin\ProductAdmin;
use WPCalibrate\TieredPricing\Admin\SettingsPage;
use WPCalibrate\TieredPricing\Cart\CartPricing;
use WPCalibrate\TieredPricing\Cart\CouponHandler;
use WPCalibrate\TieredPricing\Compatibility\BlocksStoreApi;
use WPCalibrate\TieredPricing\Contracts\LicenseServiceInterface;
use WPCalibrate\TieredPricing\Contracts\PricingEngineInterface;
use WPCalibrate\TieredPricing\Contracts\QuantityResolverInterface;
use WPCalibrate\TieredPricing\Contracts\RuleRepositoryInterface;
use WPCalibrate\TieredPricing\Contracts\RuleResolverInterface;
use WPCalibrate\TieredPricing\Frontend\LivePricing;
use WPCalibrate\TieredPricing\Frontend\TierTableRenderer;
use WPCalibrate\TieredPricing\Licensing\UnconfiguredLicenseService;
use WPCalibrate\TieredPricing\Pricing\PricingEngine;
use WPCalibrate\TieredPricing\REST\PricingController;
use WPCalibrate\TieredPricing\Rules\QuantityResolver;
use WPCalibrate\TieredPricing\Rules\RuleRepository;
use WPCalibrate\TieredPricing\Rules\RuleResolver;
use WPCalibrate\TieredPricing\Updater\GitHubUpdater;

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var ?self
	 */
	private static ?self $instance = null;

	/**
	 * Service instances.
	 */
	private RuleRepositoryInterface $rule_repository;
	private RuleResolverInterface $rule_resolver;
	private QuantityResolverInterface $quantity_resolver;
	private PricingEngineInterface $pricing_engine;
	private LicenseServiceInterface $license_service;
	private CategoryRuleAdmin $category_admin;
	private ProductAdmin $product_admin;
	private SettingsPage $settings_page;
	private AdminMenu $admin_menu;
	private TierTableRenderer $table_renderer;
	private LivePricing $live_pricing;
	private PricingController $rest_controller;
	private CartPricing $cart_pricing;
	private CouponHandler $coupon_handler;
	private BlocksStoreApi $blocks_api;
	private GitHubUpdater $updater;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$this->rule_repository   = new RuleRepository();
		$this->rule_resolver     = new RuleResolver( $this->rule_repository );
		$this->quantity_resolver = new QuantityResolver();
		$this->pricing_engine    = new PricingEngine( $this->rule_resolver, $this->quantity_resolver );
		$this->license_service   = new UnconfiguredLicenseService();

		$this->category_admin    = new CategoryRuleAdmin( $this->rule_repository );
		$this->product_admin     = new ProductAdmin( $this->rule_repository );
		$this->settings_page     = new SettingsPage( $this->rule_repository, $this->license_service, $this->category_admin, $this->product_admin );
		$this->admin_menu        = new AdminMenu( $this->settings_page );

		$this->table_renderer    = new TierTableRenderer( $this->rule_resolver );
		$this->live_pricing      = new LivePricing();
		$this->rest_controller   = new PricingController( $this->pricing_engine );
		$this->cart_pricing      = new CartPricing( $this->pricing_engine );
		$this->coupon_handler    = new CouponHandler();
		$this->blocks_api        = new BlocksStoreApi();
		$this->updater           = new GitHubUpdater(
			'zeeshanraza-official',
			'wpcalibrate-tiered-pricing-for-woocommerce',
			WPCALIBRATE_TIERED_PRICING_FILE,
			WPCALIBRATE_TIERED_PRICING_BASENAME,
			WPCALIBRATE_TIERED_PRICING_VERSION
		);
	}

	/**
	 * Initialize all plugin services and hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Localization.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Core pricing & Cart hooks.
		$this->cart_pricing->init();
		$this->coupon_handler->init();
		$this->blocks_api->init();

		// Frontend product display & live price hooks.
		$this->table_renderer->init();
		$this->live_pricing->init();
		$this->rest_controller->init();

		// Admin hooks.
		if ( is_admin() ) {
			$this->admin_menu->init();
			$this->product_admin->init();
			$this->settings_page->init();
			$this->updater->init();
		}
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wpcalibrate-tiered-pricing-for-woocommerce',
			false,
			dirname( WPCALIBRATE_TIERED_PRICING_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Get pricing engine service.
	 *
	 * @return PricingEngineInterface
	 */
	public function get_pricing_engine(): PricingEngineInterface {
		return $this->pricing_engine;
	}

	/**
	 * Get rule repository service.
	 *
	 * @return RuleRepositoryInterface
	 */
	public function get_rule_repository(): RuleRepositoryInterface {
		return $this->rule_repository;
	}

	/**
	 * Get rule resolver service.
	 *
	 * @return RuleResolverInterface
	 */
	public function get_rule_resolver(): RuleResolverInterface {
		return $this->rule_resolver;
	}

	/**
	 * Get quantity resolver service.
	 *
	 * @return QuantityResolverInterface
	 */
	public function get_quantity_resolver(): QuantityResolverInterface {
		return $this->quantity_resolver;
	}

	/**
	 * Get license service.
	 *
	 * @return LicenseServiceInterface
	 */
	public function get_license_service(): LicenseServiceInterface {
		return $this->license_service;
	}
}
