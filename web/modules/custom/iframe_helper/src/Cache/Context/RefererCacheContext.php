<?php

namespace Drupal\iframe_helper\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\RequestStackCacheContextBase;

/**
 * Defines the Referer cache context.
 *
 * Cache context ID: 'referer'.
 */
class RefererCacheContext extends RequestStackCacheContextBase {

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Referer');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    $referer = $this->requestStack->getCurrentRequest()->headers->get('referer');
    if (!$referer) {
      return '';
    }
    return parse_url($referer, PHP_URL_HOST);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
