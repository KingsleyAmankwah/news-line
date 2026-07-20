<?php

declare(strict_types=1);

namespace Drupal\newsline_api;

use Drupal\Component\Transliteration\TransliterationInterface;

/**
 * Converts arbitrary text into URL-safe, lowercase slugs.
 *
 * The same implementation is used to expose stable taxonomy slugs in the API
 * response and to resolve the ?category= query filter back to a term, so both
 * directions are guaranteed to agree on the slug format.
 */
final class Slugifier {

  public function __construct(
    protected readonly TransliterationInterface $transliteration,
  ) {}

  /**
   * Builds a slug from the given text.
   *
   * @param string $text
   *   The source text, e.g. a taxonomy term label.
   *
   * @return string
   *   A lowercase, hyphen-separated ASCII slug. May be an empty string if the
   *   input contains no transliterable alphanumeric characters.
   */
  public function slugify(string $text): string {
    $text = $this->transliteration->transliterate($text, 'en');
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';

    return trim($text, '-');
  }

}
