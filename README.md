# Tiered Pricing for WooCommerce by WPCalibrate

[![WordPress Tested](https://img.shields.io/badge/WordPress-6.5%20to%207.1.2-blue.svg)](https://wordpress.org/)
[![WooCommerce Tested](https://img.shields.io/badge/WooCommerce-8.5%20to%2011.1.2-purple.svg)](https://woocommerce.com/)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-8892BF.svg)](https://php.net/)
[![HPOS Ready](https://img.shields.io/badge/HPOS-Compatible-success.svg)](https://woocommerce.com/)
[![Cart/Checkout Blocks](https://img.shields.io/badge/Blocks-Compatible-success.svg)](https://woocommerce.com/)
[![License](https://img.shields.io/badge/License-GPLv2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Enterprise-grade, server-authoritative quantity-based tiered bulk pricing for WooCommerce products, variations, categories, and cart-wide items. Features responsive frontend pricing tables, live reactive DOM price updates, High-Performance Order Storage (HPOS) support, Cart & Checkout Blocks compatibility, and automatic WordPress dashboard updates directly from GitHub.

---

## Table of Contents

- [About](#about)
- [Features](#features)
- [Documentation](#documentation)
  - [System Requirements](#system-requirements)
  - [Installation](#installation)
  - [Automatic Updates from GitHub](#automatic-updates-from-github)
  - [Configuration & Rule Setup](#configuration--rule-setup)
  - [Deterministic Precedence Hierarchy](#deterministic-precedence-hierarchy)
  - [Developer Hooks & Filters](#developer-hooks--filters)
  - [REST API Reference](#rest-api-reference)
- [Changelog](#changelog)
- [Support](#support)
- [License](#license)

---

## About

**WPCalibrate Tiered Pricing for WooCommerce** is engineered for high-volume B2B, wholesale, and retail merchants who require robust, scalable tiered bulk pricing without sacrificing store speed.

Unlike traditional plugins that manipulate cart item prices through unsafe client-side scripts or redundant database queries, WPCalibrate Tiered Pricing operates through a **pure, side-effect-free server-authoritative calculation engine**. Tiers are validated strictly upon configuration and resolved deterministically on cart calculation cycles.

### Architecture Highlights
- **HPOS Compatible**: Fully declared compatibility with WooCommerce High-Performance Order Storage (`custom_order_tables`).
- **Cart & Checkout Blocks**: Deep integration with modern WooCommerce Cart and Checkout Blocks using the official Store API line item schema (`wp/v2/cart`).
- **Zero Query Overhead on Cart**: Rule resolutions are cached and memoized during runtime execution cycles to prevent repetitive metadata queries.
- **Side-Effect Free Engine**: Cart discounts are applied cleanly during `woocommerce_before_calculate_totals` with original price preservation to prevent price compounding.

---

## Features

### 1. Flexible Discount Pricing Types
- **Fixed Price per Unit**: Specify an exact unit price when buying within a tier (e.g. $18/unit when buying 10-19).
- **Percentage Discount**: Apply a percentage markdown off base or sale price (e.g. 15% off when buying 20+).
- **Open-Ended Final Tiers**: Support for open ranges (e.g. 50+ units with no upper boundary limit).

### 2. Scoped Quantity Aggregation
- **Individual Line Quantity**: Evaluates each product or variation line item independently.
- **Variable Product Combined**: Aggregates quantities across all selected variations of the parent variable product (e.g. buy 5 Blue and 5 Red shirts to hit the 10-item tier).
- **Category-Wide Quantity**: Aggregates quantities across all products in the cart sharing the assigned category.
- **Cart-Wide Quantity**: Evaluates the cumulative quantity of all items across the customer's entire cart.

### 3. Deterministic Precedence Engine
When calculating prices, conflicts are resolved in strict, deterministic order:
1. **Variation Rule**: Explicit tiers assigned on the specific variation.
2. **Product Rule**: Tiers assigned to the simple product or variable parent.
3. **Category Rule**: Category-level rules ordered by custom priority.
4. **Global Fallback Rule**: Store-wide fallback rule if enabled.

### 4. Interactive Frontend Pricing Table
- Configurable display positions: Before Add-to-Cart form, Before Add-to-Cart button, After Add-to-Cart form, or After single product summary.
- Dynamic row highlighting that tracks the customer's quantity input in real time.
- Optional customer savings column displaying monetary savings or percentage saved.
- Fully accessible semantic HTML table with ARIA attributes and screen reader labels.

### 5. Reactive Live Unit & Subtotal Preview
- Updates single product unit prices and line subtotals dynamically as the quantity input changes.
- Seamless variation switching: Automatically swaps displayed tier tables when a variation with distinct rules is selected.
- High-speed REST API fallback (`/wp-json/wpcalibrate-tiered-pricing/v1/calculate-price`) for dynamic price calculation.

### 6. Smart Coupon Interaction
- **Allow Coupons**: Apply store coupons on top of bulk tiered discounts.
- **Prevent Coupons**: Item-level coupon exclusion preventing store coupons from discounting tier-priced items while allowing coupons on non-tiered items.

### 7. In-Dashboard Automatic GitHub Updates
- Check GitHub Releases automatically via the WordPress dashboard.
- Update the plugin with one click right from **Plugins > Installed Plugins**, just like plugins hosted on WordPress.org.

---

## Documentation

### System Requirements

| Requirement | Minimum Version | Recommended |
|---|---|---|
| **WordPress** | 6.5 | 7.1+ |
| **WooCommerce** | 8.5 | 11.1+ |
| **PHP** | 8.2 | 8.3+ |
| **Database** | MySQL 5.7+ / MariaDB 10.4+ | MySQL 8.0+ |

### Installation

#### Method 1: WordPress Dashboard Upload (Recommended)
1. Download the latest `wpcalibrate-tiered-pricing-for-woocommerce-x.x.x.zip` from the [Releases](https://github.com/wpcalibrate/wpcalibrate-tiered-pricing-for-woocommerce/releases) page.
2. In your WordPress admin dashboard, navigate to **Plugins > Add New Plugin > Upload Plugin**.
3. Choose the downloaded ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

#### Method 2: FTP / Manual Upload
1. Unzip the downloaded archive.
2. Upload the `wpcalibrate-tiered-pricing-for-woocommerce` directory into your WordPress site's `wp-content/plugins/` directory.
3. Go to **Plugins > Installed Plugins** and activate **WPCalibrate Tiered Pricing for WooCommerce**.

---

### Automatic Updates from GitHub

This plugin includes an integrated **GitHub Updater** service. Whenever a new release or tag is published on the GitHub repository:

1. WordPress detects the update during scheduled transient checks (or when visiting **Dashboard > Updates**).
2. An update notice appears on the **Plugins** screen:
   > *There is a new version of WPCalibrate Tiered Pricing for WooCommerce available. View version details or update now.*
3. Clicking **Update Now** automatically downloads, unpacks, and activates the new version directly in your dashboard.

---

### Configuration & Rule Setup

Navigate to **WPCalibrate > Tiered Pricing** in your WordPress admin menu:

#### General Settings
- **Enable Tiered Pricing**: Master toggle to activate or deactivate pricing calculations store-wide.
- **Debug Logging**: Writes verbose calculation diagnostics to `WooCommerce > Status > Logs (wpcalibrate-tiered-pricing)`.
- **Delete Data on Uninstall**: Opt-in data erasure upon plugin deletion.

#### Pricing Behaviour
- **Base Price Strategy**:
  - *Current Effective Price*: Calculates tiers from the active sale price if on sale.
  - *Regular Price*: Always calculates tiers from the regular base price.
  - *Disable on Sale*: Bypasses tiered pricing for items that already have a sale price.
- **Default Calculation Scope**: Default quantity calculation scope for new rules.

#### Display Settings
- **Enable Frontend Table**: Toggle tier table on single product pages.
- **Display Position**: Choose between 4 hook locations around the add-to-cart form.
- **Column Customization**: Customize Quantity, Unit Price, and Savings labels.
- **Highlight Active Tier**: Dynamically highlights table rows as customers change quantity.
- **Live Total Preview**: Displays unit price and subtotal below the table.

#### Coupons & Sales
- Configure how WooCommerce coupons interact with products receiving tiered bulk discounts.

#### Rules
- **Category Rules**: Define quantity tiers for entire product categories with priority scoring.
- **Global Fallback Rule**: Store-wide default tiered pricing rule applied to products without specific rules.

---

### Deterministic Precedence Hierarchy

```
                    ┌─────────────────────────┐
                    │  Item in Cart / Page    │
                    └────────────┬────────────┘
                                 │
                   Does Variation have Rules?
                                 │
                     ┌───────────┴───────────┐
                    YES                      NO
                     │                       │
           [Use Variation Rule]     Does Parent Product have Rules?
                                             │
                                 ┌───────────┴───────────┐
                                YES                      NO
                                 │                       │
                       [Use Product Rule]       Do Categories have Rules?
                                                         │
                                             ┌───────────┴───────────┐
                                            YES                      NO
                                             │                       │
                                   [Use Highest Priority]     Is Global Rule Enabled?
                                                                     │
                                                         ┌───────────┴───────────┐
                                                        YES                      NO
                                                         │                       │
                                                [Use Global Rule]         [Standard Price]
```

---

### Developer Hooks & Filters

#### Filters

```php
// Filter calculated tiered unit price
add_filter( 'wpcttp_calculated_price', function( float $unit_price, array $tier, \WC_Product $product, float $qty ) {
    return $unit_price;
}, 10, 4 );

// Override GitHub Updater repository owner
add_filter( 'wpcttp_github_repo_owner', function( string $owner ) {
    return 'your-organization';
} );

// Override GitHub Updater repository name
add_filter( 'wpcttp_github_repo_name', function( string $repo ) {
    return 'wpcalibrate-tiered-pricing-for-woocommerce';
} );

// Customize pricing table container classes
add_filter( 'wpcttp_table_wrapper_classes', function( array $classes, \WC_Product $product ) {
    $classes[] = 'my-custom-theme-table';
    return $classes;
}, 10, 2 );
```

#### Actions

```php
// Action triggered before rendering frontend pricing table
add_action( 'wpcttp_before_pricing_table', function( \WC_Product $product, \WPCalibrate\TieredPricing\Data\PricingRule $rule ) {
    // Custom banner or badge
}, 10, 2 );

// Action triggered after rendering frontend pricing table
add_action( 'wpcttp_after_pricing_table', function( \WC_Product $product, \WPCalibrate\TieredPricing\Data\PricingRule $rule ) {
    // Custom note or disclaimer
}, 10, 2 );
```

---

### REST API Reference

#### Calculate Price Endpoint
Calculates the dynamic unit price, savings, and subtotal for a given product and quantity.

- **Route**: `POST /wp-json/wpcalibrate-tiered-pricing/v1/calculate-price`
- **Authentication**: Nonce verification (`X-WP-Nonce: wpcttp_frontend.nonce`)

**Request Payload:**
```json
{
  "product_id": 105,
  "variation_id": 108,
  "quantity": 15
}
```

**Response Payload:**
```json
{
  "success": true,
  "unit_price": 18.50,
  "unit_price_html": "$18.50",
  "line_total": 277.50,
  "line_total_html": "$277.50",
  "base_price": 25.00,
  "savings_amount": 6.50,
  "savings_percentage": 26.0,
  "savings_html": "$6.50 (26%)",
  "rule_source": "product",
  "tier_matched": {
    "min_qty": 10,
    "max_qty": 19,
    "pricing_type": "fixed",
    "value": 18.50
  }
}
```

---

## Changelog

### 1.0.1 - 2026-10-03
- **Fixed**: Admin Settings Tab Routing bug ("Sorry, you are not allowed to access this page." on tab switches).
- **Added**: Integrated GitHub-based automatic update system (`GitHubUpdater`) supporting in-dashboard 1-click updates and release detail modals.
- **Added**: Dynamic admin menu routing preserving both top-level (`wpcalibrate`) and canonical submenu (`wpcalibrate-tiered-pricing`) page parameters.
- **Improved**: Complete comprehensive documentation and architectural guides.

### 1.0.0 - 2026-10-03
- **Initial Release**:
  - PSR-4 modular architecture under `WPCalibrate\TieredPricing`.
  - Deterministic pricing engine supporting fixed price per unit and percentage discounts.
  - Multi-tier repeater editor for simple products, variable parents, and variations.
  - Category-level rules manager with priority resolution.
  - Store-wide global fallback pricing rule.
  - Scoped calculations: Individual, Variable Combined, Category, Cart-Wide.
  - Frontend pricing tables with real-time active row highlighting.
  - Live client-side unit price and line subtotal calculation.
  - Full compatibility with WooCommerce HPOS (`custom_order_tables`).
  - Full compatibility with Cart & Checkout Blocks via WooCommerce Store API.
  - Configurable coupon exclusion strategies on discounted items.
  - Built-in WooCommerce Logger integration (`wpcalibrate-tiered-pricing`).
  - Safe activation, deactivation cache flush, and opt-in uninstall data erasure.

---

## Support

Need assistance with setup, configuration, or custom integrations?

- **Direct Support Email**: [support@wpcalibrate.com](mailto:support@wpcalibrate.com)
- **Phone / WhatsApp**: [+447474795976](https://wa.me/447474795976)
- **Official Website**: [https://wpcalibrate.com](https://wpcalibrate.com)
- **Plugin Marketplace**: [https://marketplace.wpcalibrate.com](https://marketplace.wpcalibrate.com/)

---

## License

This project is licensed under the **GNU General Public License v2.0 or later** - see the [LICENSE](LICENSE) file for details.
