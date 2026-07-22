<?php

/**
 * @file
 * Seeds reproducible demo content: a byline author, categories/tags, and five
 * backdated articles (two general, three documenting this build) with
 * topic-matched hero images fetched from a free image service.
 *
 * Run with drush:  drush scr scripts/seed_demo_content.php
 * Safe to re-run — it clears existing articles/image media first.
 */

use Drupal\Core\File\FileExists;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

$etm = \Drupal::entityTypeManager();

$node_storage = $etm->getStorage('node');
$node_storage->delete($node_storage->loadMultiple(
  \Drupal::entityQuery('node')->condition('type', 'article')->accessCheck(FALSE)->execute()
));
$media_storage = $etm->getStorage('media');
$media_storage->delete($media_storage->loadMultiple(
  \Drupal::entityQuery('media')->condition('bundle', 'image')->accessCheck(FALSE)->execute()
));

$user_storage = $etm->getStorage('user');
$existing_author = $user_storage->loadByProperties(['name' => 'Kingsley A.']);
$author = $existing_author ? reset($existing_author) : $user_storage->create(['name' => 'Kingsley A.', 'status' => 1]);
if (!$existing_author) {
  $author->save();
}
$uid = (int) $author->id();

$term_storage = $etm->getStorage('taxonomy_term');
$ensure_term = function (string $vid, string $name) use ($term_storage) {
  $existing = $term_storage->loadByProperties(['vid' => $vid, 'name' => $name]);
  if ($existing) {
    return reset($existing);
  }
  $term = $term_storage->create(['vid' => $vid, 'name' => $name]);
  $term->save();
  return $term;
};

$fetch_media = function (string $keyword, string $alt, string $slug) {
  $client = \Drupal::httpClient();
  $data = '';
  foreach (["https://loremflickr.com/1200/675/$keyword", 'https://picsum.photos/1200/675'] as $url) {
    try {
      $body = (string) $client->get($url, ['http_errors' => FALSE, 'timeout' => 20])->getBody();
      if (strlen($body) > 2000) {
        $data = $body;
        break;
      }
    }
    catch (\Throwable $e) {
      // Try the next source.
    }
  }
  $file = \Drupal::service('file.repository')->writeData($data, "public://$slug.jpg", FileExists::Replace);
  $media = Media::create([
    'bundle' => 'image',
    'name' => $alt,
    'field_media_image' => ['target_id' => $file->id(), 'alt' => $alt],
  ]);
  $media->save();
  return $media;
};

$articles = [
  [
    'slug' => 'restored-wetlands-coastal-towns',
    'title' => 'How Restored Wetlands Are Bringing Coastal Towns Back to Life',
    'summary' => 'Once drained for development, coastal marshlands are being rebuilt — and the towns behind them are discovering an unexpected line of defense.',
    'body' => '<p>For decades, the salt marshes that once ringed many coastal towns were treated as wasted space — drained, filled, and paved over in the name of progress. Today that logic is being reversed.</p><p>Restored wetlands absorb storm surge, filter runoff, and store carbon at rates that rival young forests. For towns facing rising tides, a healthy marsh has become cheaper and more durable than concrete.</p>',
    'category' => 'Environment',
    'tags' => ['Climate', 'Nature'],
    'keyword' => 'wetland,marsh',
    'date' => '2023-03-14',
    'promote' => FALSE,
  ],
  [
    'slug' => 'independent-coffee-roasters-thriving',
    'title' => 'Why Independent Coffee Roasters Are Quietly Thriving',
    'summary' => 'As the big chains chase automation, small roasters are winning on the one thing that cannot be scaled: relationships.',
    'body' => '<p>Walk down almost any high street and the story seems settled: coffee belongs to the chains. Yet behind that facade, a stubborn ecosystem of independent roasters is not just surviving but growing.</p><p>Their advantage is not price. It is provenance — knowing the farm, roasting in small batches, and adjusting to a bean&rsquo;s character rather than a corporate spec sheet.</p>',
    'category' => 'Food & Drink',
    'tags' => ['Coffee', 'Small Business'],
    'keyword' => 'coffee,roastery',
    'date' => '2023-09-05',
    'promote' => FALSE,
  ],
  [
    'slug' => 'building-decoupled-drupal-nextjs',
    'title' => 'Building a Decoupled News Platform with Drupal 11 and Next.js',
    'summary' => 'A look under the hood of News Line: why we split the CMS from the website, and how the two halves talk to each other.',
    'body' => '<p>News Line runs on two systems that never share a database. Drupal 11 is the editorial backend, and the public site is a separate Next.js application that only ever reads from Drupal over HTTP.</p><p>The content model is defined once, in code, and shipped as installable configuration, so any environment rebuilds the exact same structure with one command.</p>',
    'category' => 'Engineering',
    'tags' => ['Drupal', 'Next.js'],
    'keyword' => 'code,laptop',
    'date' => '2024-06-18',
    'promote' => TRUE,
  ],
  [
    'slug' => 'custom-rest-resource-vs-json-api',
    'title' => 'Why We Wrote a Custom REST Resource Instead of Shipping Raw JSON:API',
    'summary' => 'Out-of-the-box APIs expose your database. We wanted to expose a contract instead.',
    'body' => '<p>Drupal can serve its content over JSON:API with almost no effort, but the output mirrors the internal entity structure. That is convenient for the backend and painful for the frontend.</p><p>So we built a custom REST resource that returns exactly the shape the site needs: renamed fields, ISO timestamps, simple taxonomy objects, and hero images as fully resolved URLs.</p>',
    'category' => 'Engineering',
    'tags' => ['Drupal', 'API'],
    'keyword' => 'programming,software',
    'date' => '2025-03-11',
    'promote' => FALSE,
  ],
  [
    'slug' => 'headless-oauth2-and-isr',
    'title' => 'Going Headless: OAuth2 and Incremental Static Regeneration',
    'summary' => 'How the site stays both secure and fast — authenticating to the API without ever exposing a secret to the browser.',
    'body' => '<p>Because the API lives on a different origin, the frontend authenticates with OAuth2 using the client-credentials grant. The token is fetched and cached on the server, so the browser never sees it.</p><p>Pages are rendered with Incremental Static Regeneration: generated once as static HTML and quietly re-generated in the background, so readers get the speed of a static file with content that stays current.</p>',
    'category' => 'Engineering',
    'tags' => ['Next.js', 'Security'],
    'keyword' => 'server,network',
    'date' => '2026-02-20',
    'promote' => FALSE,
  ],
];

foreach ($articles as $a) {
  $category = $ensure_term('category', $a['category']);
  $tags = array_map(
    fn(string $name) => ['target_id' => $ensure_term('tags', $name)->id()],
    $a['tags'],
  );
  $media = $fetch_media($a['keyword'], $a['title'], $a['slug']);

  Node::create([
    'type' => 'article',
    'title' => $a['title'],
    'field_summary' => $a['summary'],
    'body' => ['value' => $a['body'], 'format' => 'basic_html'],
    'field_category' => ['target_id' => $category->id()],
    'field_tags' => $tags,
    'field_hero_image' => ['target_id' => $media->id()],
    'uid' => $uid,
    'status' => 1,
    'promote' => $a['promote'] ? 1 : 0,
    'created' => strtotime($a['date']),
  ])->save();

  print 'created: ' . $a['title'] . "\n";
}

print "Done.\n";
