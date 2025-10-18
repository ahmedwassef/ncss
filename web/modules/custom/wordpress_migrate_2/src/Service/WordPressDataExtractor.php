<?php

namespace Drupal\wordpress_migrate_2\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for extracting data from WordPress database.
 */
class WordPressDataExtractor {

  /**
   * The WordPress connection service.
   *
   * @var \Drupal\wordpress_migrate\Service\WordPressConnection
   */
  protected $connection;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a WordPressDataExtractor object.
   *
   * @param \Drupal\wordpress_migrate\Service\WordPressConnection $connection
   *   The WordPress connection service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(WordPressConnection $connection, LoggerChannelFactoryInterface $logger_factory) {
    $this->connection = $connection;
    $this->logger = $logger_factory->get('wordpress_migrate');
  }

  /**
   * Get WordPress users.
   *
   * @param int $limit
   *   Number of users to retrieve.
   * @param int $offset
   *   Offset for pagination.
   *
   * @return array
   *   Array of WordPress users.
   */
  public function getUsers($limit = 100, $offset = 0) {
    $wp_connection = $this->connection->getConnection();
    if (!$wp_connection) {
      $this->logger->error('WordPress connection failed in getUsers');
      return [];
    }

    try {
      $users_table = $this->connection->getTableName('users');
      $usermeta_table = $this->connection->getTableName('usermeta');

      $this->logger->info('Querying users table: @table', ['@table' => $users_table]);

      $stmt = $wp_connection->prepare("SELECT * FROM {$users_table} ORDER BY ID ASC LIMIT {$limit} OFFSET {$offset}");
      $stmt->execute();
      $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $this->logger->info('Found @count users', ['@count' => count($users)]);

      // Convert to associative array with ID as key
      $users_by_id = [];
      foreach ($users as $user) {
        $users_by_id[$user['ID']] = $user;
      }
      $users = $users_by_id;

      // Get user meta for each user
      foreach ($users as $user_id => $user) {
        $meta_stmt = $wp_connection->prepare("SELECT meta_key, meta_value FROM {$usermeta_table} WHERE user_id = ?");
        $meta_stmt->execute([$user_id]);
        $meta_results = $meta_stmt->fetchAll(\PDO::FETCH_ASSOC);

        $users[$user_id]['meta'] = [];
        foreach ($meta_results as $meta) {
          $users[$user_id]['meta'][$meta['meta_key']] = $meta['meta_value'];
        }
      }

      return $users;
    }
    catch (\Exception $e) {
      $this->logger->error('Error retrieving WordPress users: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Get WordPress posts.
   *
   * @param string $post_type
   *   Post type (post, page, etc.).
   * @param int $limit
   *   Number of posts to retrieve.
   * @param int $offset
   *   Offset for pagination.
   *
   * @return array
   *   Array of WordPress posts.
   */
  public function getPosts($post_type = 'post', $limit = 100, $offset = 0) {
    $wp_connection = $this->connection->getConnection();
    if (!$wp_connection) {
      return [];
    }

    try {
      $posts_table = $this->connection->getTableName('posts');
      $postmeta_table = $this->connection->getTableName('postmeta');

      $this->logger->info('Querying posts table: @table for post_type: @type', [
        '@table' => $posts_table,
        '@type' => $post_type,
      ]);

      // Debug: Check what post types and statuses exist
      $debug_stmt = $wp_connection->prepare("SELECT post_type, post_status, COUNT(*) as count FROM {$posts_table} GROUP BY post_type, post_status ORDER BY post_type, post_status");
      $debug_stmt->execute();
      $debug_results = $debug_stmt->fetchAll(\PDO::FETCH_ASSOC);
      foreach ($debug_results as $debug) {
        $this->logger->info('Found @count posts of type "@type" with status "@status"', [
          '@count' => $debug['count'],
          '@type' => $debug['post_type'],
          '@status' => $debug['post_status'],
        ]);
      }

      // Try different post statuses to find data
      $stmt = $wp_connection->prepare("SELECT * FROM {$posts_table} WHERE post_type = ? AND post_status IN ('publish', 'draft', 'private') ORDER BY ID ASC LIMIT {$limit} OFFSET {$offset}");
      $stmt->execute([$post_type]);
      $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      // If no posts found with status filter, try without status filter
      if (empty($posts)) {
        $this->logger->info('No posts found with status filter, trying without status filter');
        $stmt = $wp_connection->prepare("SELECT * FROM {$posts_table} WHERE post_type = ? ORDER BY ID ASC LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute([$post_type]);
        $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      }

      $this->logger->info('Found @count posts of type @type', [
        '@count' => count($posts),
        '@type' => $post_type,
      ]);

      // Convert to associative array with ID as key
      $posts_by_id = [];
      foreach ($posts as $post) {
        $posts_by_id[$post['ID']] = $post;
      }
      $posts = $posts_by_id;

      // Get post meta for each post
      foreach ($posts as $post_id => $post) {
        $meta_stmt = $wp_connection->prepare("SELECT meta_key, meta_value FROM {$postmeta_table} WHERE post_id = ?");
        $meta_stmt->execute([$post_id]);
        $meta_results = $meta_stmt->fetchAll(\PDO::FETCH_ASSOC);

        $posts[$post_id]['meta'] = [];
        foreach ($meta_results as $meta) {
          $posts[$post_id]['meta'][$meta['meta_key']] = $meta['meta_value'];
        }

        // Get post categories and tags
        $posts[$post_id]['categories'] = $this->getPostTerms($post_id, 'category');
        $posts[$post_id]['tags'] = $this->getPostTerms($post_id, 'post_tag');
      }

      return $posts;
    }
    catch (\Exception $e) {
      $this->logger->error('Error retrieving WordPress posts: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Get WordPress media files.
   *
   * @param int $limit
   *   Number of media files to retrieve.
   * @param int $offset
   *   Offset for pagination.
   *
   * @return array
   *   Array of WordPress media files.
   */
  public function getMedia($limit = 100, $offset = 0) {
    $wp_connection = $this->connection->getConnection();
    if (!$wp_connection) {
      return [];
    }

    try {
      $posts_table = $this->connection->getTableName('posts');
      $postmeta_table = $this->connection->getTableName('postmeta');

      // Try different post statuses for media
      $stmt = $wp_connection->prepare("SELECT * FROM {$posts_table} WHERE post_type = 'attachment' AND post_status IN ('inherit', 'publish', 'draft') ORDER BY ID ASC LIMIT {$limit} OFFSET {$offset}");
      $stmt->execute();
      $media = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      // If no media found with status filter, try without status filter
      if (empty($media)) {
        $this->logger->info('No media found with status filter, trying without status filter');
        $stmt = $wp_connection->prepare("SELECT * FROM {$posts_table} WHERE post_type = 'attachment' ORDER BY ID ASC LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute();
        $media = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      }

      // Convert to associative array with ID as key
      $media_by_id = [];
      foreach ($media as $file) {
        $media_by_id[$file['ID']] = $file;
      }
      $media = $media_by_id;

      // Get attachment meta for each media file
      foreach ($media as $media_id => $file) {
        $meta_stmt = $wp_connection->prepare("SELECT meta_key, meta_value FROM {$postmeta_table} WHERE post_id = ?");
        $meta_stmt->execute([$media_id]);
        $meta_results = $meta_stmt->fetchAll(\PDO::FETCH_ASSOC);

        $media[$media_id]['meta'] = [];
        foreach ($meta_results as $meta) {
          $media[$media_id]['meta'][$meta['meta_key']] = $meta['meta_value'];
        }
      }

      return $media;
    }
    catch (\Exception $e) {
      $this->logger->error('Error retrieving WordPress media: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Get WordPress terms (categories, tags, etc.).
   *
   * @param string $taxonomy
   *   Taxonomy name (category, post_tag, etc.).
   * @param int $limit
   *   Number of terms to retrieve.
   * @param int $offset
   *   Offset for pagination.
   *
   * @return array
   *   Array of WordPress terms.
   */
  public function getTerms($taxonomy = 'category', $limit = 100, $offset = 0) {
    $wp_connection = $this->connection->getConnection();
    if (!$wp_connection) {
      return [];
    }

    try {
      $terms_table = $this->connection->getTableName('terms');
      $term_taxonomy_table = $this->connection->getTableName('term_taxonomy');

      $this->logger->info('Querying terms for taxonomy: @taxonomy', ['@taxonomy' => $taxonomy]);

      // Debug: Check what taxonomies exist
      $debug_stmt = $wp_connection->prepare("SELECT taxonomy, COUNT(*) as count FROM {$term_taxonomy_table} GROUP BY taxonomy ORDER BY taxonomy");
      $debug_stmt->execute();
      $debug_results = $debug_stmt->fetchAll(\PDO::FETCH_ASSOC);
      foreach ($debug_results as $debug) {
        $this->logger->info('Found @count terms in taxonomy "@taxonomy"', [
          '@count' => $debug['count'],
          '@taxonomy' => $debug['taxonomy'],
        ]);
      }

      $stmt = $wp_connection->prepare("
        SELECT t.*, tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tt.count
        FROM {$terms_table} t
        JOIN {$term_taxonomy_table} tt ON t.term_id = tt.term_id
        WHERE tt.taxonomy = ?
        ORDER BY t.term_id ASC
        LIMIT {$limit} OFFSET {$offset}
      ");
      $stmt->execute([$taxonomy]);
      $terms = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      // Convert to associative array with term_id as key
      $terms_by_id = [];
      foreach ($terms as $term) {
        $terms_by_id[$term['term_id']] = $term;
      }

      return $terms_by_id;
    }
    catch (\Exception $e) {
      $this->logger->error('Error retrieving WordPress terms: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Get terms associated with a post.
   *
   * @param int $post_id
   *   WordPress post ID.
   * @param string $taxonomy
   *   Taxonomy name.
   *
   * @return array
   *   Array of terms.
   */
  protected function getPostTerms($post_id, $taxonomy) {
    $wp_connection = $this->connection->getConnection();
    if (!$wp_connection) {
      return [];
    }

    try {
      $terms_table = $this->connection->getTableName('terms');
      $term_taxonomy_table = $this->connection->getTableName('term_taxonomy');
      $term_relationships_table = $this->connection->getTableName('term_relationships');

      $stmt = $wp_connection->prepare("
        SELECT t.term_id, t.name, t.slug
        FROM {$terms_table} t
        JOIN {$term_taxonomy_table} tt ON t.term_id = tt.term_id
        JOIN {$term_relationships_table} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
        WHERE tt.taxonomy = ? AND tr.object_id = ?
      ");
      $stmt->execute([$taxonomy, $post_id]);
      $terms = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      // Convert to associative array with term_id as key
      $terms_by_id = [];
      foreach ($terms as $term) {
        $terms_by_id[$term['term_id']] = $term;
      }

      return $terms_by_id;
    }
    catch (\Exception $e) {
      $this->logger->error('Error retrieving post terms: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Get WordPress comments.
   *
   * @param int $post_id
   *   WordPress post ID (optional).
   * @param int $limit
   *   Number of comments to retrieve.
   * @param int $offset
   *   Offset for pagination.
   *
   * @return array
   *   Array of WordPress comments.
   */
  public function getComments($post_id = NULL, $limit = 100, $offset = 0) {
    $wp_connection = $this->connection->getConnection();
    if (!$wp_connection) {
      return [];
    }

    try {
      $comments_table = $this->connection->getTableName('comments');
      $commentmeta_table = $this->connection->getTableName('commentmeta');

      $sql = "SELECT * FROM {$comments_table} WHERE comment_approved = '1'";
      $params = [];

      if ($post_id) {
        $sql .= " AND comment_post_ID = ?";
        $params[] = $post_id;
      }

      $sql .= " ORDER BY comment_ID ASC LIMIT {$limit} OFFSET {$offset}";

      $stmt = $wp_connection->prepare($sql);
      $stmt->execute($params);
      $comments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      // Convert to associative array with comment_ID as key
      $comments_by_id = [];
      foreach ($comments as $comment) {
        $comments_by_id[$comment['comment_ID']] = $comment;
      }
      $comments = $comments_by_id;

      // Get comment meta for each comment
      foreach ($comments as $comment_id => $comment) {
        $meta_stmt = $wp_connection->prepare("SELECT meta_key, meta_value FROM {$commentmeta_table} WHERE comment_id = ?");
        $meta_stmt->execute([$comment_id]);
        $meta_results = $meta_stmt->fetchAll(\PDO::FETCH_ASSOC);

        $comments[$comment_id]['meta'] = [];
        foreach ($meta_results as $meta) {
          $comments[$comment_id]['meta'][$meta['meta_key']] = $meta['meta_value'];
        }
      }

      return $comments;
    }
    catch (\Exception $e) {
      $this->logger->error('Error retrieving WordPress comments: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

}
