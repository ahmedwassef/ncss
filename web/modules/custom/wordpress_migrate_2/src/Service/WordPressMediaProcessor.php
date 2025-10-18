<?php

namespace Drupal\wordpress_migrate_2\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;

/**
 * Service for processing WordPress media files.
 */
class WordPressMediaProcessor {

  /**
   * The WordPress connection service.
   *
   * @var \Drupal\wordpress_migrate\Service\WordPressConnection
   */
  protected $connection;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The file repository service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * WordPress base URL for media files.
   *
   * @var string
   */
  protected $wordpressBaseUrl;

  /**
   * Constructs a WordPressMediaProcessor object.
   *
   * @param \Drupal\wordpress_migrate\Service\WordPressConnection $connection
   *   The WordPress connection service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\file\FileRepositoryInterface $file_repository
   *   The file repository service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(WordPressConnection $connection, FileSystemInterface $file_system, FileRepositoryInterface $file_repository, LoggerChannelFactoryInterface $logger_factory) {
    $this->connection = $connection;
    $this->fileSystem = $file_system;
    $this->fileRepository = $file_repository;
    $this->logger = $logger_factory->get('wordpress_migrate');
  }

  /**
   * Set WordPress base URL for media files.
   *
   * @param string $base_url
   *   The WordPress base URL.
   */
  public function setWordPressBaseUrl($base_url) {
    $this->wordpressBaseUrl = rtrim($base_url, '/');
  }

  /**
   * Process WordPress media file.
   *
   * @param array $wordpress_media
   *   WordPress media data.
   *
   * @return \Drupal\media\Entity\Media|null
   *   Created media entity or NULL on failure.
   */
  public function processMedia(array $wordpress_media) {
    try {
      // Get the file URL from WordPress
      $file_url = $this->getMediaFileUrl($wordpress_media);
      if (!$file_url) {
        $this->logger->warning('No file URL found for WordPress media ID @id', [
          '@id' => $wordpress_media['ID'],
        ]);
        return NULL;
      }

      // Download the file
      $file = $this->downloadFile($file_url, $wordpress_media);
      if (!$file) {
        return NULL;
      }

      // Create media entity
      $media = $this->createMediaEntity($file, $wordpress_media);

      if ($media) {
        $this->logger->info('Successfully processed WordPress media ID @wp_id as Drupal media ID @drupal_id', [
          '@wp_id' => $wordpress_media['ID'],
          '@drupal_id' => $media->id(),
        ]);
      }

      return $media;
    }
    catch (\Exception $e) {
      $this->logger->error('Error processing WordPress media ID @id: @message', [
        '@id' => $wordpress_media['ID'],
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Get media file URL from WordPress data.
   *
   * @param array $wordpress_media
   *   WordPress media data.
   *
   * @return string|null
   *   File URL or NULL if not found.
   */
  protected function getMediaFileUrl(array $wordpress_media) {
    // Try to get the file URL from post meta
    if (isset($wordpress_media['meta']['_wp_attached_file'])) {
      $attached_file = $wordpress_media['meta']['_wp_attached_file'];
      // If already absolute (http...), return as-is, otherwise prepend base URL
      if (preg_match('#^https?://#', $attached_file)) {
        return $attached_file;
      } else {
        // Remove any leading slash for consistency
        $attached_file = ltrim($attached_file, '/');
        // Use set base URL (from config form) or fallback to guid domain if available
        if (!empty($this->wordpressBaseUrl)) {
          return $this->wordpressBaseUrl . '/wp-content/uploads/' . $attached_file;
        } else if (!empty($wordpress_media['guid']) && preg_match('#^https?://[^/]+#', $wordpress_media['guid'], $match)) {
          // Use guid base domain
          return $match[0] . '/wp-content/uploads/' . $attached_file;
        }
      }
    }

    // Fallback to guid if present and is a URL
    if (!empty($wordpress_media['guid'])) {
      $guid = $wordpress_media['guid'];
      if (preg_match('#^https?://#', $guid)) {
        return $guid;
      }
    }

    return NULL;
  }

  /**
   * Download file from WordPress.
   *
   * @param string $url
   *   File URL.
   * @param array $wordpress_media
   *   WordPress media data.
   *
   * @return \Drupal\file\Entity\File|null
   *   File entity or NULL on failure.
   */
  protected function downloadFile($url, array $wordpress_media) {
    try {
      // Get file extension and name
      $path_info = pathinfo(parse_url($url, PHP_URL_PATH));
      $extension = isset($path_info['extension']) ? $path_info['extension'] : '';
      $filename = $path_info['basename'];

      // Use WordPress title if available
      if (!empty($wordpress_media['post_title'])) {
        $title = $wordpress_media['post_title'];
        $filename = $this->sanitizeFilename($title) . '.' . $extension;
      }

      // Create directory if it doesn't exist
      $directory = 'public://wordpress-migrate';
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      // Download the file
      $data = file_get_contents($url);
      if ($data === FALSE) {
        throw new \Exception('Failed to download file from URL: ' . $url);
      }

      // Save the file
      $uri = $directory . '/' . $filename;
      $file = $this->fileRepository->writeData($data, $uri, FileSystemInterface::EXISTS_REPLACE);

      if ($file) {
        $this->logger->info('Downloaded file: @filename', [
          '@filename' => $filename,
        ]);
      }

      return $file;
    }
    catch (\Exception $e) {
      $this->logger->error('Error downloading file from @url: @message', [
        '@url' => $url,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Create media entity from file.
   *
   * @param \Drupal\file\Entity\File $file
   *   File entity.
   * @param array $wordpress_media
   *   WordPress media data.
   *
   * @return \Drupal\media\Entity\Media|null
   *   Media entity or NULL on failure.
   */
  protected function createMediaEntity(File $file, array $wordpress_media) {
    try {
      // Determine media bundle based on file type
      $mime_type = $file->getMimeType();
      $bundle = $this->getMediaBundle($mime_type);

      if (!$bundle) {
        $this->logger->warning('No media bundle found for MIME type @mime', [
          '@mime' => $mime_type,
        ]);
        return NULL;
      }

      // Create media entity
      $media = Media::create([
        'bundle' => $bundle,
        'name' => $wordpress_media['post_title'] ?: $file->getFilename(),
        'field_media_file' => [
          'target_id' => $file->id(),
        ],
      ]);

      // Add alt text if available
      if (isset($wordpress_media['meta']['_wp_attachment_image_alt'])) {
        $media->set('field_media_image', [
          'target_id' => $file->id(),
          'alt' => $wordpress_media['meta']['_wp_attachment_image_alt'],
        ]);
      }

      // Add description
      if (!empty($wordpress_media['post_content'])) {
        $media->set('field_media_description', $wordpress_media['post_content']);
      }

      $media->save();

      return $media;
    }
    catch (\Exception $e) {
      $this->logger->error('Error creating media entity: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Get media bundle based on MIME type.
   *
   * @param string $mime_type
   *   MIME type.
   *
   * @return string|null
   *   Media bundle name or NULL if not found.
   */
  protected function getMediaBundle($mime_type) {
    $mime_bundles = [
      'image' => 'image',
      'video' => 'video',
      'audio' => 'audio',
      'application/pdf' => 'document',
    ];

    $type = explode('/', $mime_type)[0];
    return isset($mime_bundles[$type]) ? $mime_bundles[$type] : 'file';
  }

  /**
   * Sanitize filename.
   *
   * @param string $filename
   *   Original filename.
   *
   * @return string
   *   Sanitized filename.
   */
  protected function sanitizeFilename($filename) {
    // Remove special characters and replace spaces with underscores
    $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $filename);
    $filename = preg_replace('/_+/', '_', $filename);
    $filename = trim($filename, '_');

    return $filename;
  }

}
