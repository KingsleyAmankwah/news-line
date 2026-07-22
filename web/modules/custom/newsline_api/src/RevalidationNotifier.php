<?php

declare(strict_types=1);

namespace Drupal\newsline_api;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Notifies the decoupled frontend to revalidate its cached article content.
 *
 * When an article changes in Drupal, the frontend's Incremental Static
 * Regeneration cache would otherwise only refresh after its time window. This
 * service pings a frontend webhook so the change is reflected immediately. It
 * fails safe: if the endpoint is not configured it does nothing, and any
 * network error is logged rather than allowed to interrupt the content save.
 */
final class RevalidationNotifier {

  public function __construct(
    protected readonly ClientInterface $httpClient,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * Pings the frontend revalidation endpoint, if one is configured.
   */
  public function notify(): void {
    $config = $this->configFactory->get('newsline_api.settings');
    $endpoint = trim((string) $config->get('revalidation_endpoint'));
    $secret = (string) $config->get('revalidation_secret');

    // The feature is opt-in per environment; stay silent when unconfigured.
    if ($endpoint === '' || $secret === '') {
      return;
    }

    try {
      $this->httpClient->request('POST', $endpoint, [
        'headers' => ['X-Revalidate-Secret' => $secret],
        'timeout' => 5,
      ]);
    }
    catch (\Throwable $e) {
      $this->logger->warning('Frontend revalidation request failed: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
