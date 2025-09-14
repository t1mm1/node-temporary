<?php

namespace Drupal\node_temporary\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\node_temporary\NodeTemporaryHelper;

/**
 * Hook implementations for the Node Temporary module.
 */
class Entity {

  public function __construct(
    private NodeTemporaryHelper $helper,
  ) {
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if ($entity instanceof NodeInterface) {
      $this->helper->handleTemporaryEntity($entity);
    }
  }

}
