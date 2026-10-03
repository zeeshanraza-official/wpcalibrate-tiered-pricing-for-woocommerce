# PROJECT_MEMORY.md - Architectural Context & State

## Purpose
This document preserves key architectural decisions, domain models, and established patterns for **WPCalibrate Tiered Pricing for WooCommerce**.

## Core Concepts & Design Decisions

### 1. Admin Menu Structure & Slugs
- Top-level shared menu: `PARENT_SLUG = 'wpcalibrate'`.
- Primary submenu slug: `SUBMENU_SLUG = 'wpcalibrate-tiered-pricing'`.
- **Key Requirement**: The settings page must be accessible via both `page=wpcalibrate` (when registered as the first/default WPCalibrate item) and `page=wpcalibrate-tiered-pricing` (canonical direct slug and when attached as a sibling submenu).
- Submenu tab navigation must preserve the active `page` parameter so user navigates seamlessly across all tabs without triggering capability or slug routing errors.

### 2. Precedence Hierarchy
Rules are resolved deterministically:
1. **Variation Rule**: Meta on `_wpcttp_rules` for the variation ID.
2. **Product Rule**: Meta on parent/simple product ID.
3. **Category Rule**: Category term meta, ordered by highest priority.
4. **Global Fallback Rule**: Store-wide fallback rule in `wpcttp_global_rule`.

### 3. Pricing Calculation Strategies
- `current_effective`: Discounts calculated off current active price (including sale price if present).
- `regular`: Discounts calculated off regular price.
- `disable_on_sale`: If item has an active sale price, tiered pricing is bypassed.

### 4. Remote Test Environment
- Host: `169.58.213.1`
- Plugin Path: `/wp-content/plugins/wpcalibrate-tiered-pricing-for-woocommerce`
- Synchronization mechanism: `sync-ftp.ps1` via FTP.
