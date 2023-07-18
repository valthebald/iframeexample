<?php

namespace Drupal\iframe_helper\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\iframe_helper\Form\AllowedFrameReferers;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Allows Salesforce to use this site via an iframe.
 *
 * List of allowed domains can be configured at /admin/touring/allowed-iframes.
 */
class AllowIframeSubscriber implements EventSubscriberInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Allowed referers configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Constructs a WebformShareEventSubscriber object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(RouteMatchInterface $route_match,
    ConfigFactoryInterface $configFactory) {
    $this->routeMatch = $route_match;
    $this->config = $configFactory->get(AllowedFrameReferers::CONFIG_NAME);

  }

  /**
   * Remove 'X-Frame-Options' from the response header for shared webforms.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event) {
    $response = $event->getResponse();
    $cacheable = $response instanceof CacheableResponseInterface;
    if ($cacheable) {
      $meta = new CacheableMetadata();
      $meta->addCacheContexts(['referer']);
      $response->addCacheableDependency($meta);
    }

    $referer = $event->getRequest()->headers->get('referer');
    if (!$referer) {
      return;
    }
    if ($cacheable) {
      $response->addCacheableDependency($this->config);
    }
    $allowedReferrers = $this->config->get('allowed_domains');
    if (!$allowedReferrers) {
      return;
    }
    $host = parse_url($referer, PHP_URL_HOST);
    $hostAllowed = FALSE;
    foreach (preg_split('/[\r\n]{1,2}/', $allowedReferrers) as $pattern) {
      if (preg_match('/' . $pattern . '/', $host)) {
        $hostAllowed = TRUE;
      }
    }
    if ($hostAllowed) {
      $response->headers->remove('X-Frame-Options');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE] = ['onResponse'];
    return $events;
  }

}
