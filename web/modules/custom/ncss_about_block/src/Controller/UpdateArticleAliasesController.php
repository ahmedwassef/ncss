<?php

namespace Drupal\ncss_about_block\Controller;

use Drupal\Core\Controller\ControllerBase;

class UpdateArticleAliasesController extends ControllerBase {

  public function updateArticleAliases() {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $nids = $storage->getQuery()
      ->condition('type', 'article')
      ->accessCheck(FALSE)
      ->execute();
    if (empty($nids)) {
      return ['#markup' => '<p>No article nodes found.</p>'];
    }
    $nodes = $storage->loadMultiple($nids);

    $alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
    $updated = [];

    foreach ($nodes as $node) {
      $source = '/node/' . $node->id();
      $title = $node->label();
      $slug = $this->slugify($title);
      $alias = '/' . $slug;
      $langcode = $node->language()->getId();

      // Optionally delete existing aliases for this source & lang
      $existing = $alias_storage->loadByProperties([
        'path' => $source,
        'langcode' => $langcode,
      ]);
      if (!empty($existing)) {
        $alias_storage->delete($existing);
      }

      // Create new alias entity
      $pa = $alias_storage->create([
        'path' => $source,
        'alias' => $alias,
        'langcode' => $langcode,
      ]);
      $pa->save();

      $updated[] = "Node {$node->id()} → $alias";
    }

    return [
      '#theme' => 'item_list',
      '#title' => 'Updated Article Aliases',
      '#items' => $updated,
    ];
  }

  private function slugify($title) {
    $slug = mb_strtolower($title);
    $slug = preg_replace('/[^\p{Arabic}A-Za-z0-9]+/u', '-', $slug);
    return trim($slug, '-');
  }
}
