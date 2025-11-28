<?php


namespace Drupal\ncss_about_block\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller to unpublish duplicate News nodes.
 */
class UnpublishDuplicatesController extends ControllerBase
{

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager)
  {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Unpublish duplicate News nodes by title.
   */
  public function unpublishDuplicates()
  {
    $storage = $this->entityTypeManager->getStorage('node');

    // Load all news nodes titles.
    $query = $storage->getQuery()
      ->condition('type', 'article')
      ->condition('status', 1) // Only check published nodes
      ->accessCheck(FALSE);

    $nids = $query->execute();
    if (empty($nids)) {
      return new Response("No news nodes found.");
    }

    $nodes = $storage->loadMultiple($nids);

    // Group nodes by identical title.
    $grouped = [];
    foreach ($nodes as $node) {
      $title = trim($node->label());
      $grouped[$title][] = $node;
    }

    $unpublished_count = 0;

    // For each group, keep first -> unpublish the rest.
    foreach ($grouped as $title => $list) {
      if (count($list) > 1) {
        // Keep the first in the list, unpublish the rest.
        array_shift($list); // Remove first

        foreach ($list as $duplicate_node) {
          $duplicate_node->setUnpublished();
          $duplicate_node->save();
          $unpublished_count++;

//          // Log action
//          $this->logger('my_module')->notice(
//            'Unpublished duplicate news node: @nid with title "@title".',
//            ['@nid' => $duplicate_node->id(), '@title' => $title]
//          );
        }
      }
    }

    return new Response("Unpublished $unpublished_count duplicate news nodes.");
  }

}
