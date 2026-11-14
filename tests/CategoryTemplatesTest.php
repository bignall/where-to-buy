<?php
/**
 * Category pages fall back to the item list template.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy {
	// Stand-in for WordPress's is_tax(); unqualified calls in this namespace resolve here first.
	function is_tax( string $taxonomy = '' ): bool {
		return $GLOBALS['wtb_test_is_tax'] === $taxonomy;
	}
}

namespace WhereToBuy\Tests {

	use PHPUnit\Framework\TestCase;
	use WhereToBuy\Items;

	final class CategoryTemplatesTest extends TestCase {

		private const WP_DEFAULT = array( 'taxonomy-wtb_item_category-books.php', 'taxonomy-wtb_item_category.php', 'taxonomy.php' );

		public function testListTemplateGoesBeforeGenericTaxonomy(): void {
			$GLOBALS['wtb_test_is_tax'] = Items::TAXONOMY;
			$this->assertSame(
				array( 'taxonomy-wtb_item_category-books.php', 'taxonomy-wtb_item_category.php', 'archive-wtb_item.php', 'taxonomy.php' ),
				Items::categoryTemplates( self::WP_DEFAULT )
			);
		}

		public function testAppendedWhenTaxonomyTemplateMissingFromList(): void {
			$GLOBALS['wtb_test_is_tax'] = Items::TAXONOMY;
			$this->assertSame( array( 'taxonomy-wtb_item_category.php', 'archive-wtb_item.php' ), Items::categoryTemplates( array( 'taxonomy-wtb_item_category.php' ) ) );
		}

		public function testOtherTaxonomiesUntouched(): void {
			$GLOBALS['wtb_test_is_tax'] = 'genre';
			$list = array( 'taxonomy-genre.php', 'taxonomy.php' );
			$this->assertSame( $list, Items::categoryTemplates( $list ) );
		}
	}
}
