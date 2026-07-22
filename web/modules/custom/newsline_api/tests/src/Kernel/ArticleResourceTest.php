<?php

declare(strict_types=1);

namespace Drupal\Tests\newsline_api\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests the single-article REST resource.
 */
#[Group('newsline_api')]
#[RunTestsInSeparateProcesses]
final class ArticleResourceTest extends ArticleFeedKernelTestBase {

  /**
   * Resolves the pathauto-generated slug for a node.
   */
  private function slugForNode(int $nid): string {
    $alias = $this->container->get('path_alias.manager')
      ->getAliasByPath('/node/' . $nid);
    $this->assertStringStartsWith('/articles/', $alias, 'Article received a pathauto alias.');

    return substr($alias, strlen('/articles/'));
  }

  /**
   * Tests fetching a published article by its slug, including the body.
   */
  public function testReturnsArticleBySlug(): void {
    $article = $this->createArticle([
      'title' => 'Detail Page',
      'body' => ['value' => 'The full article text.', 'format' => 'plain_text'],
    ]);
    $slug = $this->slugForNode((int) $article->id());

    $resource = $this->container->get('plugin.manager.rest')->createInstance('article');
    $payload = $resource->get($slug)->getResponseData();

    $this->assertSame('Detail Page', $payload['title']);
    $this->assertArrayHasKey('body', $payload);
    $this->assertStringContainsString('The full article text.', $payload['body']);
  }

  /**
   * Tests that an unknown slug results in a 404.
   */
  public function testUnknownSlugThrowsNotFound(): void {
    $resource = $this->container->get('plugin.manager.rest')->createInstance('article');

    $this->expectException(NotFoundHttpException::class);
    $resource->get('no-such-article');
  }

  /**
   * Tests that an unpublished article is not exposed by slug.
   */
  public function testUnpublishedArticleIsNotFound(): void {
    $article = $this->createArticle([
      'title' => 'Draft Detail',
      'status' => 0,
    ]);
    $slug = $this->slugForNode((int) $article->id());

    $resource = $this->container->get('plugin.manager.rest')->createInstance('article');

    $this->expectException(NotFoundHttpException::class);
    $resource->get($slug);
  }

}
