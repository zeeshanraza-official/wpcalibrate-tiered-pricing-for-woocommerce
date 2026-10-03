<?php
/**
 * Unconfigured / Null License Service.
 *
 * @package WPCalibrate\TieredPricing\Licensing
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Licensing;

use WPCalibrate\TieredPricing\Contracts\LicenseServiceInterface;

/**
 * Class UnconfiguredLicenseService
 */
final class UnconfiguredLicenseService implements LicenseServiceInterface {

	/**
	 * Status constant.
	 */
	public const STATUS_NOT_CONFIGURED = 'not_configured';

	/**
	 * Option name for license key.
	 */
	public const OPTION_KEY = 'wpcttp_license_key';

	/**
	 * {@inheritDoc}
	 */
	public function activate( string $license_key ): array {
		$sanitized_key = sanitize_text_field( trim( $license_key ) );

		if ( empty( $sanitized_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please enter a valid license key.', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
			);
		}

		// Store key securely for future provider, but do not claim fake activation.
		update_option( self::OPTION_KEY, $sanitized_key );

		return array(
			'success' => false,
			'message' => __( 'License server is not yet configured. The license key has been saved, but validation is currently unconfigured.', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
			'code'    => self::STATUS_NOT_CONFIGURED,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function deactivate(): array {
		delete_option( self::OPTION_KEY );

		return array(
			'success' => true,
			'message' => __( 'License key removed.', 'wpcalibrate-tiered-pricing-for-woocommerce' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_status(): string {
		return self::STATUS_NOT_CONFIGURED;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_status_label(): string {
		return __( 'Not configured', 'wpcalibrate-tiered-pricing-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_expiry(): ?string {
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_masked_license_key(): string {
		$key = (string) get_option( self::OPTION_KEY, '' );
		if ( empty( $key ) ) {
			return '';
		}

		$length = strlen( $key );
		if ( $length <= 4 ) {
			return str_repeat( '*', $length );
		}

		$visible_suffix = substr( $key, -4 );
		return str_repeat( '*', min( 20, $length - 4 ) ) . $visible_suffix;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_renewal_url(): string {
		return 'https://marketplace.wpcalibrate.com/';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_valid(): bool {
		// Does not block core functionality when unconfigured.
		return true;
	}
}
