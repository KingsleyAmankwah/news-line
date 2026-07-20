<?php

declare(strict_types=1);

namespace Drupal\newsline_api\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\newsline_api\ArticleFeedNormalizerInterface;
use Drupal\newsline_api\Slugifier;
use Drupal\node\NodeInterface;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\taxonomy\TermInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Serves a paginated, frontend-shaped feed of published articles.
 *
 * Implemented as a core REST resource plugin (ResourceInterface via
 * ResourceBase) rather than a bare controller so that the HTTP method, formats
 * and authentication providers are managed through a rest_resource_config
 * entity. The plugin stays deliberately thin: it validates request input,
 * queries published articles, and delegates all response shaping to the
 * injected normalizer, attaching cache metadata for correct invalidation.
 */
#[RestResource(
  id: 'article_feed',
  label: new TranslatableMarkup('Article feed'),
  uri_paths: [
    'canonical' => '/api/article-feed',
  ],
)]
final class ArticleFeedResource extends ResourceBase {

  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    protected readonly ArticleFeedNormalizerInterface $normalizer,
    protected readonly Slugifier $slugifier,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly RequestStack $requestStack,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.channel.newsline_api'),
      $container->get('newsline_api.article_feed_normalizer'),
      $container->get('newsline_api.slugifier'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('request_stack'),
    );
  }

  /**
   * Responds to GET requests for the article feed.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing a {data, meta, links} envelope.
   */
  public function get(): ResourceResponse {
    $settings = $this->configFactory->get('newsline_api.settings');
    $request = $this->requestStack->getCurrentRequest();

    $page = max(0, (int) $request->query->get('page', 0));
    $items_per_page = (int) $request->query->get('items_per_page', (int) $settings->get('items_per_page_default'));
    $items_per_page = max(1, min($items_per_page, (int) $settings->get('items_per_page_max')));
    $category_slug = trim((string) $request->query->get('category', ''));

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheContexts([
      'url.query_args:page',
      'url.query_args:items_per_page',
      'url.query_args:category',
    ]);
    $cacheability->addCacheTags(['node_list:article']);
    $cacheability->addCacheableDependency($settings);

    // A supplied-but-unknown category yields an empty result set rather than
    // silently returning every article.
    $category_tid = NULL;
    if ($category_slug !== '') {
      $category_tid = $this->resolveCategoryTerm($category_slug, $cacheability);
      if ($category_tid === NULL) {
        return $this->buildResponse([], 0, $page, $items_per_page, $request, $cacheability);
      }
    }

    $node_storage = $this->entityTypeManager->getStorage('node');
    $query = $node_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', NodeInterface::PUBLISHED);
    if ($category_tid !== NULL) {
      $query->condition('field_category', $category_tid);
    }

    $total = (int) (clone $query)->count()->execute();

    // Sort by creation date, then by node ID as a stable tiebreaker so that
    // articles sharing a timestamp keep a deterministic order across pages
    // (otherwise offset-based pagination can duplicate or skip rows).
    $ids = $query
      ->sort('created', 'DESC')
      ->sort('nid', 'DESC')
      ->range($page * $items_per_page, $items_per_page)
      ->execute();

    $data = [];
    foreach ($node_storage->loadMultiple($ids) as $node) {
      if ($node instanceof NodeInterface) {
        $data[] = $this->normalizer->normalize($node, $cacheability);
      }
    }

    return $this->buildResponse($data, $total, $page, $items_per_page, $request, $cacheability);
  }

  /**
   * Resolves a category slug to a term ID within the category vocabulary.
   *
   * The category vocabulary is small, so slug-matching in PHP is acceptable and
   * lets the API expose readable slugs instead of internal term IDs.
   *
   * @return int|null
   *   The matching term ID, or NULL if no category matched the slug.
   */
  private function resolveCategoryTerm(string $slug, CacheableMetadata $cacheability): ?int {
    $cacheability->addCacheTags(['taxonomy_term_list:category']);
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'category']);

    foreach ($terms as $term) {
      if ($term instanceof TermInterface && $this->slugifier->slugify((string) $term->label()) === $slug) {
        $cacheability->addCacheableDependency($term);
        return (int) $term->id();
      }
    }

    return NULL;
  }

  /**
   * Assembles the response envelope and attaches cache metadata.
   *
   * @param array<int, array<string, mixed>> $data
   *   The normalized article payloads.
   * @param int $total
   *   Total number of matching articles across all pages.
   * @param int $page
   *   Zero-based index of the page being returned.
   * @param int $items_per_page
   *   Number of articles returned per page.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request, used to build pagination links.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Accumulated cache metadata to attach to the response.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The assembled feed response.
   */
  private function buildResponse(array $data, int $total, int $page, int $items_per_page, Request $request, CacheableMetadata $cacheability): ResourceResponse {
    $total_pages = (int) ceil($total / $items_per_page);

    $payload = [
      'data' => $data,
      'meta' => [
        'count' => $total,
        'page' => $page,
        'itemsPerPage' => $items_per_page,
        'totalPages' => $total_pages,
      ],
      'links' => $this->buildLinks($request, $page, $items_per_page, $total_pages),
    ];

    $response = new ResourceResponse($payload, 200);
    $response->addCacheableDependency($cacheability);

    return $response;
  }

  /**
   * Builds self/next/prev pagination links preserving active query filters.
   *
   * @return array{self: string, next: string|null, prev: string|null}
   *   The pagination links as relative URLs.
   */
  private function buildLinks(Request $request, int $page, int $items_per_page, int $total_pages): array {
    $link = function (int $target_page) use ($request, $items_per_page): string {
      $query = $request->query->all();
      $query['page'] = $target_page;
      $query['items_per_page'] = $items_per_page;

      return $request->getPathInfo() . '?' . http_build_query($query);
    };

    return [
      'self' => $link($page),
      'next' => ($page + 1) < $total_pages ? $link($page + 1) : NULL,
      'prev' => $page > 0 ? $link($page - 1) : NULL,
    ];
  }

}
