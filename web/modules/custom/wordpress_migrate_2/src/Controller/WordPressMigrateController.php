<?php

namespace Drupal\wordpress_migrate_2\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\wordpress_migrate\Service\WordPressConnection;
use Drupal\wordpress_migrate\Service\WordPressDataExtractor;
use Drupal\wordpress_migrate\Service\WordPressContentProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for WordPress Migration operations.
 */
class WordPressMigrateController extends ControllerBase {

  /**
   * The WordPress connection service.
   *
   * @var \Drupal\wordpress_migrate\Service\WordPressConnection
   */
  protected $connection;

  /**
   * The WordPress data extractor service.
   *
   * @var \Drupal\wordpress_migrate\Service\WordPressDataExtractor
   */
  protected $dataExtractor;

  /**
   * The WordPress content processor service.
   *
   * @var \Drupal\wordpress_migrate\Service\WordPressContentProcessor
   */
  protected $contentProcessor;

  /**
   * Constructs a WordPressMigrateController object.
   *
   * @param \Drupal\wordpress_migrate\Service\WordPressConnection $connection
   *   The WordPress connection service.
   * @param \Drupal\wordpress_migrate\Service\WordPressDataExtractor $data_extractor
   *   The WordPress data extractor service.
   * @param \Drupal\wordpress_migrate\Service\WordPressContentProcessor $content_processor
   *   The WordPress content processor service.
   */
  public function __construct(WordPressConnection $connection, WordPressDataExtractor $data_extractor, WordPressContentProcessor $content_processor) {
    $this->connection = $connection;
    $this->dataExtractor = $data_extractor;
    $this->contentProcessor = $content_processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('wordpress_migrate.connection'),
      $container->get('wordpress_migrate.data_extractor'),
      $container->get('wordpress_migrate.content_processor')
    );
  }

  /**
   * Test WordPress database connection.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with connection test results.
   */
  public function testConnection() {
    $result = $this->connection->testConnection();
    return new JsonResponse($result);
  }

  /**
   * Preview WordPress data.
   *
   * @return array
   *   Render array for preview page.
   */
  public function previewData() {
    $build = [];

    // Test connection first
    $connection_result = $this->connection->testConnection();

    if ($connection_result['status'] !== 'success') {
      $build['error'] = [
        '#markup' => '<div class="messages messages--error">' . $connection_result['message'] . '</div>',
      ];
      return $build;
    }

    $build['info'] = [
      '#markup' => '<div class="messages messages--status">' . $connection_result['message'] . '</div>',
    ];

    // Test connection directly
    $connection_service = \Drupal::service('wordpress_migrate.connection');
    $direct_connection = $connection_service->getConnection();

    if ($direct_connection) {
      \Drupal::logger('wordpress_migrate')->info('Direct connection successful in preview');

      // Test a simple query
      try {
        $users_table = $connection_service->getTableName('users');
        $stmt = $direct_connection->prepare("SELECT COUNT(*) FROM {$users_table}");
        $stmt->execute();
        $user_count = $stmt->fetchColumn();
        \Drupal::logger('wordpress_migrate')->info('Direct query found @count users', ['@count' => $user_count]);
      } catch (\Exception $e) {
        \Drupal::logger('wordpress_migrate')->error('Direct query failed: @message', ['@message' => $e->getMessage()]);
      }
    } else {
      \Drupal::logger('wordpress_migrate')->error('Direct connection failed in preview');
    }

    // Get preview data
    $users = $this->dataExtractor->getUsers(5);
    $posts = $this->dataExtractor->getPosts('post', 5);
    $pages = $this->dataExtractor->getPosts('page', 5);
    $media = $this->dataExtractor->getMedia(5);
    $categories = $this->dataExtractor->getTerms('category', 10);
    $tags = $this->dataExtractor->getTerms('post_tag', 10);

    // Debug: Log the results
    \Drupal::logger('wordpress_migrate')->info('Preview data: Users: @users, Posts: @posts, Pages: @pages, Media: @media, Categories: @categories, Tags: @tags', [
      '@users' => count($users),
      '@posts' => count($posts),
      '@pages' => count($pages),
      '@media' => count($media),
      '@categories' => count($categories),
      '@tags' => count($tags),
    ]);

    $build['users'] = [
      '#type' => 'details',
      '#title' => $this->t('Users (@count)', ['@count' => count($users)]),
      '#open' => TRUE,
    ];

    if (!empty($users)) {
      $user_list = [];
      foreach ($users as $user) {
        $user_list[] = $user['user_login'] . ' (' . $user['user_email'] . ')';
      }
      $build['users']['list'] = [
        '#markup' => '<ul><li>' . implode('</li><li>', $user_list) . '</li></ul>',
      ];
    } else {
      $build['users']['empty'] = [
        '#markup' => '<p>' . $this->t('No users found.') . '</p>',
      ];
    }

    $build['posts'] = [
      '#type' => 'details',
      '#title' => $this->t('Posts (@count)', ['@count' => count($posts)]),
    ];

    if (!empty($posts)) {
      $post_list = [];
      foreach ($posts as $post) {
        $post_list[] = $post['post_title'] . ' (' . $post['post_date'] . ')';
      }
      $build['posts']['list'] = [
        '#markup' => '<ul><li>' . implode('</li><li>', $post_list) . '</li></ul>',
      ];
    } else {
      $build['posts']['empty'] = [
        '#markup' => '<p>' . $this->t('No posts found.') . '</p>',
      ];
    }

    $build['pages'] = [
      '#type' => 'details',
      '#title' => $this->t('Pages (@count)', ['@count' => count($pages)]),
    ];

    if (!empty($pages)) {
      $page_list = [];
      foreach ($pages as $page) {
        $page_list[] = $page['post_title'] . ' (' . $page['post_date'] . ')';
      }
      $build['pages']['list'] = [
        '#markup' => '<ul><li>' . implode('</li><li>', $page_list) . '</li></ul>',
      ];
    } else {
      $build['pages']['empty'] = [
        '#markup' => '<p>' . $this->t('No pages found.') . '</p>',
      ];
    }

    $build['media'] = [
      '#type' => 'details',
      '#title' => $this->t('Media Files (@count)', ['@count' => count($media)]),
    ];

    if (!empty($media)) {
      $media_list = [];
      foreach ($media as $file) {
        $media_list[] = $file['post_title'] . ' (' . $file['post_mime_type'] . ')';
      }
      $build['media']['list'] = [
        '#markup' => '<ul><li>' . implode('</li><li>', $media_list) . '</li></ul>',
      ];
    } else {
      $build['media']['empty'] = [
        '#markup' => '<p>' . $this->t('No media files found.') . '</p>',
      ];
    }

    $build['categories'] = [
      '#type' => 'details',
      '#title' => $this->t('Categories (@count)', ['@count' => count($categories)]),
    ];

    if (!empty($categories)) {
      $category_list = [];
      foreach ($categories as $category) {
        $category_list[] = $category['name'] . ' (' . $category['count'] . ' posts)';
      }
      $build['categories']['list'] = [
        '#markup' => '<ul><li>' . implode('</li><li>', $category_list) . '</li></ul>',
      ];
    } else {
      $build['categories']['empty'] = [
        '#markup' => '<p>' . $this->t('No categories found.') . '</p>',
      ];
    }

    $build['tags'] = [
      '#type' => 'details',
      '#title' => $this->t('Tags (@count)', ['@count' => count($tags)]),
    ];

    if (!empty($tags)) {
      $tag_list = [];
      foreach ($tags as $tag) {
        $tag_list[] = $tag['name'] . ' (' . $tag['count'] . ' posts)';
      }
      $build['tags']['list'] = [
        '#markup' => '<ul><li>' . implode('</li><li>', $tag_list) . '</li></ul>',
      ];
    } else {
      $build['tags']['empty'] = [
        '#markup' => '<p>' . $this->t('No tags found.') . '</p>',
      ];
    }

    $build['actions'] = [
      '#type' => 'actions',
    ];

    $build['actions']['run_migration'] = [
      '#type' => 'link',
      '#title' => $this->t('Run Migration'),
      '#url' => Url::fromRoute('wordpress_migrate.run'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    return $build;
  }

  /**
   * Run WordPress migration.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   Render array for migration page.
   */
  public function runMigration(Request $request) {
    $build = [];

    // Check if this is an AJAX request for batch processing
    if ($request->isXmlHttpRequest()) {
      return $this->processMigrationBatch($request);
    }

    $build['info'] = [
      '#markup' => '<div class="messages messages--warning">' .
        $this->t('This will start the migration process. Make sure you have configured your WordPress database settings and tested the connection.') .
        '</div>',
    ];

    // Create a simple form using form builder
    $form = \Drupal::formBuilder()->getForm('\Drupal\wordpress_migrate\Form\WordPressMigrationForm');
    $build['form'] = $form;

    return $build;
  }

  /**
   * Process migration batch.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with migration results.
   */
  protected function processMigrationBatch(Request $request) {
    $migration_types = $request->request->get('migration_types', []);
    $batch_size = \Drupal::config('wordpress_migrate.settings')->get('migration.batch_size') ?: 50;

    $results = [];

    foreach ($migration_types as $type) {
      switch ($type) {
        case 'users':
          $results['users'] = $this->contentProcessor->processUsers($batch_size);
          break;

        case 'categories':
          $results['categories'] = $this->contentProcessor->processTerms('category', $batch_size);
          break;

        case 'tags':
          $results['tags'] = $this->contentProcessor->processTerms('post_tag', $batch_size);
          break;

        case 'media':
          $results['media'] = $this->contentProcessor->processMedia($batch_size);
          break;

        case 'posts':
          $results['posts'] = $this->contentProcessor->processPosts('post', $batch_size);
          break;

        case 'pages':
          $results['pages'] = $this->contentProcessor->processPosts('page', $batch_size);
          break;
      }
    }

    return new JsonResponse($results);
  }

  /**
   * AJAX callback for migration.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   Render array for AJAX response.
   */
  public function ajaxMigrationCallback(array &$form, FormStateInterface $form_state) {
    // This would be implemented for AJAX batch processing
    return ['#markup' => '<div class="messages messages--status">Migration started. Check the logs for progress.</div>'];
  }

}
