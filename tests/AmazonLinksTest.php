<?php
/**
 * Unit tests for AmazonLinks (plain PHP, no WordPress needed).
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WhereToBuy\AmazonLinks;

final class AmazonLinksTest extends TestCase {

	private const TAG = 'bignall098-20';

	public function testRealSearchLinkKeepsItsOwnTag(): void {
		$url = 'https://www.amazon.com/Atomic-Habits-Proven-Build-Break/dp/0735211299/ref=sr_1_1?crid=2V9436DZJ6IJF&dib=eyJ2IjoiMSJ9.abc&dib_tag=se&keywords=atomic+habits&qid=1731600000&sprefix=atomic+habit%2Caps%2C150&sr=8-1&tag=bignall098-20';
		$this->assertSame( 'https://www.amazon.com/dp/0735211299/?tag=bignall098-20', AmazonLinks::shorten( $url, 'other-20' ) );
	}

	/** @return array<string, array{string, string}> */
	public static function productPaths(): array {
		return array(
			'dp'               => array( 'https://www.amazon.com/dp/B08N5WRWNW', 'B08N5WRWNW' ),
			'dp trailing'      => array( 'https://www.amazon.com/dp/B08N5WRWNW/ref=cm_sw_r_cp_api', 'B08N5WRWNW' ),
			'title dp'         => array( 'https://www.amazon.com/Echo-Dot-5th-Gen/dp/B09B8V1LZ3/?th=1', 'B09B8V1LZ3' ),
			'gp product'       => array( 'https://www.amazon.com/gp/product/B07FZ8S74R/ref=ppx_yo_dt_b_asin_title', 'B07FZ8S74R' ),
			'gp aw d (mobile)' => array( 'https://www.amazon.com/gp/aw/d/B07FZ8S74R?psc=1', 'B07FZ8S74R' ),
			'no www'           => array( 'https://amazon.com/dp/0735211299', '0735211299' ),
			'lower case asin'  => array( 'https://www.amazon.com/dp/b08n5wrwnw', 'B08N5WRWNW' ),
		);
	}

	#[DataProvider( 'productPaths' )]
	public function testProductPathsShortenWithFallbackTag( string $url, string $asin ): void {
		$this->assertSame( AmazonLinks::asin( $url ), $asin );
		$this->assertSame( 'https://www.amazon.com/dp/' . $asin . '/?tag=' . self::TAG, AmazonLinks::shorten( $url, self::TAG ) );
	}

	public function testNoTagAnywhereGivesBareProductLink(): void {
		$this->assertSame( 'https://www.amazon.com/dp/0735211299/', AmazonLinks::shorten( 'https://www.amazon.com/dp/0735211299?ref=x' ) );
	}

	public function testEmptyTagInLinkFallsBackToStoreTag(): void {
		$this->assertSame(
			'https://www.amazon.com/dp/0735211299/?tag=' . self::TAG,
			AmazonLinks::shorten( 'https://www.amazon.com/dp/0735211299?tag=&qid=1', self::TAG )
		);
	}

	/** @return array<string, array{string, string}> */
	public static function otherStorefronts(): array {
		return array(
			'uk'        => array( 'https://www.amazon.co.uk/Some-Book/dp/0735211299/ref=sr_1_1?keywords=x&tag=me-21', 'https://www.amazon.co.uk/dp/0735211299/?tag=me-21' ),
			'germany'   => array( 'https://www.amazon.de/gp/product/B07FZ8S74R?tag=me-21', 'https://www.amazon.de/dp/B07FZ8S74R/?tag=me-21' ),
			'australia' => array( 'https://www.amazon.com.au/dp/B07FZ8S74R?tag=me-22', 'https://www.amazon.com.au/dp/B07FZ8S74R/?tag=me-22' ),
			'canada'    => array( 'https://amazon.ca/dp/B07FZ8S74R?tag=me-20', 'https://www.amazon.ca/dp/B07FZ8S74R/?tag=me-20' ),
			'smile'     => array( 'https://smile.amazon.com/dp/B07FZ8S74R?tag=me-20', 'https://www.amazon.com/dp/B07FZ8S74R/?tag=me-20' ),
			'amzn.com'  => array( 'https://amzn.com/B07FZ8S74R', 'https://www.amazon.com/dp/B07FZ8S74R/?tag=' . self::TAG ),
		);
	}

	#[DataProvider( 'otherStorefronts' )]
	public function testOtherStorefrontsKeepTheirDomain( string $url, string $expected ): void {
		$this->assertSame( $expected, AmazonLinks::shorten( $url, self::TAG ) );
	}

	/** @return array<string, array{string}> */
	public static function unchanged(): array {
		return array(
			'search page'     => array( 'https://www.amazon.com/s?k=atomic+habits&tag=me-20' ),
			'storefront'      => array( 'https://www.amazon.com/shop/someone' ),
			'short dp asin'   => array( 'https://www.amazon.com/dp/B08N5WRW' ),
			'not amazon'      => array( 'https://www.walmart.com/dp/B08N5WRWNW' ),
			'lookalike host'  => array( 'https://www.notamazon.com/dp/B08N5WRWNW' ),
			'amazon in path'  => array( 'https://example.com/amazon.com/dp/B08N5WRWNW' ),
			'amazon aws'      => array( 'https://amazonaws.com/dp/B08N5WRWNW' ),
			'not a url'       => array( 'amazon dp B08N5WRWNW' ),
		);
	}

	#[DataProvider( 'unchanged' )]
	public function testLinksWithoutAnAsinAreLeftAlone( string $url ): void {
		$this->assertSame( $url, AmazonLinks::shorten( $url, self::TAG ) );
	}

	public function testShortLinkIsResolvedThenShortened(): void {
		$resolve = static fn ( string $url ): string => 'https://www.amazon.com/Atomic-Habits/dp/0735211299?linkCode=sl1&tag=bignall098-20&linkId=abc';
		$this->assertSame( 'https://www.amazon.com/dp/0735211299/?tag=bignall098-20', AmazonLinks::shorten( 'https://amzn.to/3xYzAbC', 'x-20', 'tag', $resolve ) );
		$this->assertSame( 'https://www.amazon.com/dp/0735211299/?tag=bignall098-20', AmazonLinks::shorten( 'https://a.co/d/abc123', 'x-20', 'tag', $resolve ) );
	}

	public function testShortLinkLeftAloneWhenItCannotBeResolved(): void {
		$this->assertSame( 'https://amzn.to/3xYzAbC', AmazonLinks::shorten( 'https://amzn.to/3xYzAbC', self::TAG ) );
		$this->assertSame( 'https://amzn.to/3xYzAbC', AmazonLinks::shorten( 'https://amzn.to/3xYzAbC', self::TAG, 'tag', static fn (): string => '' ) );
	}

	public function testIsAmazon(): void {
		$this->assertTrue( AmazonLinks::isAmazon( 'https://www.amazon.co.jp/dp/B07FZ8S74R' ) );
		$this->assertTrue( AmazonLinks::isAmazon( 'https://amzn.to/abc' ) );
		$this->assertFalse( AmazonLinks::isAmazon( 'https://www.target.com/p/x' ) );
		$this->assertFalse( AmazonLinks::isAmazon( '' ) );
	}
}
