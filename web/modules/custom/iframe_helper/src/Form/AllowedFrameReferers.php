<?php

namespace Drupal\iframe_helper\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure the sites that can use this site in an iframe.
 */
class AllowedFrameReferers extends ConfigFormBase {

  /**
   * The configuration name.
   */
  const CONFIG_NAME = 'iframe_helper.allowed_iframe_referrers';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iframe_helper_allowed_iframe_referrers';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [self::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);

    $form['allowed_domains'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed domains'),
      '#description' => $this->t('Domains are separated by line breaks. Each line is a regex expression.'),
      '#default_value' => $config->get('allowed_domains'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(self::CONFIG_NAME)
      ->set('allowed_domains', $form_state->getValue('allowed_domains'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
