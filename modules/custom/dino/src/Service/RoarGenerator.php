<?php

/**
 * @file
 *
 * @author Jon Kamke <jon@kamke.org>
 */

namespace Drupal\dino\Service;


use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

class RoarGenerator {

  /**
   *
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  private $keyValue;
  /**
   * Use Cache
   *
   * @var bool
   */
  private $useCache;

  /**
   * RoarGenerator constructor.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValue
   * @param bool $useCache
   */
  public function __construct(KeyValueFactoryInterface $keyValue, $useCache) {

    $this->keyValue = $keyValue;
    $this->useCache = $useCache;
  }

  /**
   * Creates the ROAR!
   *
   * @param $length
   * @return string
   */
  public function getRoar($length) {

    $key = 'dino_' . $length;
    $store = $this->keyValue->get('dino');

    if ($store->has($key) && $this->useCache) {
      return $store->get($key);
    }

    sleep(2);

    $string = 'R' . str_repeat('O', $length) . 'AR!';

    if ($this->useCache) {
      $store->set($key, $string);
    }

    return $string;
  }
}