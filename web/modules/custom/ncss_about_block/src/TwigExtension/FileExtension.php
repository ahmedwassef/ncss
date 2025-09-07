<?php


namespace Drupal\ncss_about_block\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class FileExtension extends AbstractExtension
{


  /**
   * @return array|\Twig\TwigFilter[]
   */
  public function getFilters() {
    return [
      new TwigFilter('ext', [$this, 'ext']),
    ];
  }


  public function ext($filepath){
    $ext = pathinfo($filepath, PATHINFO_EXTENSION);
    return $ext;
  }

}
