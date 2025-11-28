<?php

namespace Drupal\ncss_about_block\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Provides a Dislike form.
 */
class DisLikeForm extends FormBase {

  protected $database;
  protected $routeMatch;
  protected $messenger;

  public function __construct(Connection $database, RouteMatchInterface $route_match, MessengerInterface $messenger) {
    $this->database = $database;
    $this->routeMatch = $route_match;
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('current_route_match'),
      $container->get('messenger')
    );
  }

  public function getFormId() {
    $nid = $this->routeMatch->getParameter('nid') ?? 0;
    return 'dislike_form_' . $nid;
  }

  public function buildForm(array $form, FormStateInterface $form_state,$route_parameters = NULL) {
    $form['#attributes']['class'] = ['w-100'];
    $form['#prefix'] = '<form>';
    $form['#suffix'] = '</form>';

    // Store current route name
    $form['route_name'] = [
      '#type' => 'hidden',
      '#value' => $route_parameters,
    ];

    // Row container
    $form['row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['row']],
    ];

    // Left column
    $form['row']['left'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['col-md-6']],
    ];

    $form['row']['left']['title'] = [
      '#markup' => '<h4 class="mb-1">' . $this->t('Please tell us the reason') . '</h4>',
    ];

    $form['row']['left']['subtitle'] = [
      '#markup' => '<p class="text-muted small mb-3">' . $this->t('(You can select multiple options)') . '</p>',
    ];

    // Checkboxes
    $checkboxes = [
      'technical_issue' => 'There is a technical issue',
      'no_relevant_answer' => 'Couldn’t find a relevant answer',
      'poorly_written' => 'Poorly written content',
      'checkOther' => 'Others',
    ];

    $form['row']['left']['reasons_wrapper'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    foreach ($checkboxes as $id => $label) {
      $form['row']['left']['reasons_wrapper'][$id] = [
        '#type' => 'checkbox',
        '#title' => '',
        '#return_value' => 1,
        '#attributes' => [
          'class' => ['form-check-input'],
          'id' => $id,
        ],
        '#prefix' => '<div class="form-check mb-2">',
        '#suffix' => '<label class="form-check-label" for="' . $id . '">' . $this->t($label) . '</label></div>',
        '#theme_wrappers' => [],
      ];
    }

    // Gender radios
    $form['row']['left']['gender_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['d-flex', 'align-items-center']],
    ];

    $form['row']['left']['gender_wrapper']['label'] = [
      '#markup' => '<span class="me-3">' . $this->t('I am') . '</span>',
    ];

    $form['row']['left']['gender_wrapper']['male'] = [
      '#type' => 'radio',
      '#title' => '',
      '#return_value' => 'male',
      '#attributes' => [
        'class' => ['form-check-input'],
        'id' => 'genderMale',
        'name' => 'gender',
        'required' => 'required',
      ],
      '#prefix' => '<div class="form-check form-check-inline">',
      '#suffix' => '<label class="form-check-label" for="genderMale">' . $this->t('Male') . '</label></div>',
      '#theme_wrappers' => [],
    ];

    $form['row']['left']['gender_wrapper']['female'] = [
      '#type' => 'radio',
      '#title' => '',
      '#return_value' => 'female',
      '#attributes' => [
        'class' => ['form-check-input'],
        'id' => 'genderFemale',
        'name' => 'gender',
        'required' => 'required',
      ],
      '#prefix' => '<div class="form-check form-check-inline">',
      '#suffix' => '<label class="form-check-label" for="genderFemale">' . $this->t('Female') . '</label></div>',
      '#theme_wrappers' => [],
    ];

    // Right column
    $form['row']['right'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['col-md-6']],
    ];

    $form['row']['right']['notes'] = [
      '#type' => 'textarea',
      '#title' => '',
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['form-control'],
        'id' => 'commentTextarea',
        'rows' => 5,
        'placeholder' => $this->t('Enter your comment'),
      ],
      '#title_attributes' => ['class' => ['form-label']],
      '#prefix' => '<div class="mb-3"><label for="commentTextarea" class="form-label">' . $this->t('Comment') . '</label>',
      '#suffix' => '</div>',
      '#theme_wrappers' => [],
    ];


    // Footer
    $form['footer'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['d-flex', 'justify-content-between', 'align-items-center', 'mt-5']],
    ];

    $form['footer']['info'] = [
      '#markup' => '<div><p class="mb-0 small text-muted">' .
        $this->t('For more information, please review <a href="/node/60">rules of engagement</a> and <a href="/node/60">e-participation statement</a>.') .
        '</p></div>',
    ];

    $form['footer']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => ['class' => ['btn', 'btn-primary', 'px-4']],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $reasons = array_filter($form_state->getValue('reasons_wrapper') ?? []);
    $gender = $form_state->getValue('gender');
    $notes = $form_state->getValue('notes');
    $route_name = $form_state->getValue('route_name');

    $data = [
      "reasons" => $reasons,
      "gender" => $gender,
      "notes" => $notes,
    ];

     $this->saveFlag($route_name, 'dislike', $data);

    $this->messenger->addStatus($this->t('You disliked this content.'));
  }

  protected function saveFlag($route_name, $type = 'dislike', $data = null) {
    $flag_id = 'mu_content_dislike';
    $timestamp = \Drupal::time()->getCurrentTime();
    $uid = \Drupal::currentUser()->id();
    $ip_address = \Drupal::request()->getClientIp();
    $json_data = $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;

    // Update flag_counts
    $existing = $this->database->select('flag_counts', 'fc')
      ->fields('fc', ['id'])
      ->condition('entity_route', $route_name)
      ->condition('flag_id', $flag_id)
      ->execute()
      ->fetchField();

    if ($existing) {
      $this->database->update('flag_counts')
        ->expression('count', 'count + 1')
        ->fields(['created' => $timestamp])
        ->condition('id', $existing)
        ->execute();
    } else {
      $this->database->insert('flag_counts')
        ->fields([
          'entity_route' => $route_name,
          'flag_id' => $flag_id,
          'count' => 1,
          'created' => $timestamp,
        ])
        ->execute();
    }

    // Insert individual submission
    $this->database->insert('flag_submissions')
      ->fields([
        'entity_route' => $route_name,
        'flag_id' => $flag_id,
        'type' => $type,
        'uid' => $uid,
        'ip_address' => $ip_address,
        'data' => $json_data,
        'created' => $timestamp,
      ])
      ->execute();
  }
}
