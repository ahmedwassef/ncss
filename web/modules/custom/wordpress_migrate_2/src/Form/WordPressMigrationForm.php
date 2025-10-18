<?php

namespace Drupal\wordpress_migrate_2\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\wordpress_migrate\Service\WordPressContentProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for running WordPress migration.
 */
class WordPressMigrationForm extends FormBase {

  /**
   * The WordPress content processor service.
   *
   * @var \Drupal\wordpress_migrate\Service\WordPressContentProcessor
   */
  protected $contentProcessor;

  /**
   * Constructs a WordPressMigrationForm object.
   *
   * @param \Drupal\wordpress_migrate\Service\WordPressContentProcessor $content_processor
   *   The WordPress content processor service.
   */
  public function __construct(WordPressContentProcessor $content_processor) {
    $this->contentProcessor = $content_processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('wordpress_migrate.content_processor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wordpress_migration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['migration_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select content types to migrate'),
      '#title_display' => 'before',
      '#options' => [
        'users' => $this->t('Users'),
        'categories' => $this->t('Categories'),
        'tags' => $this->t('Tags'),
        'media' => $this->t('Media Files'),
        'posts' => $this->t('Posts'),
        'pages' => $this->t('Pages'),
      ],
      '#default_value' => ['users', 'categories', 'tags', 'media', 'posts', 'pages'],
      '#description' => $this->t('Select which content types you want to migrate from WordPress to Drupal.'),
    ];

    $form['batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Batch Size'),
      '#default_value' => \Drupal::config('wordpress_migrate.settings')->get('migration.batch_size') ?: 50,
      '#min' => 1,
      '#max' => 500,
      '#description' => $this->t('Number of items to process in each batch.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Migration'),
      '#button_type' => 'primary',
    ];

    $form['actions']['preview'] = [
      '#type' => 'submit',
      '#value' => $this->t('Preview Data'),
      '#submit' => ['::previewData'],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $migration_types = array_filter($form_state->getValue('migration_types'));

    if (empty($migration_types)) {
      $form_state->setErrorByName('migration_types', $this->t('Please select at least one content type to migrate.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $migration_types = array_filter($form_state->getValue('migration_types'));
    $batch_size = $form_state->getValue('batch_size');

    $results = [];
    $total_processed = 0;
    $total_success = 0;
    $total_failed = 0;
    $total_skipped = 0;

    foreach ($migration_types as $type) {
      $this->messenger()->addMessage($this->t('Starting migration of @type...', ['@type' => $type]));

      switch ($type) {
        case 'users':
          $result = $this->contentProcessor->processUsers($batch_size);
          break;

        case 'categories':
          $result = $this->contentProcessor->processTerms('category', $batch_size);
          break;

        case 'tags':
          $result = $this->contentProcessor->processTerms('post_tag', $batch_size);
          break;

        case 'media':
          $result = $this->contentProcessor->processMedia($batch_size);
          break;

        case 'posts':
          $result = $this->contentProcessor->processPosts('post', $batch_size);
          break;

        case 'pages':
          $result = $this->contentProcessor->processPosts('page', $batch_size);
          break;

        default:
          continue 2;
      }

      $results[$type] = $result;
      $total_processed += array_sum($result);
      $total_success += $result['success'];
      $total_failed += $result['failed'];
      $total_skipped += $result['skipped'];

      $this->messenger()->addMessage($this->t('@type migration completed: @success successful, @failed failed, @skipped skipped', [
        '@type' => $type,
        '@success' => $result['success'],
        '@failed' => $result['failed'],
        '@skipped' => $result['skipped'],
      ]));
    }

    // Summary message
    $this->messenger()->addStatus($this->t('Migration completed! Total: @total processed, @success successful, @failed failed, @skipped skipped', [
      '@total' => $total_processed,
      '@success' => $total_success,
      '@failed' => $total_failed,
      '@skipped' => $total_skipped,
    ]));

    // Redirect to settings page
    $form_state->setRedirect('wordpress_migrate.admin');
  }

  /**
   * Preview WordPress data.
   */
  public function previewData(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('wordpress_migrate.preview');
  }

}
