# TROUBLESHOOTING.md - Known Issues & Resolutions

## Bug 1: "Sorry, you are not allowed to access this page." on Settings Tabs

### Symptoms
When accessing the settings page under **WPCalibrate > Tiered Pricing**, the first tab (`General`) loads correctly. However, clicking any other tab (`Pricing Behaviour`, `Display`, `Coupons & Sales`, `Rules`, `License`, `Support`) leads to the WordPress error:
> "Sorry, you are not allowed to access this page."

### Root Cause
1. In `AdminMenu.php`, when no parent `wpcalibrate` menu existed, the plugin registered `wpcalibrate` via `add_menu_page()` and `add_submenu_page()`.
2. The canonical submenu slug `wpcalibrate-tiered-pricing` was NOT registered with WordPress when `$parent_exists` was false.
3. In `SettingsPage.php`, tab links hardcoded `'page' => AdminMenu::SUBMENU_SLUG` (`wpcalibrate-tiered-pricing`).
4. When clicking a tab from `page=wpcalibrate`, WordPress redirected to `page=wpcalibrate-tiered-pricing&tab=...`. Because that page slug was not registered, WordPress security triggered `wp_die( __( 'Sorry, you are not allowed to access this page.' ), 403 )`.

### Solution
1. **Dynamic Page Slug in SettingsPage.php**: Ensure tab links preserve the active `page` parameter (`wpcalibrate` or `wpcalibrate-tiered-pricing`).
2. **Register Alias Submenu in AdminMenu.php**: Always register both the primary menu slug and the canonical submenu slug (`wpcalibrate-tiered-pricing`) using `add_submenu_page( null, ... )` so WordPress acknowledges both slugs as valid authorized pages.
3. **Form Action**: Explicitly output the current page and tab in the settings form action.
