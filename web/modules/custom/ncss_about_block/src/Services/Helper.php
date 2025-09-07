<?php

namespace Drupal\ncss_about_block\Services;


use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileRepositoryInterface;
use Drupal\paragraphs\Entity\Paragraph;

class Helper
{

  protected FileSystemInterface $fileSystem;
  protected FileRepositoryInterface$fileRepository;

  public function __construct(FileSystemInterface $file_system, FileRepositoryInterface $file_repository) {
    $this->fileSystem = $file_system;
    $this->fileRepository = $file_repository;
  }

  public static function create(ContainerInterface$container): self {
    return new static(
      $container->get('file_system'),
      $container->get('file.repository')
    );
  }

  /*
   * filtering an array
   */
  public function filter_by_value ($array, $index, $value){
    if(is_array($array) && count($array)>0)
    {
      foreach(array_keys($array) as $key){
        $temp[$key] = $array[$key][$index];

        if ($temp[$key] == $value){
          $newarray[$key] = $array[$key];
        }
      }
    }
    return $newarray;
  }





  public  function GetParagraphIdFromOldId($old_id ,$paragraph_type){
    $query = \Drupal::entityQuery('paragraph');
    $query->condition('type', $paragraph_type);
    $query->condition('field_paragraph_old_id', $old_id);
    $query->accessCheck(FALSE);
    $tids = $query->execute();
    $new_id =current($tids);
    $pargraph = Paragraph::load($new_id);
    if($pargraph)
    {
      $paragraph_revision_id =  $pargraph->getRevisionId();
      $new_paragraph['target_revision_id']=$paragraph_revision_id;
    }
    $new_paragraph['target_id']=$new_id;
    return $new_paragraph;
  }



  public function  GetAllNidsFromSpecificContent($type){
    $query = \Drupal::database()
      ->select('node_field_data', 'n');
    $query = $query->fields('n', ['nid']);
    $query->condition('type', $type);
    $query = $query->distinct();
    $result = $query->execute()->fetchall();
    return $result;

  }

  public function  GetAllNidFromTitleType($title,$type){
    $query = \Drupal::database()
      ->select('node_field_data', 'n');
    $query = $query->fields('n', ['nid']);
    $query->condition('type', $type);
    $query->condition('title', $title);
    $result = $query->execute()->fetchObject();
    return $result->nid;

  }

  public function getLikesCount($nid){
    $query = \Drupal::database()
      ->select('flag_counts', 'counts');
    $query = $query->fields('counts',['count']);
    $query->condition('entity_route', $nid);
    $query->condition('flag_id','mu_content_like');
    return $query->execute()->fetchAssoc()['count'] ?? 0;
  }

  public function getDisLikesCount($nid){
    $query = \Drupal::database()
      ->select('flag_counts', 'counts');
    $query = $query->fields('counts',['count']);
    $query->condition('entity_route', $nid);
    $query->condition('flag_id','mu_content_dislike');

    return $query->execute()->fetchAssoc()['count'] ?? 0;
  }

  public function getVisitorCount($path) {
    $query = \Drupal::database()
      ->select('visitors', 'v')
      ->condition('visitors_path', $path);

    $query->addExpression('COUNT(*)', 'count');

    $count = $query->execute()->fetchField();

    return (int) $count;
  }

  /**
   * Get all statistics items for a specific authority.
   *
   * @param int $authority_id
   *   The authority ID to filter by.
   *
   * @return array
   *   An array of matched statistics items.
   */
public function getStatisticsByAuthority($authority_id) {
    $config = \Drupal::config('ncss_about_block.statistics');
    $items = $config->get('items') ?? [];

    $filtered_items = [];
    foreach ($items as $item) {
      // Filter by authority_id
      if (isset($item['authority_id']) && $item['authority_id'] == $authority_id) {
        // Load icon URL
        $icon_url = '';
        if (!empty($item['icon'][0])) {
          $file = File::load($item['icon'][0]);
          if ($file) {
            $icon_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
          }
        }

        $filtered_items[] = [
          'title_en' => $item['title_en'] ?? '',
          'title_ar' => $item['title_ar'] ?? '',
          'number' => $item['number'] ?? '',
          'unique_key' => $item['unique_key'] ?? '',
          'promote' => !empty($item['promote']),
          'icon_url' => $icon_url,
        ];
      }
    }

    return $filtered_items;
  }
public function getFrontStatistics() {
    $config = \Drupal::config('ncss_about_block.statistics');
    $items = $config->get('items') ?? [];
    $current_lang = \Drupal::languageManager()->getCurrentLanguage()->getId(); // e.g., 'en' or 'ar'

    $filtered_items = [];
    foreach ($items as $item) {
      // Filter by authority_id
      if (isset($item['promote']) && $item['promote']) {
        // Load icon URL
        $icon_url = '';
        if (!empty($item['icon'][0])) {
          $file = File::load($item['icon'][0]);
          if ($file) {
            $icon_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
          }
        }

        // Determine the title based on language
        $title = $current_lang === 'ar' ? ($item['title_ar'] ?? '') : ($item['title_en'] ?? '');

        $filtered_items[] = [
          'title' => $title,
          'title_en' => $item['title_en'] ?? '',
          'title_ar' => $item['title_ar'] ?? '',
          'number' => $item['number'] ?? '',
          'unique_key' => $item['unique_key'] ?? '',
          'promote' => !empty($item['promote']),
          'icon_url' => $icon_url,
        ];
      }
    }

    return $filtered_items;
  }



  /**
   * Download and save an image from a remote URL into Drupal's file system.
   *
   * @param string $image_url
   *   The absolute URL of the image (e.g., https://example.com/image.jpg).
   * @param string $destination_scheme
   *   The Drupal file scheme to save the file under ('public' or 'private').
   * @param string|null $custom_filename
   *   Optional custom file name to save as.
   *
   * @return \Drupal\file\Entity\File|null
   *   Returns the saved File entity or NULL on failure.
   */
  public function save_image_from_url(string $image_url, string $scheme = 'public', ?string $custom_filename = null): ?File {
    try {
      $file_contents = file_get_contents($image_url);
      if ($file_contents === false) {
        \Drupal::logger('ncss_about_block')->error('Failed to download image from @url', ['@url' => $image_url]);
        return null;
      }

      $filename = $custom_filename ?: basename(parse_url($image_url, PHP_URL_PATH));
      $destination = $scheme . '://' . $filename;
      $destination = $this->fileSystem->getDestinationFilename($destination, FileSystemInterface::EXISTS_RENAME);

      $file = $this->fileRepository->writeData($file_contents, $destination, FileSystemInterface::EXISTS_REPLACE);
      if ($file) {
        $file->setPermanent();
        $file->save();
        return $file;
      }

    } catch (\Exception $e) {
      \Drupal::logger('ncss_about_block')->error('Image save failed: @message', ['@message' => $e->getMessage()]);
    }

    return null;
  }
}


