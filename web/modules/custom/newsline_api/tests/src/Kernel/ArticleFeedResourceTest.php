<?php

declare(strict_types=1);

namespace Drupal\Tests\newsline_api\Kernel;

use Drupal\node\NodeInterface;
use Drupal\rest\ResourceResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Article Feed REST resource envelope, paging, filtering and caching.
 */
#[Group('newsline_api')]
#[RunTestsInSeparateProcesses]
final class ArticleFeedResourceTest extends ArticleFeedKernelTestBase {

  /**
   * Executes the resource's GET handler and returns the full response.
   *
   * The query is applied to the request already on the stack rather than
   * pushing a new one, so the request keeps the session the kernel booted with.
   *
   * @param array<string, mixed> $query
   *   Query-string parameters.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The resource response.
   */
  private function getFeedResponse(array $query = []): ResourceResponse {
    $request = $this->container->get('request_stack')->getCurrentRequest();
    $request->query->replace($query);
    $resource = $this->container->get('plugin.manager.rest')->createInstance('article_feed');

    return $resource->get();
  }

  /**
   * Executes the resource's GET handler and returns the decoded payload.
   *
   * @param array<string, mixed> $query
   *   Query-string parameters.
   *
   * @return array<string, mixed>
   *   The decoded response payload.
   */
  private function getFeed(array $query = []): array {
    return $this->getFeedResponse($query)->getResponseData();
  }

  /**
   * Tests the response envelope structure and metadata.
   */
  public function testEnvelopeAndMeta(): void {
    $this->createArticle(['title' => 'One']);
    $this->createArticle(['title' => 'Two']);

    $payload = $this->getFeed();

    $this->assertArrayHasKey('data', $payload);
    $this->assertArrayHasKey('meta', $payload);
    $this->assertArrayHasKey('links', $payload);
    $this->assertSame(2, $payload['meta']['count']);
    $this->assertSame(0, $payload['meta']['page']);
    $this->assertSame(1, $payload['meta']['totalPages']);
    $this->assertNull($payload['links']['prev']);
  }

  /**
   * Tests that pagination is stable and never duplicates or skips rows.
   *
   * Regression test: articles sharing a creation timestamp must keep a
   * deterministic order across pages via the node-ID tiebreaker.
   */
  public function testPaginationIsStableAcrossPages(): void {
    $created = 1_700_000_000;
    for ($i = 1; $i <= 5; $i++) {
      $this->createArticle(['title' => "Article $i", 'created' => $created]);
    }

    $page0 = $this->getFeed(['page' => 0, 'items_per_page' => 2]);
    $page1 = $this->getFeed(['page' => 1, 'items_per_page' => 2]);
    $page2 = $this->getFeed(['page' => 2, 'items_per_page' => 2]);

    $ids = array_merge(
      array_column($page0['data'], 'id'),
      array_column($page1['data'], 'id'),
      array_column($page2['data'], 'id'),
    );

    $this->assertCount(5, $ids);
    $this->assertCount(5, array_unique($ids), 'Every article appears exactly once across pages.');
    $this->assertSame(5, $page0['meta']['count']);
    $this->assertSame(3, $page0['meta']['totalPages']);
  }

  /**
   * Tests that items_per_page is clamped to the configured maximum.
   */
  public function testItemsPerPageIsClamped(): void {
    $payload = $this->getFeed(['items_per_page' => 999]);
    $this->assertSame(50, $payload['meta']['itemsPerPage']);

    $payload = $this->getFeed(['items_per_page' => 0]);
    $this->assertSame(1, $payload['meta']['itemsPerPage']);
  }

  /**
   * Tests that unpublished articles are excluded from the feed.
   */
  public function testUnpublishedArticlesAreExcluded(): void {
    $this->createArticle(['title' => 'Published']);
    $this->createArticle(['title' => 'Draft', 'status' => NodeInterface::NOT_PUBLISHED]);

    $payload = $this->getFeed();

    $this->assertSame(1, $payload['meta']['count']);
    $this->assertSame('Published', $payload['data'][0]['title']);
  }

  /**
   * Tests the category filter, including the unknown-slug empty result.
   */
  public function testCategoryFilter(): void {
    $engineering = $this->createTerm('category', 'Engineering');
    $design = $this->createTerm('category', 'Design');
    $this->createArticle(['title' => 'Eng', 'field_category' => ['target_id' => $engineering->id()]]);
    $this->createArticle(['title' => 'Des', 'field_category' => ['target_id' => $design->id()]]);

    $engineering_feed = $this->getFeed(['category' => 'engineering']);
    $this->assertSame(1, $engineering_feed['meta']['count']);
    $this->assertSame('Eng', $engineering_feed['data'][0]['title']);

    $unknown_feed = $this->getFeed(['category' => 'nonexistent']);
    $this->assertSame(0, $unknown_feed['meta']['count']);
    $this->assertSame([], $unknown_feed['data']);
  }

  /**
   * Tests that the response carries the expected cache metadata.
   */
  public function testResponseCacheMetadata(): void {
    $this->createArticle(['title' => 'Cached']);

    $response = $this->getFeedResponse();

    $metadata = $response->getCacheableMetadata();
    $this->assertContains('node_list:article', $metadata->getCacheTags());
    $this->assertContains('url.query_args:page', $metadata->getCacheContexts());
    $this->assertContains('url.query_args:category', $metadata->getCacheContexts());
  }

}
