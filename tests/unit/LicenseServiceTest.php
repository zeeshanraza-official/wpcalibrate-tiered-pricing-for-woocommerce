<?php
/**
 * License Service Unit Tests.
 *
 * @package WPCalibrate\TieredPricing\Tests\Unit
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPCalibrate\TieredPricing\Licensing\UnconfiguredLicenseService;

/**
 * Class LicenseServiceTest
 */
final class LicenseServiceTest extends TestCase {

	private UnconfiguredLicenseService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->service = new UnconfiguredLicenseService();
	}

	public function test_initial_unconfigured_state(): void {
		$this->assertSame( 'not_configured', $this->service->get_status() );
		$this->assertFalse( $this->service->is_configured() );
		$this->assertTrue( $this->service->is_valid(), 'Core functionality should never be blocked by unconfigured license' );
		$this->assertNull( $this->service->get_expiry() );
	}

	public function test_activate_does_not_return_fake_success(): void {
		$result = $this->service->activate( 'SAMPLE-LICENSE-KEY-1234' );

		$this->assertFalse( $result['success'], 'Should never return fake success' );
		$this->assertSame( 'not_configured', $result['code'] );
	}
}
