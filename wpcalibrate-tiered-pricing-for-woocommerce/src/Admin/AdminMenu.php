<?php
/**
 * Shared WPCalibrate Admin Menu Handler.
 *
 * @package WPCalibrate\TieredPricing\Admin
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Admin;

/**
 * Class AdminMenu
 */
final class AdminMenu {

	/**
	 * Top-level parent menu slug.
	 */
	public const PARENT_SLUG = 'wpcalibrate';

	/**
	 * Submenu page slug.
	 */
	public const SUBMENU_SLUG = 'wpcalibrate-tiered-pricing';

	/**
	 * Required capability.
	 */
	public const CAPABILITY = 'manage_woocommerce';

	/**
	 * Constructor.
	 *
	 * @param SettingsPage $settings_page Settings page controller.
	 */
	public function __construct(
		private readonly SettingsPage $settings_page
	) {}

	/**
	 * Initialize admin menu hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 9 );
		add_action( 'admin_head', array( $this, 'inject_menu_icon_styles' ) );
	}

	/**
	 * Register or reuse top-level WPCalibrate menu and attach Tiered Pricing submenu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		global $menu;

		$capability = self::CAPABILITY;
		if ( ! current_user_can( $capability ) ) {
			$capability = 'manage_options';
		}

		// Check if any sibling WPCalibrate plugin already registered the top-level parent menu.
		$parent_exists = false;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && self::PARENT_SLUG === $item[2] ) {
					$parent_exists = true;
					break;
				}
			}
		}

		if ( ! $parent_exists ) {
			add_menu_page(
				__( 'WPCalibrate', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
				__( 'WPCalibrate', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
				$capability,
				self::PARENT_SLUG,
				array( $this->settings_page, 'render' ),
				WPCALIBRATE_TIERED_PRICING_URL . 'assets/branding/icon-white.png',
				56
			);

			// Replace the default parent submenu item title.
			add_submenu_page(
				self::PARENT_SLUG,
				__( 'Tiered Pricing', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
				__( 'Tiered Pricing', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
				$capability,
				self::PARENT_SLUG,
				array( $this->settings_page, 'render' )
			);

			// Also register the canonical submenu slug so requests to 'wpcalibrate-tiered-pricing'
			// are recognized by WordPress and never throw 'Sorry, you are not allowed to access this page.'
			add_submenu_page(
				null,
				__( 'Tiered Pricing', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
				__( 'Tiered Pricing', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
				$capability,
				self::SUBMENU_SLUG,
				array( $this->settings_page, 'render' )
			);
		} else {
			// Attach submenu to existing shared parent.
			add_submenu_page(
				self::PARENT_SLUG,
				__( 'Tiered Pricing - WPCalibrate', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
				__( 'Tiered Pricing', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
				$capability,
				self::SUBMENU_SLUG,
				array( $this->settings_page, 'render' )
			);
		}
	}

	/**
	 * Inject menu icon CSS for proper retina sizing.
	 *
	 * @return void
	 */
	public function inject_menu_icon_styles(): void {
		?>
		<style>
			#adminmenu .toplevel_page_wpcalibrate .wp-menu-image img {
				max-width: 20px;
				max-height: 20px;
				padding-top: 7px;
				opacity: 0.9;
			}
			#adminmenu .toplevel_page_wpcalibrate:hover .wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate.wp-has-current-submenu .wp-menu-image img {
				opacity: 1;
			}
		</style>
		<?php
	}
}
