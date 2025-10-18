<?php

namespace Drupal\wordpress_migrate_2\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\wordpress_migrate\Service\WordPressConnection;

/**
 * Configuration form for WordPress Migration settings.
 */
class WordPressMigrateSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['wordpress_migrate.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wordpress_migrate_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('wordpress_migrate.settings');

    $form['database'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('WordPress Database Settings'),
      '#description' => $this->t('Configure the connection to your WordPress database.'),
    ];

    $form['database']['host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database Host'),
      '#default_value' => $config->get('database.host') ?: 'localhost',
      '#required' => TRUE,
      '#description' => $this->t('The hostname of your WordPress database server.'),
    ];

    $form['database']['port'] = [
      '#type' => 'number',
      '#title' => $this->t('Database Port'),
      '#default_value' => $config->get('database.port') ?: 3306,
      '#min' => 1,
      '#max' => 65535,
      '#description' => $this->t('The port number for the database connection.'),
    ];

    $form['database']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database Name'),
      '#default_value' => $config->get('database.name'),
      '#required' => TRUE,
      '#description' => $this->t('The name of your WordPress database.'),
    ];

    $form['database']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database Username'),
      '#default_value' => $config->get('database.username'),
      '#required' => TRUE,
      '#description' => $this->t('The username for the database connection.'),
    ];

    $form['database']['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Database Password'),
      '#default_value' => $config->get('database.password'),
      '#description' => $this->t('The password for the database connection.'),
    ];

    $form['database']['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Table Prefix'),
      '#default_value' => $config->get('database.prefix') ?: 'wp_',
      '#description' => $this->t('The table prefix used in your WordPress database (usually wp_).'),
    ];

    $form['wordpress'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('WordPress Site Settings'),
    ];

    $form['wordpress']['base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('WordPress Base URL'),
      '#default_value' => $config->get('wordpress.base_url'),
      '#description' => $this->t('The base URL of your WordPress site (e.g., https://example.com). This is used for downloading media files.'),
    ];

    $form['migration'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Migration Settings'),
    ];

    $form['migration']['batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Batch Size'),
      '#default_value' => $config->get('migration.batch_size') ?: 50,
      '#min' => 1,
      '#max' => 500,
      '#description' => $this->t('Number of items to process in each batch.'),
    ];

    $form['migration']['skip_existing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip Existing Content'),
      '#default_value' => $config->get('migration.skip_existing') ?: TRUE,
      '#description' => $this->t('Skip content that has already been migrated.'),
    ];

    $form['migration']['create_content_types'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create Content Types'),
      '#default_value' => $config->get('migration.create_content_types') ?: TRUE,
      '#description' => $this->t('Automatically create content types for WordPress posts and pages.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['test_connection'] = [
      '#type' => 'submit',
      '#value' => $this->t('Test Connection'),
      '#submit' => ['::testConnection'],
      '#limit_validation_errors' => [
        ['database'],
      ],
    ];

    $form['actions']['preview'] = [
      '#type' => 'submit',
      '#value' => $this->t('Preview Data'),
      '#submit' => ['::previewData'],
      '#limit_validation_errors' => [
        ['database'],
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Configuration'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate database connection if test connection is clicked
    if ($form_state->getTriggeringElement()['#id'] === 'edit-test-connection') {
      $this->testConnection($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('wordpress_migrate.settings');

    // Save database settings
    $config->set('database.host', $form_state->getValue('host'));
    $config->set('database.port', $form_state->getValue('port'));
    $config->set('database.name', $form_state->getValue('name'));
    $config->set('database.username', $form_state->getValue('username'));

    // Only save password if it's not empty
    if (!empty($form_state->getValue('password'))) {
      $config->set('database.password', $form_state->getValue('password'));
    }

    $config->set('database.prefix', $form_state->getValue('prefix'));

    // Save WordPress settings
    $config->set('wordpress.base_url', $form_state->getValue('base_url'));

    // Save migration settings
    $config->set('migration.batch_size', $form_state->getValue('batch_size'));
    $config->set('migration.skip_existing', $form_state->getValue('skip_existing'));
    $config->set('migration.create_content_types', $form_state->getValue('create_content_types'));

    $config->save();

    $this->messenger()->addMessage($this->t('WordPress Migration settings have been saved.'));
  }

  /**
   * Test database connection.
   */
  public function testConnection(array &$form, FormStateInterface $form_state) {
    // Temporarily save the form values to test the connection
    $temp_config = $this->configFactory()->getEditable('wordpress_migrate.settings');
    $temp_config->set('database.host', $form_state->getValue('host'));
    $temp_config->set('database.port', $form_state->getValue('port'));
    $temp_config->set('database.name', $form_state->getValue('name'));
    $temp_config->set('database.username', $form_state->getValue('username'));
    $temp_config->set('database.password', $form_state->getValue('password'));
    $temp_config->set('database.prefix', $form_state->getValue('prefix'));
    $temp_config->save();

    $connection_service = \Drupal::service('wordpress_migrate.connection');
    $result = $connection_service->testConnection();

    if ($result['status'] === 'success') {
      $this->messenger()->addMessage($this->t('Database connection successful! @message', [
        '@message' => $result['message'],
      ]), 'status');
    }
    elseif ($result['status'] === 'warning') {
      $this->messenger()->addWarning($this->t('Database connection successful with warnings: @message', [
        '@message' => $result['message'],
      ]));
    }
    else {
      $this->messenger()->addError($this->t('Database connection failed: @message', [
        '@message' => $result['message'],
      ]));
    }
  }

  /**
   * Preview WordPress data.
   */
  public function previewData(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('wordpress_migrate.preview');
  }

}
