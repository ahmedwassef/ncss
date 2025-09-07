<?php

namespace Drupal\ncss_about_block\Template;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides a Twig extension for retrieving route name  .
 */
class RouteNameTwigExtension extends AbstractExtension {
  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new RouteNameTwigExtension.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, LanguageManagerInterface $languageManager) {
    $this->configFactory = $configFactory;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('routeName', [$this, 'getRouteName']),
    ];
  }


  public function getRouteName() {
    $route_name = \Drupal::routeMatch()->getRouteName();

    $params = \Drupal::routeMatch()->getRawParameters()->all();

    $route_with_params = $route_name;

// نضيف الباراميترز كسلسلة نصية
    if (!empty($params)) {
      foreach ($params as $key => $value) {
        $route_with_params .= '_' . $key . '_' . $value;
      }
    }

    return [
      'route_name' => $route_name,
      'route_parameters' => $route_with_params,
    ];
  }
}
