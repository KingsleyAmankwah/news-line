<?php

declare(strict_types=1);

namespace Drupal\Tests\newsline_api\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\newsline_api\ArticleFeedNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the normalizer shapes articles into the API contract.
 */
#[CoversClass(ArticleFeedNormalizer::class)]
#[Group('newsline_api')]
#[RunTestsInSeparateProcesses]
final class ArticleFeedNormalizerTest extends ArticleFeedKernelTestBase {

  /**
   * The normalizer under test.
   */
  private ArticleFeedNormalizer $normalizer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->normalizer = $this->container->get('newsline_api.article_feed_normalizer');
  }

  /**
   * Tests the full shape of a fully-populated article, including the hero.
   */
  public function testNormalizeFullyPopulatedArticle(): void {
    $category = $this->createTerm('category', 'Engineering');
    $tag = $this->createTerm('tags', 'Drupal');
    $media = $this->createHeroMedia('The hero alt text');

    $article = $this->createArticle([
      'title' => 'Decoupled Drupal',
      'field_summary' => 'Shaping a custom feed.',
      'field_category' => ['target_id' => $category->id()],
      'field_tags' => [['target_id' => $tag->id()]],
      'field_hero_image' => ['target_id' => $media->id()],
      'promote' => TRUE,
    ]);

    $cacheability = new CacheableMetadata();
    $result = $this->normalizer->normalize($article, $cacheability);

    // Scalars and renamed keys.
    $this->assertSame($article->uuid(), $result['id']);
    $this->assertSame('article', $result['type']);
    $this->assertSame('Decoupled Drupal', $result['title']);
    $this->assertSame('Shaping a custom feed.', $result['summary']);
    $this->assertTrue($result['promoted']);
    $this->assertFalse($result['sticky']);
    // 300 words at the default 200 wpm rounds up to 2 minutes.
    $this->assertSame(2, $result['readingTimeMinutes']);
    // ISO 8601 timestamps.
    $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $result['publishedAt']);

    // Author, category, tags become {id, name, slug} objects.
    $this->assertSame('reporter', $result['author']['name']);
    $this->assertSame('engineering', $result['category']['slug']);
    $this->assertSame('Engineering', $result['category']['name']);
    $this->assertCount(1, $result['tags']);
    $this->assertSame('drupal', $result['tags'][0]['slug']);

    // Hero resolves to absolute image-style URLs.
    $this->assertSame('The hero alt text', $result['hero']['alt']);
    $this->assertStringContainsString('/styles/feed_hero/', $result['hero']['src']);
    $this->assertStringContainsString('/styles/feed_thumbnail/', $result['hero']['thumbnail']);
    $this->assertIsInt($result['hero']['width']);
  }

  /**
   * Tests that an article with no hero, category, or tags degrades cleanly.
   */
  public function testNormalizeSparseArticle(): void {
    $article = $this->createArticle(['title' => 'Bare article']);

    $result = $this->normalizer->normalize($article, new CacheableMetadata());

    $this->assertNull($result['hero']);
    $this->assertNull($result['category']);
    $this->assertSame([], $result['tags']);
    $this->assertSame('Bare article', $result['title']);
  }

  /**
   * Tests that the detail normalization adds the rendered body.
   */
  public function testNormalizeDetailIncludesRenderedBody(): void {
    $article = $this->createArticle([
      'title' => 'With body',
      'body' => ['value' => 'Hello world body text.', 'format' => 'plain_text'],
    ]);

    $result = $this->normalizer->normalizeDetail($article, new CacheableMetadata());

    // Retains the feed shape...
    $this->assertSame('With body', $result['title']);
    $this->assertArrayHasKey('summary', $result);
    // ...and adds the rendered body.
    $this->assertArrayHasKey('body', $result);
    $this->assertStringContainsString('Hello world body text.', $result['body']);
  }

  /**
   * Tests that normalization collects cache metadata for its dependencies.
   */
  public function testNormalizeCollectsCacheMetadata(): void {
    $category = $this->createTerm('category', 'Engineering');
    $article = $this->createArticle([
      'title' => 'Cached',
      'field_category' => ['target_id' => $category->id()],
    ]);

    $cacheability = new CacheableMetadata();
    $this->normalizer->normalize($article, $cacheability);

    $tags = $cacheability->getCacheTags();
    $this->assertContains('node:' . $article->id(), $tags);
    $this->assertContains('taxonomy_term:' . $category->id(), $tags);
    $this->assertContains('config:newsline_api.settings', $tags);
  }

}
