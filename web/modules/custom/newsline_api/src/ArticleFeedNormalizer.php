<?php

declare(strict_types=1);

namespace Drupal\newsline_api;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Psr\Log\LoggerInterface;

/**
 * Default implementation of the article feed normalizer.
 *
 * Converts Drupal's internal entity/field structure into a flattened, renamed,
 * frontend-oriented payload: media references become resolved absolute
 * image-style URLs, entity references become {id, name, slug} objects, and
 * timestamps become ISO 8601 strings. Every field access is defensive so that
 * partially populated or structurally broken content degrades to null rather
 * than producing a fatal error that would break the whole feed.
 */
final class ArticleFeedNormalizer implements ArticleFeedNormalizerInterface {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly DateFormatterInterface $dateFormatter,
    protected readonly FileUrlGeneratorInterface $fileUrlGenerator,
    protected readonly Slugifier $slugifier,
    protected readonly ReadingTimeCalculator $readingTimeCalculator,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function normalize(NodeInterface $node, RefinableCacheableDependencyInterface $cacheability): array {
    $cacheability->addCacheableDependency($node);
    $settings = $this->configFactory->get('newsline_api.settings');
    $cacheability->addCacheableDependency($settings);

    $body = $this->getStringValue($node, 'body');
    $words_per_minute = (int) $settings->get('reading_words_per_minute');

    return [
      'id' => $node->uuid(),
      'type' => 'article',
      'title' => (string) $node->label(),
      'path' => $this->resolvePath($node, $cacheability),
      'summary' => $this->getStringValue($node, 'field_summary'),
      'promoted' => $node->isPromoted(),
      'sticky' => $node->isSticky(),
      'readingTimeMinutes' => $this->readingTimeCalculator->calculate(strip_tags($body), $words_per_minute),
      'publishedAt' => $this->formatTimestamp((int) $node->getCreatedTime()),
      'updatedAt' => $this->formatTimestamp((int) $node->getChangedTime()),
      'author' => $this->normalizeAuthor($node, $cacheability),
      'category' => $this->normalizeCategory($node, $cacheability),
      'tags' => $this->normalizeTags($node, $cacheability),
      'hero' => $this->normalizeHero($node, $cacheability, $settings),
    ];
  }

  /**
   * Resolves the node's canonical path (URL alias) with cache metadata.
   */
  private function resolvePath(NodeInterface $node, RefinableCacheableDependencyInterface $cacheability): string {
    $generated = $node->toUrl('canonical', ['absolute' => FALSE])->toString(TRUE);
    $cacheability->addCacheableDependency($generated);

    return $generated->getGeneratedUrl();
  }

  /**
   * Returns a plain-string field value, or an empty string when unavailable.
   */
  private function getStringValue(NodeInterface $node, string $field_name): string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return '';
    }

    return (string) $node->get($field_name)->value;
  }

  /**
   * Formats a Unix timestamp as an ISO 8601 string in UTC.
   */
  private function formatTimestamp(int $timestamp): string {
    return $this->dateFormatter->format($timestamp, 'custom', 'c', 'UTC');
  }

  /**
   * Normalizes the node author into an {id, name} object, or null.
   */
  private function normalizeAuthor(NodeInterface $node, RefinableCacheableDependencyInterface $cacheability): ?array {
    $owner = $node->getOwner();
    if ($owner === NULL) {
      return NULL;
    }
    $cacheability->addCacheableDependency($owner);

    return [
      'id' => $owner->uuid(),
      'name' => $owner->getDisplayName(),
    ];
  }

  /**
   * Normalizes the single category reference into a term object, or null.
   */
  private function normalizeCategory(NodeInterface $node, RefinableCacheableDependencyInterface $cacheability): ?array {
    if (!$node->hasField('field_category')) {
      return NULL;
    }
    $items = $node->get('field_category');
    if (!$items instanceof EntityReferenceFieldItemListInterface) {
      return NULL;
    }
    $term = $items->referencedEntities()[0] ?? NULL;

    return $term instanceof TermInterface ? $this->normalizeTerm($term, $cacheability) : NULL;
  }

  /**
   * Normalizes the multi-value tags reference into a list of term objects.
   *
   * @return array<int, array{id: string, name: string, slug: string}>
   *   The normalized tag terms.
   */
  private function normalizeTags(NodeInterface $node, RefinableCacheableDependencyInterface $cacheability): array {
    if (!$node->hasField('field_tags')) {
      return [];
    }
    $items = $node->get('field_tags');
    if (!$items instanceof EntityReferenceFieldItemListInterface) {
      return [];
    }
    $tags = [];
    foreach ($items->referencedEntities() as $term) {
      if ($term instanceof TermInterface) {
        $tags[] = $this->normalizeTerm($term, $cacheability);
      }
    }

    return $tags;
  }

  /**
   * Normalizes a taxonomy term into an {id, name, slug} object.
   *
   * @return array{id: string, name: string, slug: string}
   *   The normalized term.
   */
  private function normalizeTerm(TermInterface $term, RefinableCacheableDependencyInterface $cacheability): array {
    $cacheability->addCacheableDependency($term);
    $name = (string) $term->label();

    return [
      'id' => $term->uuid(),
      'name' => $name,
      'slug' => $this->slugifier->slugify($name),
    ];
  }

  /**
   * Resolves the hero image into absolute image-style URLs, or null.
   *
   * Any structural problem (missing source field, deleted file, unrenderable
   * derivative) is logged and degrades to null so a single broken image never
   * takes down the whole feed response.
   */
  private function normalizeHero(NodeInterface $node, RefinableCacheableDependencyInterface $cacheability, ImmutableConfig $settings): ?array {
    if (!$node->hasField('field_hero_image')) {
      return NULL;
    }
    $hero_items = $node->get('field_hero_image');
    if (!$hero_items instanceof EntityReferenceFieldItemListInterface) {
      return NULL;
    }
    $media = $hero_items->referencedEntities()[0] ?? NULL;
    if (!$media instanceof MediaInterface) {
      return NULL;
    }
    $cacheability->addCacheableDependency($media);

    try {
      $source_field = $media->getSource()->getConfiguration()['source_field'] ?? '';
      if ($source_field === '' || !$media->hasField($source_field)) {
        return NULL;
      }
      $image_items = $media->get($source_field);
      $item = $image_items->first();
      if ($item === NULL || !$image_items instanceof EntityReferenceFieldItemListInterface) {
        return NULL;
      }
      $file = $image_items->referencedEntities()[0] ?? NULL;
      if (!$file instanceof FileInterface) {
        return NULL;
      }
      $cacheability->addCacheableDependency($file);
      $uri = $file->getFileUri();

      $hero_style = $this->loadImageStyle((string) $settings->get('hero_image_style'), $cacheability);
      $thumbnail_style = $this->loadImageStyle((string) $settings->get('thumbnail_image_style'), $cacheability);

      // Read the image item's stored properties via its value array rather than
      // magic properties, which are invisible to static analysis on the base
      // FieldItemInterface returned by first().
      $image = $item->getValue();

      return [
        'alt' => (string) ($image['alt'] ?? ''),
        'width' => isset($image['width']) ? (int) $image['width'] : NULL,
        'height' => isset($image['height']) ? (int) $image['height'] : NULL,
        'src' => $hero_style?->buildUrl($uri) ?? $this->fileUrlGenerator->generateAbsoluteString($uri),
        'thumbnail' => $thumbnail_style?->buildUrl($uri),
      ];
    }
    catch (\Throwable $e) {
      $this->logger->error('Failed to resolve hero image for article @id: @message', [
        '@id' => $node->id(),
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Loads an image style by machine name and records it as a cache dependency.
   */
  private function loadImageStyle(string $id, RefinableCacheableDependencyInterface $cacheability): ?ImageStyleInterface {
    if ($id === '') {
      return NULL;
    }
    $style = $this->entityTypeManager->getStorage('image_style')->load($id);
    if ($style instanceof ImageStyleInterface) {
      $cacheability->addCacheableDependency($style);
      return $style;
    }

    return NULL;
  }

}
