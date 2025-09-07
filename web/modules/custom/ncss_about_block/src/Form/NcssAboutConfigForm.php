<?php

namespace Drupal\ncss_about_block\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\Entity\File;

class NcssAboutConfigForm extends ConfigFormBase
{

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new NcssAboutConfigForm.
   */
  public function __construct(LanguageManagerInterface $language_manager)
  {
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'ncss_about_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return ['ncss_about_block.settings'];
  }

  /**
   * {@inheritdoc}
   */

  public function buildForm(array $form, FormStateInterface $form_state) {
    $languages = $this->languageManager->getLanguages();
    $config = $this->config('ncss_about_block.settings');

    foreach ($languages as $langcode => $language) {
      $form[$langcode] = [
        '#type' => 'details',
        '#title' => $language->getName(),
        '#open' => $langcode === 'en',
        '#tree' => TRUE, // 👈 keep values nested under language key
      ];

      // Basic info.
      $form[$langcode]['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#default_value' => $config->get("$langcode.title") ?? '',
      ];

      $form[$langcode]['image'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Image'),
        '#default_value' => $config->get("$langcode.image") ? [$config->get("$langcode.image")] : NULL,
        '#description' => $this->t('Upload an image for the about section.'),
      ];

      $form[$langcode]['description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#default_value' => $config->get("$langcode.description") ?? '',
      ];
      $form[$langcode]['button_text'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Button text'),
        '#default_value' => $config->get("$langcode.button_text") ?? '',
      ];
      $form[$langcode]['button_url'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Button URL'),
        '#default_value' => $config->get("$langcode.button_url") ?? '',
      ];
      $form[$langcode]['features'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Features (one per line)'),
        '#default_value' => $config->get("$langcode.features") ?? '',
      ];
      $form[$langcode]['image_url'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Image URL'),
        '#default_value' => $config->get("$langcode.image_url") ?? '',
      ];

      // Social links.
      $form[$langcode]['social_links'] = [
        '#type' => 'details',
        '#title' => $this->t('Social Links'),
        '#open' => FALSE,
        '#tree' => TRUE,
      ];
      $form[$langcode]['social_links']['facebook'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Facebook URL'),
        '#default_value' => $config->get("$langcode.social_links.facebook") ?? '',
      ];
      $form[$langcode]['social_links']['twitter'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Twitter URL'),
        '#default_value' => $config->get("$langcode.social_links.twitter") ?? '',
      ];
      $form[$langcode]['social_links']['linkedin'] = [
        '#type' => 'textfield',
        '#title' => $this->t('LinkedIn URL'),
        '#default_value' => $config->get("$langcode.social_links.linkedin") ?? '',
      ];
      $form[$langcode]['social_links']['instagram'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Instagram URL'),
        '#default_value' => $config->get("$langcode.social_links.instagram") ?? '',
      ];
      $form[$langcode]['social_links']['youtube'] = [
        '#type' => 'textfield',
        '#title' => $this->t('YouTube URL'),
        '#default_value' => $config->get("$langcode.social_links.youtube") ?? '',
      ];

      // Contact info.
      $form[$langcode]['contact_info'] = [
        '#type' => 'details',
        '#title' => $this->t('Contact Us'),
        '#open' => FALSE,
        '#tree' => TRUE,
      ];
      $form[$langcode]['contact_info']['email'] = [
        '#type' => 'email',
        '#title' => $this->t('Email'),
        '#default_value' => $config->get("$langcode.contact_info.email") ?? '',
      ];
      $form[$langcode]['contact_info']['phone'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Phone'),
        '#default_value' => $config->get("$langcode.contact_info.phone") ?? '',
      ];
      $form[$langcode]['contact_info']['address'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Address'),
        '#default_value' => $config->get("$langcode.contact_info.address") ?? '',
      ];
      $form[$langcode]['contact_info']['map_url'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Google Maps URL'),
        '#default_value' => $config->get("$langcode.contact_info.map_url") ?? '',
      ];
    }

    return parent::buildForm($form, $form_state);
  }



  public function submitForm(array &$form, FormStateInterface $form_state) {
    $languages = $this->languageManager->getLanguages();
    $config = $this->configFactory->getEditable('ncss_about_block.settings');
    $values = $form_state->getValues();

    foreach ($languages as $langcode => $language) {
      $config->set("$langcode.title", $values[$langcode]['title']);
      $config->set("$langcode.description", $values[$langcode]['description']);
      $config->set("$langcode.button_text", $values[$langcode]['button_text']);
      $config->set("$langcode.button_url", $values[$langcode]['button_url']);
      $config->set("$langcode.features", $values[$langcode]['features']);
      $config->set("$langcode.social_links", $values[$langcode]['social_links']);
      $config->set("$langcode.contact_info", $values[$langcode]['contact_info']);

      // Handle image upload.
      if (!empty($values[$langcode]['image'][0])) {
        $fid = $values[$langcode]['image'][0];
        $file = \Drupal\file\Entity\File::load($fid);
        if ($file) {
          $file->setPermanent();
          $file->save();
          $config->set("$langcode.image", $fid);
        }
      }
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }
}
