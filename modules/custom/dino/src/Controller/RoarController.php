<?php

namespace Drupal\dino\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dino\Service\RoarGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RoarController.
 *
 * @package Drupal\dino\Controller
 */
class RoarController extends ControllerBase {

  /**
   * Roar Generator service.
   *
   * @var \Drupal\dino\Service\RoarGenerator
   */
  private $roarGenerator;

  /**
   * RoarController constructor.
   *
   * @param \Drupal\dino\Service\RoarGenerator $roarGenerator
   */
  public function __construct(RoarGenerator $roarGenerator) {

    $this->roarGenerator = $roarGenerator;
  }

  /**
   * Dependency Injection.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @return static
   */
  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('dino.roar_generator')
    );
  }

  /**
   * Say.
   *
   * @param int $roar
   * @return string
   *   Return Hello string.
   */
  public function says($roar) {
    return [
      '#type' => 'markup',
      '#markup' => $this->t($this->roarGenerator->getRoar($roar)),
    ];
  }
}
