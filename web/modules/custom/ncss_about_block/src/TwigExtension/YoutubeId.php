<?php


namespace Drupal\ncss_about_block\TwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class YoutubeID extends AbstractExtension
{


    /**
     * @return array|\Twig\TwigFilter[]
     */
    public function getFilters() {
        return [
            new TwigFilter('ncss_about_block_youtube_id', array($this,'getID'))
        ];
    }

    /**
     * @return string
     */
    public function getName() {
        return 'ncss_about_block_youtube_id';
    }

    /**
     * @param $nid
     *
     * @return mixed
     */
    function getID( $url){
        preg_match('/(http(s|):|)\/\/(www\.|)yout(.*?)\/(embed\/|watch.*?v=|)([a-z_A-Z0-9\-]{11})/i', $url, $results);
        if(isset($results[6])){
          return $results[6];

        }
    }

}
