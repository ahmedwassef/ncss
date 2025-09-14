<?php

namespace Drupal\sdp_integration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SDPSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['sdp_integration.settings'];
  }

  public function getFormId() {
    return 'sdp_integration_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sdp_integration.settings');

    $form['sdp_base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SDP Base URL'),
      '#default_value' => $config->get('sdp_base_url') ?: 'https://stg-sd.hrsd.gov.sa/auth-svc',
      '#description' => $this->t('Base URL for the SDP Auth Service'),
      '#required' => TRUE,
    ];

    $form['sdp_user_info_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Info Endpoint'),
      '#default_value' => $config->get('sdp_user_info_endpoint') ?: '/api/v1/token/user-info',
      '#description' => $this->t('Endpoint for retrieving user info'),
      '#required' => TRUE,
    ];

    $form['sdp_auth_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authorization Endpoint'),
      '#default_value' => $config->get('sdp_auth_endpoint') ?: '/api/v1/token',
      '#description' => $this->t('Endpoint for authorization check'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('sdp_integration.settings')
      ->set('sdp_base_url', $form_state->getValue('sdp_base_url'))
      ->set('sdp_user_info_endpoint', $form_state->getValue('sdp_user_info_endpoint'))
      ->set('sdp_auth_endpoint', $form_state->getValue('sdp_auth_endpoint'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
