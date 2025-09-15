<?php

namespace Drupal\node_temporary\Entity;

use DateTimeZone;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * The property entity.
 *
 * @ContentEntityType(
 *   id = "node_temporary",
 *   label = @Translation("Node temporary"),
 *   label_collection = @Translation("Temporary nodes list"),
 *   base_table = "node_temporary",
 *   data_table = "node_temporary_field_data",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *   },
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\node_temporary\ListBuilder\NodeTemporaryListBuilder",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "route_provider" = {
 *        "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *      },
 *   },
 *   links = {
 *     "delete-form" = "/admin/structure/node-temporary/list/{node_temporary}/delete",
 *     "collection" = "/admin/structure/node-temporary/list",
 *   },
 *   admin_permission = "administer content",
 *   translatable = TRUE,
 * )
 */
class NodeTemporaryEntity extends ContentEntityBase implements EntityOwnerInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = [];

    $fields[$entity_type->getKey('id')] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('Entity ID.'));

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID.'))
      ->setReadOnly(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The author of the entity.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getCurrentUserId');

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code of the entity.'))
      ->setDisplayOptions('form', [
        'type' => 'language_select',
        'weight' => 100,
      ]);

    $fields['parent'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Node'))
      ->setDescription(t('The parent node entity.'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'hidden',
        'weight' => -5,
      ]);

    $fields['date_expire'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Expire date'))
      ->setDescription(t('Datetime when content should be unpublished or deleted.'))
      ->setSettings([
        'datetime_type' => 'datetime',
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 10,
        'settings' => [
          'datetime_type' => 'date',
        ],
      ]);

    $fields['delete'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Delete temporary node'))
      ->setDescription(t('A flag to delete temporary parent node.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 101,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The created datetime.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The changed datetime.'));

    return $fields;
  }

  /**
   * Returns the current user id for default value callbacks.
   */
  public static function getCurrentUserId(): array {
    return [\Drupal::currentUser()->id()];
  }

  /**
   * Return user entity.
   *
   * @return \Drupal\user\UserInterface|null
   *   User entity.
   */
  public function getUser(): ?UserInterface {
    $user = $this->get('uid')->entity;
    return ($user instanceof UserInterface) ? $user : NULL;
  }

  /**
   * Gets the creation timestamp.
   *
   * @return int
   *   Creation timestamp of the entity.
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * Help function for formatting date related to user timezone.
   *
   * @param string $format
   *   The date format.
   *
   * @return string
   *   The formated date.
   * @throws \DateInvalidTimeZoneException
   */
  public function getFormattedExpire(string $format = 'd.m.Y'): string {
    return (new DrupalDateTime($this->get('date_expire')->value, 'UTC'))->setTimezone(new DateTimeZone(
      \Drupal::currentUser()->getTimeZone() ?: 'UTC'
    ))->format($format);
  }

  /**
   * Help function for getting date in timestamp.
   *
   * @return string
   *   The timestamp.
   * @throws \DateInvalidTimeZoneException
   */
  public function getTimestampExpire(): string {
    $date = DrupalDateTime::createFromFormat('d.m.Y', $this->getFormattedExpire());
    return $date->getTimestamp();
  }

}
