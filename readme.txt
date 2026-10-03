=== WPCalibrate Tiered Pricing for WooCommerce ===
Contributors: wpcalibrate
Tags: woocommerce, tiered pricing, bulk pricing, quantity discount, dynamic pricing
Requires at least: 6.5
Tested up to: 7.1.2
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional, server-authoritative quantity-based tiered bulk pricing for WooCommerce products, variations, categories, and cart-wide rules.

== Description ==

**WPCalibrate Tiered Pricing for WooCommerce** empowers store owners to offer quantity-based bulk discounts with full server-side price authority, real-time frontend table display, and seamless WooCommerce Cart & Checkout Blocks compatibility.

Unlike plugins that rely on fragile client-side scripts to calculate discounts, WPCalibrate Tiered Pricing guarantees that the authoritative price is calculated strictly on the server during cart calculation, order creation, and Store API requests.

### Key Features

* **Server-Authoritative Pricing Engine:** Never trusts client-side prices or hidden form fields.
* **Deterministic Rule Precedence:** Variation Rules > Product Rules > Category Rules > Global Rules.
* **Flexible Calculation Scopes:**
  * *Individual Line Quantity:* Calculates discount based on the exact product line.
  * *Variable Product Combined Quantity:* Aggregates quantities across all variations of the same parent product.
  * *Category Quantity:* Aggregates quantities across all eligible products in a category.
  * *Cart-Wide Quantity:* Aggregates total eligible quantities in the entire cart.
* **Fixed & Percentage Pricing Tiers:** Define custom fixed unit prices (e.g. $40 each for 5-9) or percentage discounts (e.g. 15% off for 10+).
* **Open-Ended Final Tiers:** Easily configure unbounded upper tiers (e.g., 20+).
* **Live Product Table & Highlights:** Automatically renders a semantic, accessible pricing table that dynamically highlights the active tier as shoppers change quantity.
* **Cart & Checkout Blocks Compatibility:** Fully declared and tested with WooCommerce Cart and Checkout Blocks and the Store API.
* **High-Performance Order Storage (HPOS) Ready:** Officially declares HPOS compatibility.
* **Sale-Price Strategies:**
  * Current Effective Price: Applies tier discounts on top of active sale prices.
  * Regular Price: Computes tiers from the regular base price.
  * Disable While On Sale: Skips tier discounts if the product is on sale.
* **Coupon Interaction Controls:** Choose whether WooCommerce coupons can be applied alongside tiered bulk discounts or prevent coupons from discounting tier-priced items at the line-item level.
* **Tax-Aware Display:** Follows store tax configuration (`wc_get_price_to_display`) for inclusive or exclusive prices and suffixes.
* **Clean WPCalibrate Admin Dashboard:** Shared parent menu with sibling plugins, responsive tier repeater, and comprehensive settings.

== Installation ==

1. Upload the `wpcalibrate-tiered-pricing-for-woocommerce` directory to the `/wp-content/plugins/` directory, or install the ZIP file via **Plugins > Add New > Upload Plugin**.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Configure settings under **WPCalibrate > Tiered Pricing**.
4. Configure tiered rules directly in the **Product Data > Tiered Pricing** tab on any product edit screen, or centrally in the settings Rules tab.

== Frequently Asked Questions ==

= Does this plugin work with variable products? =
Yes. You can define parent-level tiered pricing that all variations inherit, or override rules on specific variations. You can also configure the *Variable Product Combined Quantity* scope so purchasing different variations of the same product contributes toward the bulk discount tier.

= Does this plugin support WooCommerce Cart and Checkout Blocks? =
Yes. WPCalibrate Tiered Pricing declares full compatibility with `cart_checkout_blocks` and integrates with Store API cart calculation passes.

= Is it compatible with High-Performance Order Storage (HPOS)? =
Yes. Compatibility with `custom_order_tables` is declared and strictly enforced without direct queries to legacy post tables.

= Can I use coupons with tiered pricing? =
Yes. Under **WPCalibrate > Tiered Pricing > Coupons & Sales**, you can choose whether to allow coupons on tier-priced items or exclude them at the individual line-item level.

= Will uninstalling the plugin delete my product rules? =
By default, all product rules and settings are preserved. An explicit opt-in setting ("Delete Data on Uninstall") is available if you wish to purge plugin data upon removal.

== Changelog ==

= 1.0.0 =
* Initial commercial release.
* Server-authoritative quantity-based pricing engine.
* Support for individual, combined variation, category, and cart-wide scopes.
* Fixed unit price and percentage discount tiers.
* Live frontend pricing table and active tier highlighter.
* WooCommerce Cart and Checkout Blocks compatibility.
* High-Performance Order Storage (HPOS) compatibility.
* Replaceable licensing abstraction.
