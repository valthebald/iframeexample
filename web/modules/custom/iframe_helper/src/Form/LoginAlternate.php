<?php

namespace Drupal\iframe_helper\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\iframe_helper\Authentication\Provider\UrlParameters;
use Drupal\user\Form\UserLoginForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoginAlternate extends UserLoginForm {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    /** @var \Drupal\Core\Session\AccountInterface $session */
    if (empty($uid = $form_state->get('uid'))) {
      return;
    }
    $account = $this->userStorage->load($uid);

    $sessionStorage = $this->entityTypeManager->getStorage('iframe_session');
    $session = $sessionStorage->create([
      'uid' => $account->id(),
    ]);
    $sessionStorage->save($session);

    $form_state->setRedirect(
      'entity.user.canonical',
      ['user' => $account->id()],
      ['query' => [UrlParameters::PARAMNAME => $session->get('uuid')->value .
        ':' . $session->get('session_key')->value]],
    );
  }

}
