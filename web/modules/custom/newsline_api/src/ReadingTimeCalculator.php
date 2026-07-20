<?php

declare(strict_types=1);

namespace Drupal\newsline_api;

/**
 * Estimates the reading time of a block of text.
 *
 * Kept as a dependency-free service so the calculation can be unit tested in
 * isolation and reused independently of the feed normalizer.
 */
final class ReadingTimeCalculator {

  /**
   * Fallback reading speed in words per minute.
   */
  public const DEFAULT_WORDS_PER_MINUTE = 200;

  /**
   * Estimates reading time in whole minutes.
   *
   * @param string $text
   *   Plain-text content to measure. Callers are responsible for stripping any
   *   markup before passing it in.
   * @param int $words_per_minute
   *   Average reading speed. Values below one fall back to the default so the
   *   result can never be negative or divide by zero.
   *
   * @return int
   *   The estimated reading time, rounded up, with a floor of one minute.
   */
  public function calculate(string $text, int $words_per_minute = self::DEFAULT_WORDS_PER_MINUTE): int {
    if ($words_per_minute < 1) {
      $words_per_minute = self::DEFAULT_WORDS_PER_MINUTE;
    }

    $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $word_count = count($words);
    if ($word_count === 0) {
      return 1;
    }

    return max(1, (int) ceil($word_count / $words_per_minute));
  }

}
