<?php

namespace Drupal\node_temporary\ListBuilder;

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Class to build a listing of temporaries nodes list.
 */
class NodeTemporaryListBuilder extends EntityListBuilder {

  /**
   * Page limit.
   */
  protected $limit = 50;

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['parent'] = $this->t('Node');
    $header['uid'] = $this->t('User');
    $header['option'] = $this->t('Option');
    $header['when'] = $this->t('When');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row['id'] = $entity->id();
    $row['parent'] = $this->getNodeLink($entity);
    $row['uid'] = $this->getProfileLink($entity);
    $row['option'] = $this->getOptionValue($entity);
    $row['when'] = $entity->getFormattedExpire();

    return $row + parent::buildRow($entity);
  }

  /**
   * Help function for getting parent Node link.
   *
   * @param EntityInterface $entity
   *   The item list entity.
   *
   * @return Link|TranslatableMarkup|null
   *   Link to node.
   */
  public function getNodeLink(EntityInterface $entity): Link|TranslatableMarkup|null {
    $node = $entity->get('parent')->entity;
    if ($node) {
      $link = Link::fromTextAndUrl(
        $node->label(),
        $node->toUrl(),
      );
    }
    else {
      $link = $this->t('Node was removed.');
    }

    return $link;
  }

  /**
   * Help function for getting owner profile link.
   *
   * @param EntityInterface $entity
   *   The item list entity.
   *
   * @return Link|null
   *   Link to profile.
   */
  public function getProfileLink(EntityInterface $entity): Link|null {
    $user = $entity->getUser();
    if ($user) {
      $link = $user->toLink($user->getDisplayName());
    }
    else {
      $link = $this->t('[User was removed]');
    }

    return $link;
  }

  /**
   * Help function for getting value of options.
   *
   * @param EntityInterface $entity
   *   The item list entity.
   *
   * @return string
   *   Bool value.
   */
  public function getOptionValue(EntityInterface $entity): string {
    $value = $this->t('Unpublish');

    if ($entity->get('delete')->value) {
      $value = $this->t('Delete');
    }

    return $value;
  }

}
