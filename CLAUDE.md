# CLAUDE.md - Developer Guide & Project Context

## Project Overview
**WPCalibrate Tiered Pricing for WooCommerce** is an enterprise-grade WordPress/WooCommerce plugin providing server-authoritative quantity-based tiered pricing rules for WooCommerce products, variations, categories, and cart-wide items.

- **Plugin Namespace**: `WPCalibrate\TieredPricing`
- **Minimum PHP**: 8.2 (Strict types enabled)
- **Minimum WooCommerce**: 8.5+ (HPOS & Cart/Checkout Blocks compatible)
- **Minimum WordPress**: 6.5+

## Architecture & Code Structure
- `wpcalibrate-tiered-pricing-for-woocommerce.php`: Main bootstrap, constants, dependency verification (PHP 8.2+, WooCommerce active), HPOS declaration.
- `src/Autoloader.php`: PSR-4 autoloader for `WPCalibrate\TieredPricing\` namespace.
- `src/Plugin.php`: Singleton container orchestrating all sub-services.
- `src/Admin/AdminMenu.php`: Admin menu coordinator with sibling WPCalibrate compatibility.
- `src/Admin/SettingsPage.php`: Central multi-tab settings screen (General, Pricing Behaviour, Display, Coupons & Sales, Rules, License, Support).
- `src/Admin/ProductAdmin.php`: Single product & variation tiered pricing data panels and repeater editor.
- `src/Admin/CategoryRuleAdmin.php`: Category-level pricing rule management.
- `src/Pricing/PricingEngine.php`: Evaluates rules and calculates unit discounts.
- `src/Pricing/PricingCalculator.php`: Pure computational engine (handles effective vs regular price, percent, fixed).
- `src/Rules/RuleRepository.php`: Data access layer for product, category, and global rules.
- `src/Rules/RuleResolver.php`: Deterministic rule precedence (Variation > Product > Category > Global).
- `src/Rules/QuantityResolver.php`: Scoped quantity resolver (Individual, Variable Combined, Category, Cart-Wide).
- `src/Cart/CartPricing.php`: Integrates with `woocommerce_before_calculate_totals` for server-side cart pricing.
- `src/Cart/CouponHandler.php`: Coordinates coupon strategy (allow vs prevent on tiered items).
- `src/Frontend/TierTableRenderer.php`: Frontend table rendering on single product pages.
- `src/Frontend/LivePricing.php`: Client-side real-time calculation and DOM update.
- `src/REST/PricingController.php`: REST endpoint `/wp-json/wpcalibrate-tiered-pricing/v1/calculate-price`.

## Deployment & Development Workflow
- **Live FTP Server**: Host `169.58.213.1`, remote root `/wp-content/plugins/wpcalibrate-tiered-pricing-for-woocommerce`.
- **Live Watch & Auto-Upload**: Run `.\watch.bat` or `powershell -ExecutionPolicy Bypass -File .\sync-ftp.ps1 -Watch`.
- **Full Deploy**: Run `.\deploy.bat` or `powershell -ExecutionPolicy Bypass -File .\sync-ftp.ps1`.
- **Config**: Credentials and remote path stored in `ftp-config.json` (git-ignored).
