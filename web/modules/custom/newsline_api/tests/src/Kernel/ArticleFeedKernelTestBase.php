<?php

declare(strict_types=1);

namespace Drupal\Tests\newsline_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\filter\Entity\FilterFormat;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\user\Entity\User;

/**
 * Base class providing the Article content model for News Line kernel tests.
 */
abstract class ArticleFeedKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'options',
    'node',
    'taxonomy',
    'file',
    'image',
    'media',
    'views',
    'media_library',
    'path',
    'path_alias',
    'token',
    'pathauto',
    'serialization',
    'rest',
    'consumers',
    'simple_oauth',
    'newsline_core',
    'newsline_api',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('path_alias');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig([
      'system',
      'field',
      'filter',
      'node',
      'user',
      'taxonomy',
      'image',
      'media',
      'pathauto',
      'simple_oauth',
      'newsline_core',
      'newsline_api',
    ]);

    if (!FilterFormat::load('plain_text')) {
      FilterFormat::create(['format' => 'plain_text', 'name' => 'Plain text'])->save();
    }

    // The first user (uid 1) is the superuser and bypasses node access, so
    // access-checked queries in the resource return the seeded content.
    $author = User::create(['name' => 'reporter', 'status' => 1]);
    $author->save();
    $this->container->get('current_user')->setAccount($author);
  }

  /**
   * Creates a taxonomy term in the given vocabulary.
   */
  protected function createTerm(string $vid, string $name): TermInterface {
    $term = Term::create(['vid' => $vid, 'name' => $name]);
    $term->save();
    return $term;
  }

  /**
   * Creates an image media entity from Drupal's bundled Druplicon image.
   */
  protected function createHeroMedia(string $alt = 'Hero alt'): MediaInterface {
    $data = file_get_contents($this->root . '/core/misc/druplicon.png');
    $file = $this->container->get('file.repository')
      ->writeData($data, 'public://test-hero.png');

    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Test hero',
      'field_media_image' => ['target_id' => $file->id(), 'alt' => $alt],
    ]);
    $media->save();

    return $media;
  }

  /**
   * Creates a published article node.
   *
   * @param array<string, mixed> $values
   *   Field values to merge over sensible defaults.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved article.
   */
  protected function createArticle(array $values = []): NodeInterface {
    $node = Node::create($values + [
      'type' => 'article',
      'title' => 'Untitled',
      'field_summary' => 'A teaser.',
      'body' => ['value' => str_repeat('word ', 300), 'format' => 'plain_text'],
      'status' => NodeInterface::PUBLISHED,
    ]);
    $node->save();

    return $node;
  }

}
