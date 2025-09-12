<?php

namespace Drupal\node_temporary;

use DateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\node_temporary\Entity\NodeTemporaryEntity;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Helper service for node temporary module.
 */
class NodeTemporaryHelper {

  /**
   * @var EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The usage url service.
   *
   * @var MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * The current user.
   *
   * @var AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The current request.
   *
   * @var RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The config service.
   *
   * @var ConfigFactoryInterface
   *   The config service.
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger,
    AccountInterface $current_user,
    RequestStack $request_stack,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->configFactory = $config_factory;
  }

  /**
   * Handle creation/update/remove of NodeTemporaryEntity.
   *
   * @var NodeInterface $node
   *   The node entity.
   * @var bool $temporary
   *    The checkbox value.
   * @var bool $update
   *    The update date value.
   */
  public function handleTemporaryEntity(NodeInterface $node, bool $temporary, bool $update = FALSE): void {
    $entity = $this->getTemporaryEntity($node);

    if ($temporary) {
      $format = 'Y-m-d\TH:i:s';
      $expire = new DateTime();
      $bundles = $this->getSettingsBundles();
      $expire_days = $bundles[$node->getType()]['expire_days'];
      $expire->modify('+' . $expire_days . ' days');

      // Update expire date.
      if ($entity && $update) {
        $entity->set('date_expire', $expire->format($format));
        $entity->save();
      }

      // Create new node temporary entity.
      if (!$entity) {
        $entity = NodeTemporaryEntity::create([
          'parent' => $node->id(),
          'date_expire' => $expire->format($format),
        ]);
        $entity->save();
      }
    }
    else {
      if ($entity) {
        $entity->delete();
      }
    }
  }

  /**
   * Get NodeTemporaryEntity by node.
   *
   * @var NodeInterface $node
   *   The node entity.
   * @return NodeTemporaryEntity|NULL
   *   Node Temporary entity or null.
   */
  public function getTemporaryEntity(NodeInterface $node): ?NodeTemporaryEntity {
    try {
      $entities = $this->entityTypeManager
        ->getStorage('node_temporary')
        ->loadByProperties([
          'parent' => $node->id(),
        ]);
    }
    catch (\Exception) {
      return NULL;
    }

    if (empty($entities) || !is_array($entities)) {
      return NULL;
    }

    return reset($entities);
  }

  /**
   * Help function show message.
   *
   * @var NodeInterface $node
   *   The processing node.
   * @var bool|null $is_processing_input
   *   The form state processing.
   */
  public function setMessage(NodeInterface $node, ?bool $is_processing_input): void {
    if ($is_processing_input === NULL) {
      $is_processing_input = $this->requestStack->getCurrentRequest()->getMethod() !== 'GET';
    }

    if (!$is_processing_input) {
      $message = $this->getMessage($node);
      if (!empty($message)) {
        $this->messenger->addStatus(Markup::create($message));
      }
    }
  }

  /**
   * Help function to get message text.
   *
   * @var NodeInterface $node
   *   The processing node.
   * @return string|false
   *   The message text.
   */
  public function getMessage(NodeInterface $node): string|false {
    $temporary = $this->getTemporaryEntity($node);

    if (empty($temporary)) {
      return FALSE;
    }

    if ($this->isOwner($temporary)) {
      return t('You have marked the node as temporary.<br />It will expire on <strong>@date</strong>.', [
        '@date' => $temporary->getFormattedExpire(),
      ]);
    }
    else {
      return t('@user has marked the node as temporary.<br />It will expire on <strong>@date</strong>.', [
        '@user' => $temporary->getUser()->getAccountName(),
        '@date' => $temporary->getFormattedExpire(),
      ]);
    }
  }

  /**
   * Help function for check is user owner or not.
   *
   * @var NodeTemporaryEntity $node
   *   The processing node.
   */
  public function isOwner(NodeTemporaryEntity $temporary): bool {
    $owner = $temporary->getUser();
    if (empty($owner)) {
      return FALSE;
    }

    if ($owner->id() === $this->currentUser->id()) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Help function for getting bundles from configs.
   *
   * @return array
   *   The bundles list.
   */
  public function getSettingsBundles(): array {
    return $this->configFactory->get('node_temporary.settings')->get('bundles');
  }

  /**
   * Help function for getting flag is bundle enable or not.
   *
   * @return bool
   *   The flag is service enable or not (with node bundle checking).
   */
  public function isEnabled(string $bundle = ''): bool {
    if (!$bundle) {
      return $this->configFactory->get('node_temporary.settings')->get('enabled');
    }

    $bundles = $this->getSettingsBundles();
    if (empty($bundles)) {
      return FALSE;
    }

    return !empty($bundles[$bundle]['enabled']);
  }

}
