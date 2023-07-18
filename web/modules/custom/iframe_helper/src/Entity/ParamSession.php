<?php

namespace Drupal\iframe_helper\Entity;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the URL session entity.
 *
 * @ContentEntityType(
 *   id = "iframe_session",
 *   label = @Translation("Session"),
 *   label_singular = @Translation("session"),
 *   label_plural = @Translation("sessions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count session",
 *     plural = "@count sessions",
 *   ),
 *   base_table = "iframe_session",
 *   internal = FALSE,
 *   fieldable = FALSE,
 *   entity_keys = {
 *     "id" = "sid",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "owner" = "owner",
 *   },
 *   handlers={
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "permission_provider" = "Drupal\entity\EntityPermissionProvider",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *       "permissions" = "Drupal\user\Entity\EntityPermissionsRouteProviderWithCheck",
 *     }
 *   },
 * )
 */
class ParamSession extends ContentEntityBase implements EntityOwnerInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('Session creation time.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('Time of the last update of the session.'))
      ->setTranslatable(TRUE);

    $fields['uuid']->setLabel(t('Journey ID'));
    $fields['session_key'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Session key'))
      ->setRequired(TRUE)
      ->setReadOnly(TRUE)
      ->setSetting('max_length', 255);
    $fields['data'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Data'))
      ->setDescription(t('A serialized array of data.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getData(): array {
    return $this->get('data')->getValue()[0] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setData(array $data): self {
    $this->set('data', $data);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSessionKey(): string {
    return $this->get('session_key')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    if (empty($values['session_key'])) {
      $values['session_key'] = Crypt::randomBytesBase64(32);
    }
    parent::preCreate($storage, $values);
  }

}
