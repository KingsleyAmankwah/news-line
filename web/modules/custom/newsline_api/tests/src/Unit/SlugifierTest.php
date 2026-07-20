<?php

declare(strict_types=1);

namespace Drupal\Tests\newsline_api\Unit;

use Drupal\Component\Transliteration\PhpTransliteration;
use Drupal\newsline_api\Slugifier;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests slug generation.
 */
#[CoversClass(Slugifier::class)]
#[Group('newsline_api')]
final class SlugifierTest extends UnitTestCase {

  /**
   * The slugifier under test.
   */
  private Slugifier $slugifier;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->slugifier = new Slugifier(new PhpTransliteration());
  }

  /**
   * Tests slug generation across a range of inputs.
   */
  #[DataProvider('slugProvider')]
  public function testSlugify(string $input, string $expected): void {
    $this->assertSame($expected, $this->slugifier->slugify($input));
  }

  /**
   * Data provider for testSlugify().
   *
   * @return array<string, array{string, string}>
   *   Sets of [input, expected slug].
   */
  public static function slugProvider(): array {
    return [
      'single word lowercased' => ['Engineering', 'engineering'],
      'spaces become hyphens' => ['Hello World', 'hello-world'],
      'symbols and ampersands collapse' => ['Café & Bar', 'cafe-bar'],
      'accents transliterated' => ['Über Åland', 'uber-aland'],
      'leading and trailing separators trimmed' => ['  --Breaking News!--  ', 'breaking-news'],
      'runs of separators collapse' => ['a---b   c', 'a-b-c'],
      'digits preserved' => ['Top 10 Stories', 'top-10-stories'],
      'no alphanumerics yields empty string' => ['---', ''],
    ];
  }

}
