<?php

namespace Drupal\wordpress_migrate_2\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use PDO;

/**
 * Service for connecting to WordPress database.
 */
class WordPressConnection {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * WordPress database connection.
   *
   * @var \PDO
   */
  protected $connection;

  /**
   * Constructs a WordPressConnection object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('wordpress_migrate');
  }

  /**
   * Get WordPress database connection.
   *
   * @return \PDO|null
   *   The database connection or NULL if connection fails.
   */
  public function getConnection() {
    // Don't cache connection to avoid issues
    // if ($this->connection) {
    //   return $this->connection;
    // }

    $config = $this->configFactory->get('wordpress_migrate.settings');

    $host = $config->get('database.host');
    $port = $config->get('database.port') ?: 3306;
    $database = $config->get('database.name');
    $username = $config->get('database.username');
    $password = $config->get('database.password');

    try {
      // Create a direct PDO connection
      $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
      $this->connection = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
      ]);

      // Test the connection
      $this->connection->query('SELECT 1')->fetchColumn();

      $this->logger->info('Successfully connected to WordPress database.');
      return $this->connection;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to connect to WordPress database: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Test WordPress database connection.
   *
   * @return array
   *   Test results with status and message.
   */
  public function testConnection() {
    $connection = $this->getConnection();

    if (!$connection) {
      return [
        'status' => 'error',
        'message' => 'Failed to connect to WordPress database. Please check your settings.',
      ];
    }

    try {
      // Test if WordPress tables exist
      $tables = [
        'posts',
        'users',
        'postmeta',
        'usermeta',
        'terms',
        'term_taxonomy',
        'term_relationships',
      ];

      $missing_tables = [];
      foreach ($tables as $table) {
        $table_name = $this->getTableName($table);

        $stmt = $connection->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table_name]);
        if (!$stmt->fetch()) {
          $missing_tables[] = $table_name;
        }
      }

      if (!empty($missing_tables)) {
        return [
          'status' => 'warning',
          'message' => 'Connected to database but some WordPress tables are missing: ' . implode(', ', $missing_tables),
        ];
      }

      // Get basic statistics
      $posts_table = $this->getTableName('posts');
      $users_table = $this->getTableName('users');
      $post_count = $connection->query("SELECT COUNT(*) FROM {$posts_table} WHERE post_status = 'publish'")->fetchColumn();
      $user_count = $connection->query("SELECT COUNT(*) FROM {$users_table}")->fetchColumn();

      return [
        'status' => 'success',
        'message' => "Successfully connected to WordPress database. Found {$post_count} published posts and {$user_count} users.",
        'stats' => [
          'posts' => $post_count,
          'users' => $user_count,
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'status' => 'error',
        'message' => 'Database connection test failed: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Get WordPress table name with prefix.
   *
   * @param string $table
   *   The table name without prefix.
   *
   * @return string
   *   The table name with prefix.
   */
  public function getTableName($table) {
    $prefix = $this->configFactory->get('wordpress_migrate.settings')->get('database.prefix') ?: 'wp_';
    return $prefix . $table;
  }

  /**
   * Get table prefix.
   *
   * @return string
   *   The table prefix.
   */
  public function getTablePrefix() {
    return $this->configFactory->get('wordpress_migrate.settings')->get('database.prefix') ?: 'wp_';
  }

}
