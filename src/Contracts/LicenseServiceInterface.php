<?php
/**
 * License Service Interface.
 *
 * @package WPCalibrate\TieredPricing\Contracts
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Contracts;

/**
 * Interface LicenseServiceInterface
 */
interface LicenseServiceInterface {

	/**
	 * Attempt to activate the plugin with a license key.
	 *
	 * @param string $license_key License key.
	 * @return array{success: bool, message: string, code?: string}
	 */
	public function activate( string $license_key ): array;

	/**
	 * Attempt to deactivate the current license.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function deactivate(): array;

	/**
	 * Get current license status ('not_configured', 'active', 'expired', 'invalid').
	 *
	 * @return string
	 */
	public function get_status(): string;

	/**
	 * Get human-readable license status label.
	 *
	 * @return string
	 */
	public function get_status_label(): string;

	/**
	 * Get expiry date string or null if not applicable.
	 *
	 * @return ?string
	 */
	public function get_expiry(): ?string;

	/**
	 * Get masked license key for secure display.
	 *
	 * @return string
	 */
	public function get_masked_license_key(): string;

	/**
	 * Get renewal or account URL.
	 *
	 * @return string
	 */
	public function get_renewal_url(): string;

	/**
	 * Whether a real licensing provider is configured and active.
	 *
	 * @return bool
	 */
	public function is_configured(): bool;

	/**
	 * Whether the plugin is allowed to run tiered pricing.
	 * Defaults to true for unconfigured provider (does not block core functionality).
	 *
	 * @return bool
	 */
	public function is_valid(): bool;
}
