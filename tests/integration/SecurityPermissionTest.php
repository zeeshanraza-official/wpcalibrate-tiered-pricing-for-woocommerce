<?php
/**
 * Security and Permission Tests.
 *
 * @package WPCalibrate\TieredPricing\Tests\Integration
 */

declare(strict_types=1);

namespace WPCalibrate\TieredPricing\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WPCalibrate\TieredPricing\Admin\ProductAdmin;
use WPCalibrate\TieredPricing\Contracts\RuleRepositoryInterface;

/**
 * Class SecurityPermissionTest
 */
final class SecurityPermissionTest extends TestCase {

	public function test_save_product_meta_aborts_without_valid_nonce(): void {
		$repo_mock = $this->createMock( RuleRepositoryInterface::class );
		$repo_mock->expects( $this->never() )->method( 'save_for_product' );

		$admin = new ProductAdmin( $repo_mock );

		// Simulate request with missing nonce
		$_POST = array(
			'wpcttp_rule' => array(
				'enabled' => '1',
				'tiers'   => array(
					array( 'min_qty' => 5, 'value' => 20 ),
				),
			),
		);

		$admin->save_product_meta( 123 );
		$this->assertTrue( true );
	}
}
