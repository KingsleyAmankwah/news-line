<?php

declare(strict_types=1);

namespace Drupal\Tests\newsline_api\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\newsline_api\RevalidationNotifier;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests the frontend revalidation notifier.
 */
#[CoversClass(RevalidationNotifier::class)]
#[Group('newsline_api')]
final class RevalidationNotifierTest extends UnitTestCase {

  /**
   * Builds a config factory returning the given endpoint and secret.
   *
   * @param array<string, string> $values
   *   The newsline_api.settings values to expose.
   */
  private function configFactory(array $values): ConfigFactoryInterface {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(
      fn(string $key) => $values[$key] ?? NULL,
    );
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('newsline_api.settings')->willReturn($config);

    return $factory;
  }

  /**
   * Tests that a configured notifier posts to the endpoint with the secret.
   */
  public function testNotifiesWhenConfigured(): void {
    $client = $this->createMock(ClientInterface::class);
    $client->expects($this->once())
      ->method('request')
      ->with(
        'POST',
        'https://front.example/api/revalidate',
        $this->callback(
          fn(array $options) => ($options['headers']['X-Revalidate-Secret'] ?? NULL) === 's3cret',
        ),
      );

    $notifier = new RevalidationNotifier(
      $client,
      $this->configFactory([
        'revalidation_endpoint' => 'https://front.example/api/revalidate',
        'revalidation_secret' => 's3cret',
      ]),
      $this->createMock(LoggerInterface::class),
    );

    $notifier->notify();
  }

  /**
   * Tests that nothing is sent when the endpoint or secret is missing.
   */
  public function testSkipsWhenUnconfigured(): void {
    $client = $this->createMock(ClientInterface::class);
    $client->expects($this->never())->method('request');

    $notifier = new RevalidationNotifier(
      $client,
      $this->configFactory(['revalidation_endpoint' => '', 'revalidation_secret' => '']),
      $this->createMock(LoggerInterface::class),
    );

    $notifier->notify();
  }

  /**
   * Tests that a network failure is logged and swallowed.
   */
  public function testLogsAndSwallowsFailure(): void {
    $client = $this->createMock(ClientInterface::class);
    $client->method('request')->willThrowException(
      new ConnectException('down', $this->createMock(RequestInterface::class)),
    );

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('warning');

    $notifier = new RevalidationNotifier(
      $client,
      $this->configFactory([
        'revalidation_endpoint' => 'https://front.example/api/revalidate',
        'revalidation_secret' => 's3cret',
      ]),
      $logger,
    );

    // Must not throw.
    $notifier->notify();
  }

}
