<?php
/**
 * The known-store catalog and the defaults drawn from it.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy\Tests;

use PHPUnit\Framework\TestCase;
use WhereToBuy\Stores;

final class StoresCatalogTest extends TestCase {

	public function testDefaultsAreTheSixStartingStoresInOrder(): void {
		$this->assertSame( array( 'amazon', 'walmart', 'best-buy', 'target', 'chewy', 'ebay' ), array_column( Stores::defaults(), 'id' ) );
	}

	public function testDefaultsHaveExactlyTheSavedStoreFields(): void {
		foreach ( Stores::defaults() as $store ) {
			$this->assertSame( array_keys( Stores::blank() ), array_keys( $store ) );
		}
	}

	public function testCatalogIdsAreUniqueAndEveryStoreHasDomains(): void {
		$ids = array_column( Stores::catalog(), 'id' );
		$this->assertSame( $ids, array_values( array_unique( $ids ) ) );
		foreach ( Stores::catalog() as $store ) {
			$this->assertNotSame( '', $store['name'] );
			$this->assertNotSame( '', $store['domains'], $store['id'] . ' has no domains' );
		}
	}

	public function testCatalogLogosExistInTheLogosDownload(): void {
		foreach ( Stores::catalog() as $store ) {
			if ( '' !== $store['logo'] ) {
				$this->assertFileExists( dirname( __DIR__ ) . '/store-logos/png/' . $store['logo'] . '.png' );
			}
		}
	}
}
