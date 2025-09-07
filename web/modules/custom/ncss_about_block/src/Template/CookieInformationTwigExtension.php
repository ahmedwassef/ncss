<?php

namespace Drupal\ncss_about_block\Template;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides a Twig extension for retrieving cookie information.
 */
class CookieInformationTwigExtension extends AbstractExtension {
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
   * Constructs a new CookieInformationTwigExtension.
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
      new TwigFunction('cookie_information', [$this, 'getCookieInformation']),
    ];
  }

  /**
   * Retrieves cookie information based on the current language.
   *
   * @return array
   *   An associative array containing:
   *   - title: The cookie information title.
   *   - description: The cookie information description.
   *   - more_details_link: A link for more details about cookies.
   */
  public function getCookieInformation() {
    $config = $this->configFactory->get('cookie_information.settings');
    $current_lang = $this->languageManager->getCurrentLanguage()->getId();

    $title = ($current_lang === 'ar') ? $config->get('title_ar') : $config->get('title');
    $description = ($current_lang === 'ar') ? $config->get('description_ar') : $config->get('description');
    $link = ($current_lang === 'ar') ? $config->get('more_details_link') : $config->get('en_more_details_link');

    return [
      'title' => $title,
      'description' => $description,
      'more_details_link' => $link,
    ];
  }
}
