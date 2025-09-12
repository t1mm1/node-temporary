<?php

namespace Drupal\node_temporary\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node_temporary\NodeTemporaryHelper;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Generic cron hook implementation for the Node Temporary module.
 */
class Cron {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    private ConfigFactoryInterface $configFactory,
    private RequestStack $requestStack,
    private MessengerInterface $messenger,
    private NodeTemporaryHelper $nodeTemporaryHelper,
  ) {
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('cron')]
  public function cron(): void {
    // TODO: Unpublish by cron.
    // If date > expire.
    // TODO: remove by cron.
    // If date  expire + 15 days.
  }

}
