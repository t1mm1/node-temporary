<?php

namespace Drupal\node_temporary\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node_temporary\NodeTemporaryQueue;

/**
 * Generic cron hook implementation for the Node Temporary module.
 */
class Cron {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    private NodeTemporaryQueue $nodeTemporaryQueue,
  ) {
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->nodeTemporaryQueue->queueExpiredNodes();
  }

}
