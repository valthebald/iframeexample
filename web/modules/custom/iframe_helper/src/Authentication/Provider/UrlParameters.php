<?php

namespace Drupal\iframe_helper\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\NullLockBackend;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\MetadataBag;
use Drupal\Core\Session\UserSession;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\iframe_helper\Entity\ParamSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * URL-based authentication provider.
 */
class UrlParameters implements AuthenticationProviderInterface {

  const PARAMNAME = 'iSession';

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
   * and secret should match appropriate value in iframe_session table.
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
    return !empty($this->getSession($uuid, $secret));
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    [$uuid, $secret] = explode(':', $request->query->get(self::PARAMNAME), 2);
    $session = $this->getSession($uuid, $secret);
    if (!$session instanceof ParamSession) {
      return NULL;
    }
    // Taken from core's Cookie::getUserFromSession()
    $uid = $session->getOwnerId();
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
    $values[self::PARAMNAME] = $session;
    $this->metadataBag->setCsrfTokenSeed($session->get('session_key')->value);

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
   * Get session entity from its uuid and secret.
   *
   * @param string $uuid
   *   session UUID.
   * @param string $key
   *   session key.
   *
   * @return \Drupal\iframe_helper\Entity\ParamSession|null
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getSession(string $uuid, string $key) : ?ParamSession {
    $match = $this->entityTypeManager->getStorage('iframe_session')
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
