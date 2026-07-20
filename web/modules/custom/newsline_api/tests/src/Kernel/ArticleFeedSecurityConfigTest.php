<?php

declare(strict_types=1);

namespace Drupal\Tests\newsline_api\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the module ships the intended OAuth2 security configuration.
 *
 * These guard against a regression that would silently re-open the endpoint
 * (e.g. reverting the resource to cookie/anonymous auth) or break the mapping
 * from the OAuth2 scope to the feed permission.
 */
#[Group('newsline_api')]
#[RunTestsInSeparateProcesses]
final class ArticleFeedSecurityConfigTest extends ArticleFeedKernelTestBase {

  /**
   * Tests that the REST resource is served only over OAuth2 for GET.
   */
  public function testResourceRequiresOauth2(): void {
    $configuration = $this->container->get('config.factory')
      ->get('rest.resource.article_feed')
      ->get('configuration');

    $this->assertSame(['GET'], $configuration['methods']);
    $this->assertSame(['json'], $configuration['formats']);
    $this->assertSame(['oauth2'], $configuration['authentication']);
  }

  /**
   * Tests that the OAuth2 scope grants exactly the feed permission.
   */
  public function testScopeGrantsFeedPermission(): void {
    $scope = $this->container->get('config.factory')
      ->get('simple_oauth.oauth2_scope.article_feed_read');

    $this->assertSame('article_feed:read', $scope->get('name'));
    $this->assertSame('permission', $scope->get('granularity_id'));
    $this->assertSame(
      'restful get article_feed',
      $scope->get('granularity_configuration.permission'),
    );
    $this->assertArrayHasKey('client_credentials', $scope->get('grant_types'));
  }

}
