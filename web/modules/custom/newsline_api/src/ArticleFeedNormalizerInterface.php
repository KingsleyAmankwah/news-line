<?php

declare(strict_types=1);

namespace Drupal\newsline_api;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\node\NodeInterface;

/**
 * Shapes article nodes into the Article Feed API's public JSON structure.
 */
interface ArticleFeedNormalizerInterface {

  /**
   * Normalizes a single article node into the API response structure.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The article node to normalize.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   Collects cache metadata for every entity and configuration object the
   *   normalized output depends on. Mutated in place so the caller can attach
   *   the accumulated metadata to the response.
   *
   * @return array
   *   A flattened, renamed, frontend-friendly representation of the article.
   */
  public function normalize(NodeInterface $node, RefinableCacheableDependencyInterface $cacheability): array;

  /**
   * Normalizes an article for a single-article (detail) response.
   *
   * Returns the same shape as normalize() plus the rendered body HTML, which
   * the feed deliberately omits.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The article node to normalize.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   Collects cache metadata for the entities and configuration read.
   *
   * @return array
   *   The normalized article including a "body" HTML string.
   */
  public function normalizeDetail(NodeInterface $node, RefinableCacheableDependencyInterface $cacheability): array;

}
