<?php

namespace Drupal\node_temporary;

use DateTime;
use DateTimeZone;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * Queue service for node temporary module.
 */
class NodeTemporaryQueue {

  /**
   * The Entity Type Manager service.
   *
   * @var EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The Queue Factory service.
   *
   * @var QueueFactory
   */
  protected QueueFactory $queue;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    QueueFactory $queue,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->queue = $queue;
  }

  /**
   * Add expired nodes to the queue.
   */
  public function queueExpiredNodes(): void {
    $now = (new DateTime('now', new DateTimeZone('UTC')))
      ->setTime(0, 0, 0)
      ->format('Y-m-d\TH:i:s');

    $storage = $this->entityTypeManager->getStorage('node_temporary');
    $query = $storage->getQuery();
    $entities = $query
      ->condition('date_expire', $now, '<')
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($entities)) {
      $queue = $this->queue->get('node_temporary_delete_expired_nodes_queue');
      foreach ($entities as $id) {
        $queue->createItem(['id' => $id]);
      }
    }
  }

}
