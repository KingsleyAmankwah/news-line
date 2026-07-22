<?php

declare(strict_types=1);

namespace Drupal\newsline_api\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\newsline_api\ArticleFeedNormalizerInterface;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves a single published article, including its rendered body, by slug.
 *
 * The slug is the last segment of the article's URL alias (/articles/{slug}),
 * resolved back to a node through the alias manager so the API exposes the same
 * readable identifier the feed advertises in each item's "path".
 */
#[RestResource(
  id: 'article',
  label: new TranslatableMarkup('Article'),
  uri_paths: [
    'canonical' => '/api/article/{slug}',
  ],
)]
final class ArticleResource extends ResourceBase {

  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    protected readonly ArticleFeedNormalizerInterface $normalizer,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AliasManagerInterface $aliasManager,
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
      $container->get('entity_type.manager'),
      $container->get('path_alias.manager'),
    );
  }

  /**
   * Responds to GET requests for a single article.
   *
   * @param string $slug
   *   The article slug (final segment of its /articles/... alias).
   *
   * @return \Drupal\rest\ResourceResponse
   *   The normalized article including its body.
   */
  public function get(string $slug): ResourceResponse {
    $system_path = $this->aliasManager->getPathByAlias('/articles/' . $slug);
    if (!preg_match('#^/node/(\d+)$#', $system_path, $matches)) {
      throw new NotFoundHttpException(sprintf('No article found for "%s".', $slug));
    }

    $node = $this->entityTypeManager->getStorage('node')->load($matches[1]);
    if (
      !$node instanceof NodeInterface
      || $node->bundle() !== 'article'
      || !$node->isPublished()
    ) {
      throw new NotFoundHttpException(sprintf('No article found for "%s".', $slug));
    }

    $cacheability = new CacheableMetadata();
    // The alias-to-node resolution depends on the alias list.
    $cacheability->addCacheTags(['node_list:article']);

    $data = $this->normalizer->normalizeDetail($node, $cacheability);

    $response = new ResourceResponse($data, 200);
    $response->addCacheableDependency($cacheability);

    return $response;
  }

}
