<?php

namespace Drupal\node_temporary\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes expired temporary nodes.
 *
 * @QueueWorker(
 *   id = "node_temporary_process_expired_nodes_queue",
 *   title = @Translation("Node temporary: Process expired temporary nodes"),
 *   cron = {"time" = 86400}
 * )
 */
class ProcessExpiredNodesQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The Entity Type Manager service.
   *
   * @var EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The Logger service.
   *
   * @var LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition
    );

    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $storage = $this->entityTypeManager->getStorage('node_temporary');
    $temporary = $storage->load($data['id']);

    if (empty($temporary)) {
      return;
    }

    $parent = $temporary->get('parent');
    if (!$parent->isEmpty() && $parent->entity) {
      $node = $parent->entity;

      if (!$temporary->get('delete')->isEmpty()) {
        $node->delete();

        $this->logger->get('node_temporary')->notice('Deleted expired node: @label (@nid)', [
          '@label' => $node->label(),
          '@nid' => $node->id(),
        ]);
      }
      else {
        $node->setUnpublished();
        $node->save();

        $this->logger->get('node_temporary')->notice('Unpublish expired node: @label (@nid)', [
          '@label' => $node->label(),
          '@nid' => $node->id(),
        ]);
      }
    }

    $temporary->delete();
  }

}
