<?php

declare(strict_types=1);

namespace Drupal\Tests\newsline_api\Unit;

use Drupal\newsline_api\ReadingTimeCalculator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the reading-time estimation.
 */
#[CoversClass(ReadingTimeCalculator::class)]
#[Group('newsline_api')]
final class ReadingTimeCalculatorTest extends UnitTestCase {

  /**
   * The calculator under test.
   */
  private ReadingTimeCalculator $calculator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->calculator = new ReadingTimeCalculator();
  }

  /**
   * Tests reading-time estimation across a range of inputs.
   */
  #[DataProvider('readingTimeProvider')]
  public function testCalculate(string $text, int $wpm, int $expected): void {
    $this->assertSame($expected, $this->calculator->calculate($text, $wpm));
  }

  /**
   * Data provider for testCalculate().
   *
   * @return array<string, array{string, int, int}>
   *   Sets of [text, words per minute, expected minutes].
   */
  public static function readingTimeProvider(): array {
    return [
      'empty text floors to one minute' => ['', 200, 1],
      'whitespace only floors to one minute' => ["   \n\t ", 200, 1],
      'exactly one minute of words' => [str_repeat('word ', 200), 200, 1],
      'one word over rounds up' => [str_repeat('word ', 201), 200, 2],
      'two full minutes' => [str_repeat('word ', 400), 200, 2],
      'slower reader takes longer' => [str_repeat('word ', 200), 100, 2],
      'collapses irregular whitespace' => ["one\n\ntwo   three\tfour", 200, 1],
      'invalid wpm falls back to default' => [str_repeat('word ', 201), 0, 2],
      'negative wpm falls back to default' => [str_repeat('word ', 201), -50, 2],
    ];
  }

}
