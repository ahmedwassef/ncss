<?php


namespace Drupal\ncss_about_block\TwigExtension;

use Drupal\node\Entity\Node;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class NodeLikes extends AbstractExtension
{

  public function getFunctions()
  {
    return [
      new TwigFunction('get_node_likes', [$this, 'getNodeLikesCount']),
      new TwigFunction('get_node_dislikes', [$this, 'getNodeDisLikesCount']),
      new TwigFunction('get_node_satisfaction', [$this, 'getNodeSatisfactionIndex']),
      new TwigFunction('get_page_visitor_count', [$this, 'getPageVisitorCount']),
    ];
  }


  public function getNodeLikesCount($nid)
  {
    return \Drupal::service('ncss_about_block.helper_service')->getLikesCount($nid);
  }
  public function getNodeSatisfactionIndex($nid)
  {
    $likes=$this->getNodeLikesCount($nid);
    $dislike=$this->getNodeDisLikesCount($nid);
    return  $likes?round(($likes/($likes+$dislike))*100):0;
  }


  public function getPageVisitorCount()
  {
    $current_path = \Drupal::service('path.current')->getPath();
    return \Drupal::service('ncss_about_block.helper_service')->getVisitorCount($current_path);

  }

  public function getNodeDisLikesCount($nid)
  {
    return \Drupal::service('ncss_about_block.helper_service')->getDisLikesCount($nid);
  }
}
