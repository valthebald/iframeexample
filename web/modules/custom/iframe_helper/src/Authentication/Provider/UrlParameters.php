<?php

namespace Drupal\touring_wizard\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\NullLockBackend;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\MetadataBag;
use Drupal\Core\Session\UserSession;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\touring_wizard\Entity\WizardJourney;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * URL-based authentication provider.
 */
class UrlParameters implements AuthenticationProviderInterface {

  const PARAMNAME = 'jrnSession';

  use StringTranslationTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * @var \Drupal\Core\Session\MetadataBag
   */
  protected MetadataBag $metadataBag;

  /**
   * Constructs a new cookie authentication provider.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(Connection $connection,
    EntityTypeManagerInterface $entityTypeManager,
    MetadataBag $metadataBag
  ) {
    $this->connection = $connection;
    $this->entityTypeManager = $entityTypeManager;
    $this->metadataBag = $metadataBag;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    return $request->query->has(self::PARAMNAME)
      && $this->paramCorrect($request->query->get(self::PARAMNAME));
  }

  /**
   * Check validity of URL parameter.
   *
   * Parameter should be passed in the form uuid:secret,
   * and secret should match appropriate value in wizard_journey table.
   *
   * @param string $param
   *   Parameter in format uuid:secret.
   *
   * @return bool
   *   TRUE when parameter is valid.
   */
  protected function paramCorrect(string $param) : bool {
    [$uuid, $secret] = explode(':', $param, 2);
    if (!$secret) {
      return FALSE;
    }
    return !empty($this->getJourney($uuid, $secret));
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    [$uuid, $secret] = explode(':', $request->query->get(self::PARAMNAME), 2);
    $journey = $this->getJourney($uuid, $secret);
    if (!$journey instanceof WizardJourney) {
      return NULL;
    }
    // Taken from core's Cookie::getUserFromSession()
    $uid = $journey->getOwnerId();
    if (!$uid) {
      return NULL;
    }
    // @todo Load the User entity in SessionHandler so we don't need queries.
    // @see https://www.drupal.org/node/2345611
    $values = $this->connection
      ->query('SELECT * FROM {users_field_data} [u] WHERE [u].[uid] = :uid AND [u].[default_langcode] = 1',
        [':uid' => $uid])
      ->fetchAssoc();

    // Check if the user data was found and the user is active.
    if (empty($values) || empty($values['status'])) {
      return NULL;
    }
    // Add the user's roles.
    $rids = $this->connection
      ->query('SELECT [roles_target_id] FROM {user__roles} WHERE [entity_id] = :uid', [':uid' => $values['uid']])
      ->fetchCol();
    $values['roles'] = array_merge([AccountInterface::AUTHENTICATED_ROLE], $rids);
    $values[self::PARAMNAME] = $journey;
    $this->metadataBag->setCsrfTokenSeed($journey->get('session_key')->value);

    return new UserSession($values);
  }

  /**
   * Returns the UserSession object for the given session.
   *
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session.
   *
   * @return \Drupal\Core\Session\AccountInterface|null
   *   The UserSession object for the current user, or NULL if this is an
   *   anonymous session.
   */
  protected function getUserFromSession(SessionInterface $session) {
    if ($uid = $session->get('uid')) {
      // @todo Load the User entity in SessionHandler so we don't need queries.
      // @see https://www.drupal.org/node/2345611
      $values = $this->connection
        ->query('SELECT * FROM {users_field_data} [u] WHERE [u].[uid] = :uid AND [u].[default_langcode] = 1', [':uid' => $uid])
        ->fetchAssoc();

      // Check if the user data was found and the user is active.
      if (!empty($values) && $values['status'] == 1) {
        // Add the user's roles.
        $rids = $this->connection
          ->query('SELECT [roles_target_id] FROM {user__roles} WHERE [entity_id] = :uid', [':uid' => $values['uid']])
          ->fetchCol();
        $values['roles'] = array_merge([AccountInterface::AUTHENTICATED_ROLE], $rids);

        return new UserSession($values);
      }
    }

    // This is an anonymous session.
    return NULL;
  }

  /**
   * Get journey entity from its uuid and secret.
   *
   * @param string $uuid
   *   Journey UUID.
   * @param string $key
   *   Journey key.
   *
   * @return \Drupal\touring_wizard\Authentication\Provider\WizardJourney|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getJourney(string $uuid, string $key) : ?WizardJourney {
    $match = $this->entityTypeManager->getStorage('wizard_journey')
      ->loadByProperties(['uuid' => $uuid]);
    if (!$match) {
      return NULL;
    }
    $match = current($match);
    if ($match->get('session_key')->value !== $key) {
      return NULL;
    }
    return $match;
  }

}
