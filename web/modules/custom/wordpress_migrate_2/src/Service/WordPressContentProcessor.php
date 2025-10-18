<?php

namespace Drupal\wordpress_migrate_2\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Service for processing WordPress content into Drupal entities.
 */
class WordPressContentProcessor {

  /**
   * The WordPress data extractor service.
   *
   * @var \Drupal\wordpress_migrate\Service\WordPressDataExtractor
   */
  protected $dataExtractor;

  /**
   * The WordPress media processor service.
   *
   * @var \Drupal\wordpress_migrate\Service\WordPressMediaProcessor
   */
  protected $mediaProcessor;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Migration log storage.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a WordPressContentProcessor object.
   *
   * @param \Drupal\wordpress_migrate\Service\WordPressDataExtractor $data_extractor
   *   The WordPress data extractor service.
   * @param \Drupal\wordpress_migrate\Service\WordPressMediaProcessor $media_processor
   *   The WordPress media processor service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(WordPressDataExtractor $data_extractor, WordPressMediaProcessor $media_processor, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory) {
    $this->dataExtractor = $data_extractor;
    $this->mediaProcessor = $media_processor;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('wordpress_migrate');
    $this->database = \Drupal::database();
  }

  /**
   * Process WordPress users.
   *
   * @param int $limit
   *   Number of users to process.
   * @param int $offset
   *   Offset for pagination.
   *
   * @return array
   *   Processing results.
   */
  public function processUsers($limit = 100, $offset = 0) {
    $users = $this->dataExtractor->getUsers($limit, $offset);
    $results = ['success' => 0, 'failed' => 0, 'skipped' => 0];

    foreach ($users as $wp_user) {
      try {
        // Check if user already exists
        if ($this->isUserMigrated($wp_user['ID'])) {
          $results['skipped']++;
          continue;
        }

        // Create Drupal user
        $user = $this->createUser($wp_user);

        if ($user) {
          $this->logMigration('users', $wp_user['ID'], $user->id(), 'success');
          $results['success']++;
        } else {
          $this->logMigration('users', $wp_user['ID'], NULL, 'failed', 'Failed to create user');
          $results['failed']++;
        }
      }
      catch (\Exception $e) {
        $this->logMigration('users', $wp_user['ID'], NULL, 'failed', $e->getMessage());
        $results['failed']++;
        $this->logger->error('Error processing WordPress user @id: @message', [
          '@id' => $wp_user['ID'],
          '@message' => $e->getMessage(),
        ]);
      }
    }

    return $results;
  }

  /**
   * Process WordPress posts.
   *
   * @param string $post_type
   *   Post type (post, page, etc.).
   * @param int $limit
   *   Number of posts to process.
   * @param int $offset
   *   Offset for pagination.
   *
   * @return array
   *   Processing results.
   */
  public function processPosts($post_type = 'post', $limit = 100, $offset = 0) {
    $posts = $this->dataExtractor->getPosts($post_type, $limit, $offset);
    $results = ['success' => 0, 'failed' => 0, 'skipped' => 0];

    foreach ($posts as $wp_post) {
      try {
        // If the post was migrated before, update it; otherwise create new.
        if ($this->isPostMigrated($wp_post['ID'])) {
          $existing_nid = $this->getMigratedPostId($wp_post['ID']);
          $node = $existing_nid ? Node::load($existing_nid) : NULL;
          if ($node) {
            // Update basic fields.
            if (!empty($wp_post['post_title'])) {
              $node->setTitle($wp_post['post_title']);
            }
            // Update body only if field exists and content present.
            $bundle = $node->bundle();
            $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);
            if (isset($fields['body']) && isset($wp_post['post_content'])) {
              $node->set('body', [
                'value' => $wp_post['post_content'],
                'format' => 'full_html',
              ]);
            }
            // Update author/dates if available.
            if (!empty($wp_post['post_author'])) {
              $author_id = $this->getMigratedUserId($wp_post['post_author']);
              if ($author_id) {
                $node->set('uid', $author_id);
              }
            }
            if (!empty($wp_post['post_date'])) {
              $node->setCreatedTime(strtotime($wp_post['post_date']));
            }
            if (!empty($wp_post['post_modified'])) {
              $node->setChangedTime(strtotime($wp_post['post_modified']));
            }

            // Update taxonomy references if fields exist.
            if (!empty($wp_post['categories']) && isset($fields['field_categories'])) {
              $category_ids = [];
              foreach ($wp_post['categories'] as $category) {
                $term_id = $this->getMigratedTermId($category['term_id']);
                if ($term_id) {
                  $category_ids[] = $term_id;
                }
              }
              $node->set('field_categories', $category_ids);
            }

            if (!empty($wp_post['tags']) && isset($fields['field_tags'])) {
              $tag_ids = [];
              foreach ($wp_post['tags'] as $tag) {
                $term_id = $this->getMigratedTermId($tag['term_id']);
                if ($term_id) {
                  $tag_ids[] = $term_id;
                }
              }
              $node->set('field_tags', $tag_ids);
            }

            // Set langcode if meta present and field exists
            $langcode = NULL;
            if (!empty($wp_post['meta']['_lang'])) {
              $langcode = $this->mapWPLangToDrupalLangcode($wp_post['meta']['_lang']);
            } elseif (!empty($wp_post['meta']['lang'])) {
              $langcode = $this->mapWPLangToDrupalLangcode($wp_post['meta']['lang']);
            } elseif (!empty($wp_post['meta']['_language'])) {
              $langcode = $this->mapWPLangToDrupalLangcode($wp_post['meta']['_language']);
            }
            if ($langcode) {
              $field_defs = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $node->bundle());
              if (isset($field_defs['langcode'])) {
                $node->set('langcode', $langcode);
              }
            }

            $node->save();
            $this->setNodeAlias($node, $wp_post, $langcode ?? $node->language()->getId());
          }
          else {
            // Stale mapping: create anew.
            $node = $this->createNode($wp_post, $post_type);
          }
        }
        else {
          // Create Drupal node
          $node = $this->createNode($wp_post, $post_type);
        }

        if ($node) {
          $this->logMigration('posts', $wp_post['ID'], $node->id(), 'success');
          $results['success']++;
        } else {
          $this->logMigration('posts', $wp_post['ID'], NULL, 'failed', 'Failed to create node');
          $results['failed']++;
        }
      }
      catch (\Exception $e) {
        $this->logMigration('posts', $wp_post['ID'], NULL, 'failed', $e->getMessage());
        $results['failed']++;
        $this->logger->error('Error processing WordPress post @id: @message', [
          '@id' => $wp_post['ID'],
          '@message' => $e->getMessage(),
        ]);
      }
    }

    return $results;
  }

  /**
   * Process WordPress media files.
   *
   * @param int $limit
   *   Number of media files to process.
   * @param int $offset
   *   Offset for pagination.
   *
   * @return array
   *   Processing results.
   */
  public function processMedia($limit = 100, $offset = 0) {
    $media_files = $this->dataExtractor->getMedia($limit, $offset);
    $results = ['success' => 0, 'failed' => 0, 'skipped' => 0];

    foreach ($media_files as $wp_media) {
      try {
        // Check if media already exists
        if ($this->isMediaMigrated($wp_media['ID'])) {
          $results['skipped']++;
          continue;
        }

        // Process media file
        $media = $this->mediaProcessor->processMedia($wp_media);

        if ($media) {
          $this->logMigration('media', $wp_media['ID'], $media->id(), 'success');
          $results['success']++;
        } else {
          $this->logMigration('media', $wp_media['ID'], NULL, 'failed', 'Failed to process media');
          $results['failed']++;
        }
      }
      catch (\Exception $e) {
        $this->logMigration('media', $wp_media['ID'], NULL, 'failed', $e->getMessage());
        $results['failed']++;
        $this->logger->error('Error processing WordPress media @id: @message', [
          '@id' => $wp_media['ID'],
          '@message' => $e->getMessage(),
        ]);
      }
    }

    return $results;
  }

  /**
   * Process WordPress terms (categories, tags).
   *
   * @param string $taxonomy
   *   Taxonomy name.
   * @param int $limit
   *   Number of terms to process.
   * @param int $offset
   *   Offset for pagination.
   *
   * @return array
   *   Processing results.
   */
  public function processTerms($taxonomy = 'category', $limit = 100, $offset = 0) {
    $terms = $this->dataExtractor->getTerms($taxonomy, $limit, $offset);
    $results = ['success' => 0, 'failed' => 0, 'skipped' => 0];

    // Ensure vocabulary exists
    $this->ensureVocabulary($taxonomy);

    foreach ($terms as $wp_term) {
      try {
        // Check if term already exists
        if ($this->isTermMigrated($wp_term['term_id'])) {
          $results['skipped']++;
          continue;
        }

        // Create Drupal term
        $term = $this->createTerm($wp_term, $taxonomy);

        if ($term) {
          $this->logMigration('terms', $wp_term['term_id'], $term->id(), 'success');
          $results['success']++;
        } else {
          $this->logMigration('terms', $wp_term['term_id'], NULL, 'failed', 'Failed to create term');
          $results['failed']++;
        }
      }
      catch (\Exception $e) {
        $this->logMigration('terms', $wp_term['term_id'], NULL, 'failed', $e->getMessage());
        $results['failed']++;
        $this->logger->error('Error processing WordPress term @id: @message', [
          '@id' => $wp_term['term_id'],
          '@message' => $e->getMessage(),
        ]);
      }
    }

    return $results;
  }

  /**
   * Create Drupal user from WordPress user data.
   *
   * @param array $wp_user
   *   WordPress user data.
   *
   * @return \Drupal\user\Entity\User|null
   *   Created user entity or NULL on failure.
   */
  protected function createUser(array $wp_user) {
    // Check if user with this email already exists
    $existing_user = user_load_by_mail($wp_user['user_email']);
    if ($existing_user) {
      return $existing_user;
    }

    $user = User::create([
      'name' => $wp_user['user_login'],
      'mail' => $wp_user['user_email'],
      'status' => 1,
      'created' => strtotime($wp_user['user_registered']),
    ]);

    // Set display name if available
    if (!empty($wp_user['meta']['display_name'])) {
      $user->set('field_display_name', $wp_user['meta']['display_name']);
    }

    // Set first and last name if available
    if (!empty($wp_user['meta']['first_name'])) {
      $user->set('field_first_name', $wp_user['meta']['first_name']);
    }
    if (!empty($wp_user['meta']['last_name'])) {
      $user->set('field_last_name', $wp_user['meta']['last_name']);
    }

    $user->save();
    return $user;
  }

  /**
   * Create Drupal node from WordPress post data.
   *
   * @param array $wp_post
   *   WordPress post data.
   * @param string $post_type
   *   Post type.
   *
   * @return \Drupal\node\Entity\Node|null
   *   Created node entity or NULL on failure.
   */
  protected function createNode(array $wp_post, $post_type) {
    $bundle = ($post_type === 'page') ? 'wordpress_page' : 'wordpress_post';

    $node_values = [
      'type' => $bundle,
      'title' => $wp_post['post_title'] ?? '(untitled)',
      'status' => 1,
      'created' => !empty($wp_post['post_date']) ? strtotime($wp_post['post_date']) : time(),
      'changed' => !empty($wp_post['post_modified']) ? strtotime($wp_post['post_modified']) : time(),
    ];

    // Only set body if field exists on bundle.
    $body_exists = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('node', $bundle);
    if (isset($body_exists['body']) && !empty($wp_post['post_content'])) {
      $node_values['body'] = [
        'value' => $wp_post['post_content'],
        'format' => 'full_html',
      ];
    }

    // Map WordPress language/locale to Drupal langcode.
    $langcode = 'und';
    if (!empty($wp_post['meta']['_lang'])) {
      $langcode = $this->mapWPLangToDrupalLangcode($wp_post['meta']['_lang']);
    } elseif (!empty($wp_post['meta']['lang'])) {
      $langcode = $this->mapWPLangToDrupalLangcode($wp_post['meta']['lang']);
    } elseif (!empty($wp_post['meta']['_language'])) {
      $langcode = $this->mapWPLangToDrupalLangcode($wp_post['meta']['_language']);
    }
    // Only set if bundle supports langcode
    $field_defs = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);
    if (isset($field_defs['langcode'])) {
      $node_values['langcode'] = $langcode;
    }

    $node = Node::create($node_values);

    // Set author if available
    if (!empty($wp_post['post_author'])) {
      $author_id = $this->getMigratedUserId($wp_post['post_author']);
      if ($author_id) {
        $node->set('uid', $author_id);
      }
    }

    // Add categories and tags
    if (!empty($wp_post['categories']) && isset($body_exists['field_categories'])) {
      $category_ids = [];
      foreach ($wp_post['categories'] as $category) {
        $term_id = $this->getMigratedTermId($category['term_id']);
        if ($term_id) {
          $category_ids[] = $term_id;
        }
      }
      if (!empty($category_ids)) {
        $node->set('field_categories', $category_ids);
      }
    }

    if (!empty($wp_post['tags']) && isset($body_exists['field_tags'])) {
      $tag_ids = [];
      foreach ($wp_post['tags'] as $tag) {
        $term_id = $this->getMigratedTermId($tag['term_id']);
        if ($term_id) {
          $tag_ids[] = $term_id;
        }
      }
      if (!empty($tag_ids)) {
        $node->set('field_tags', $tag_ids);
      }
    }

    $node->save();
    $this->setNodeAlias($node, $wp_post, $langcode);
    return $node;
  }

  /**
   * Create Drupal term from WordPress term data.
   *
   * @param array $wp_term
   *   WordPress term data.
   * @param string $taxonomy
   *   Taxonomy name.
   *
   * @return \Drupal\taxonomy\Entity\Term|null
   *   Created term entity or NULL on failure.
   */
  protected function createTerm(array $wp_term, $taxonomy) {
    $vocabulary = $this->getVocabularyName($taxonomy);

    $term = Term::create([
      'vid' => $vocabulary,
      'name' => $wp_term['name'],
      'description' => [
        'value' => $wp_term['description'],
        'format' => 'basic_html',
      ],
    ]);

    // Set parent if available
    if (!empty($wp_term['parent']) && $wp_term['parent'] > 0) {
      $parent_id = $this->getMigratedTermId($wp_term['parent']);
      if ($parent_id) {
        $term->set('parent', $parent_id);
      }
    }

    $term->save();
    return $term;
  }

  /**
   * Ensure vocabulary exists.
   *
   * @param string $taxonomy
   *   Taxonomy name.
   */
  protected function ensureVocabulary($taxonomy) {
    $vocabulary_name = $this->getVocabularyName($taxonomy);

    if (!Vocabulary::load($vocabulary_name)) {
      $vocabulary = Vocabulary::create([
        'vid' => $vocabulary_name,
        'name' => ucfirst($taxonomy),
        'description' => 'Migrated from WordPress ' . $taxonomy,
      ]);
      $vocabulary->save();
    }
  }

  /**
   * Get vocabulary name for taxonomy.
   *
   * @param string $taxonomy
   *   Taxonomy name.
   *
   * @return string
   *   Vocabulary name.
   */
  protected function getVocabularyName($taxonomy) {
    $vocabularies = [
      'category' => 'wordpress_categories',
      'post_tag' => 'wordpress_tags',
    ];

    return isset($vocabularies[$taxonomy]) ? $vocabularies[$taxonomy] : 'wordpress_' . $taxonomy;
  }

  /**
   * Check if user is already migrated.
   *
   * @param int $wp_user_id
   *   WordPress user ID.
   *
   * @return bool
   *   TRUE if migrated, FALSE otherwise.
   */
  protected function isUserMigrated($wp_user_id) {
    $result = $this->database->select('wordpress_migrate_log', 'wml')
      ->fields('wml', ['id', 'drupal_id'])
      ->condition('migration_type', 'users')
      ->condition('wordpress_id', $wp_user_id)
      ->condition('status', 'success')
      ->execute()
      ->fetchAssoc();

    if (!empty($result)) {
      // Verify the Drupal user still exists. If not, remove stale log.
      $exists = User::load($result['drupal_id']);
      if ($exists) {
        return TRUE;
      }
      $this->database->delete('wordpress_migrate_log')
        ->condition('id', $result['id'])
        ->execute();
    }
    return FALSE;
  }

  /**
   * Check if post is already migrated.
   *
   * @param int $wp_post_id
   *   WordPress post ID.
   *
   * @return bool
   *   TRUE if migrated, FALSE otherwise.
   */
  protected function isPostMigrated($wp_post_id) {
    $result = $this->database->select('wordpress_migrate_log', 'wml')
      ->fields('wml', ['id', 'drupal_id'])
      ->condition('migration_type', 'posts')
      ->condition('wordpress_id', $wp_post_id)
      ->condition('status', 'success')
      ->execute()
      ->fetchAssoc();

    if (!empty($result)) {
      $exists = Node::load($result['drupal_id']);
      if ($exists) {
        return TRUE;
      }
      $this->database->delete('wordpress_migrate_log')
        ->condition('id', $result['id'])
        ->execute();
    }
    return FALSE;
  }

  /**
   * Check if media is already migrated.
   *
   * @param int $wp_media_id
   *   WordPress media ID.
   *
   * @return bool
   *   TRUE if migrated, FALSE otherwise.
   */
  protected function isMediaMigrated($wp_media_id) {
    $result = $this->database->select('wordpress_migrate_log', 'wml')
      ->fields('wml', ['id', 'drupal_id'])
      ->condition('migration_type', 'media')
      ->condition('wordpress_id', $wp_media_id)
      ->condition('status', 'success')
      ->execute()
      ->fetchAssoc();

    if (!empty($result)) {
      $exists = \Drupal\media\Entity\Media::load($result['drupal_id']);
      if ($exists) {
        return TRUE;
      }
      $this->database->delete('wordpress_migrate_log')
        ->condition('id', $result['id'])
        ->execute();
    }
    return FALSE;
  }

  /**
   * Check if term is already migrated.
   *
   * @param int $wp_term_id
   *   WordPress term ID.
   *
   * @return bool
   *   TRUE if migrated, FALSE otherwise.
   */
  protected function isTermMigrated($wp_term_id) {
    $result = $this->database->select('wordpress_migrate_log', 'wml')
      ->fields('wml', ['id', 'drupal_id'])
      ->condition('migration_type', 'terms')
      ->condition('wordpress_id', $wp_term_id)
      ->condition('status', 'success')
      ->execute()
      ->fetchAssoc();

    if (!empty($result)) {
      $exists = Term::load($result['drupal_id']);
      if ($exists) {
        return TRUE;
      }
      $this->database->delete('wordpress_migrate_log')
        ->condition('id', $result['id'])
        ->execute();
    }
    return FALSE;
  }

  /**
   * Get migrated user ID.
   *
   * @param int $wp_user_id
   *   WordPress user ID.
   *
   * @return int|null
   *   Drupal user ID or NULL if not found.
   */
  protected function getMigratedUserId($wp_user_id) {
    $result = $this->database->select('wordpress_migrate_log', 'wml')
      ->fields('wml', ['drupal_id'])
      ->condition('migration_type', 'users')
      ->condition('wordpress_id', $wp_user_id)
      ->condition('status', 'success')
      ->execute()
      ->fetchField();

    return $result ?: NULL;
  }

  /**
   * Get migrated post (node) ID.
   *
   * @param int $wp_post_id
   *   WordPress post ID.
   *
   * @return int|null
   *   Drupal node ID or NULL if not found.
   */
  protected function getMigratedPostId($wp_post_id) {
    $result = $this->database->select('wordpress_migrate_log', 'wml')
      ->fields('wml', ['drupal_id'])
      ->condition('migration_type', 'posts')
      ->condition('wordpress_id', $wp_post_id)
      ->condition('status', 'success')
      ->execute()
      ->fetchField();

    return $result ?: NULL;
  }

  /**
   * Get migrated term ID.
   *
   * @param int $wp_term_id
   *   WordPress term ID.
   *
   * @return int|null
   *   Drupal term ID or NULL if not found.
   */
  protected function getMigratedTermId($wp_term_id) {
    $result = $this->database->select('wordpress_migrate_log', 'wml')
      ->fields('wml', ['drupal_id'])
      ->condition('migration_type', 'terms')
      ->condition('wordpress_id', $wp_term_id)
      ->condition('status', 'success')
      ->execute()
      ->fetchField();

    return $result ?: NULL;
  }

  /**
   * Log migration activity.
   *
   * @param string $migration_type
   *   Migration type.
   * @param int $wordpress_id
   *   WordPress ID.
   * @param int|null $drupal_id
   *   Drupal ID.
   * @param string $status
   *   Status (success, failed, skipped).
   * @param string $message
   *   Additional message.
   */
  protected function logMigration($migration_type, $wordpress_id, $drupal_id, $status, $message = '') {
    $this->database->insert('wordpress_migrate_log')
      ->fields([
        'migration_type' => $migration_type,
        'wordpress_id' => $wordpress_id,
        'drupal_id' => $drupal_id,
        'status' => $status,
        'message' => $message,
        'created' => time(),
      ])
      ->execute();
  }

  /**
   * Map WordPress language/locale to Drupal langcode.
   */
  protected function mapWPLangToDrupalLangcode($input) {
    if (!$input) return NULL;
    $input_lc = strtolower(trim($input));
    if (strpos($input_lc, 'ar') === 0) return 'ar';
    if (strpos($input_lc, 'en') === 0) return 'en';
    return 'und';
  }

  /**
   * Set path alias for the created or updated node from WordPress slug.
   */
  protected function setNodeAlias($node, $wp_post, $langcode = NULL) {
    // Must have a node and a slug.
    if (!$node || empty($wp_post['post_name'])) return;

    $slug = $wp_post['post_name'];
    $slug = trim($slug, '/');
    // Construct alias.
    $lang_prefix = ($langcode === 'ar') ? '/ar' : '';
    $alias = $lang_prefix . '/' . $slug;
    $alias = preg_replace('#/+#','/', $alias); // Remove duplicate slashes.
    if ($alias === '/') return;

    $path = '/node/'.$node->id();
    // Remove all previous aliases for this node path and language.
    $storage = \Drupal::entityTypeManager()->getStorage('path_alias');
    $query = $storage->getQuery()->condition('path', $path)->accessCheck(FALSE);
    if ($langcode) {
      $query->condition('langcode', $langcode);
    }
    $ids = $query->execute();
    if ($ids) {
      $entities = $storage->loadMultiple($ids);
      foreach ($entities as $alias_entity) {
        $alias_entity->delete();
      }
    }
    // Save new alias using entity API.
    $alias_entity = $storage->create([
      'path' => $path,
      'alias' => $alias,
      'langcode' => $langcode ?? $node->language()->getId(),
    ]);
    $alias_entity->save();
  }

}
