<?php

namespace Drupal\ncss_about_block\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a LikeForm for collecting user feedback.
 */
class LikeForm extends FormBase {

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

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ncss_about_block_like_form';
  }

  /**
   * {@inheritdoc}
   */




  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'w-100';
    $form['#prefix'] = '<form>';
    $form['#suffix'] = '</form>';

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
      '#markup' => '<h4 class="mb-1">من فضلك أخبرنا بالسبب</h4>',
    ];

    $form['row']['left']['subtitle'] = [
      '#markup' => '<p class="text-muted small mb-3">(يمكنك تحديد خيارات متعددة)</p>',
    ];

    // Checkboxes
    $checkboxes = [
      'checkRelevant' => 'المحتوى ذو صلة',
      'checkWellWritten' => 'لقد كانت مكتوبة بشكل جيد',
      'checkEasyRead' => 'جعل التخطيط من السهل القراءة',
      'checkOther' => 'شيء آخر',
    ];

    foreach ($checkboxes as $id => $label) {
      $form['row']['left'][$id] = [
        '#type' => 'checkbox',
        '#title' => $this->t($label),
        '#title_display' => 'after',
        '#return_value' => 1,
        '#attributes' => [
          'class' => ['form-check-input'],
          'id' => $id,
        ],
        '#prefix' => '<div class="form-check mb-2">',
        '#suffix' => ' </div>',
      ];
    }

    // Gender radios
    $form['row']['left']['gender_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['d-flex', 'align-items-center']],
    ];

    $form['row']['left']['gender_wrapper']['label'] = [
      '#markup' => '<span class="me-3">أنا</span>',
    ];

    $form['row']['left']['gender_wrapper']['male'] = [
      '#type' => 'radio',
      '#title' => $this->t('ذكر'),
      '#return_value' => 'male',
      '#attributes' => [
        'class' => ['form-check-input'],
        'id' => 'genderMale',
        'name' => 'gender',
        'required' => 'required',
      ],
      '#prefix' => '<div class="form-check form-check-inline">',
      '#suffix' => ' </div>',
    ];

    $form['row']['left']['gender_wrapper']['female'] = [
      '#type' => 'radio',
      '#title' => $this->t('أنثى'),
      '#return_value' => 'female',
      '#attributes' => [
        'class' => ['form-check-input'],
        'id' => 'genderFemale',
        'name' => 'gender',
        'required' => 'required',
      ],
      '#prefix' => '<div class="form-check form-check-inline">',
      '#suffix' => ' </div>',
    ];

    // Right column
    $form['row']['right'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['col-md-6']],
    ];

    $form['row']['right']['comment'] = [
      '#type' => 'textarea',
      '#title' => $this->t('تعليق'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['form-control'],
        'id' => 'commentTextarea',
        'rows' => 5,
        'placeholder' => $this->t('النص المدخل'),
      ],
      '#title_attributes' => ['class' => ['form-label']],
      '#prefix' => '<div class="mb-3">',
      '#suffix' => '</div>',
    ];

    // Footer
    $form['footer'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['d-flex', 'justify-content-between', 'align-items-center', 'mt-5']],
    ];

    $form['footer']['info'] = [
      '#markup' => '<div><p class="mb-0 small text-muted">لمزيد من المعلومات يمكنك مراجعة #rules of engagement</a> و #e-participation statement</a></p></div>',
    ];

    $form['footer']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('إرسال'),
      '#attributes' => ['class' => ['btn', 'btn-primary', 'px-4']],
    ];

    return $form;
  }




  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $reasons = array_filter($form_state->getValue('reasons_wrapper') ?? []);
    $gender = $form_state->getValue(['gender_wrapper', 'gender']);
    $comment = $form_state->getValue(['row', 'right_column', 'comment_wrapper', 'comment']);
    $route_name = $form_state->getValue('route_name');

    $data = [
      "reasons" => $reasons,
      "gender" => $gender,
      "comment" => $comment,
    ];

    $this->saveFlag($route_name, 'like', $data);

    $this->messenger->addStatus($this->t('شكراً لتقديمك ملاحظاتك.'));

    \Drupal::logger('ncss_about_block')->notice('Feedback submitted: gender = @gender, reasons = @reasons, comment = @comment, route = @route', [
      '@gender' => $gender,
      '@reasons' => print_r($reasons, TRUE),
      '@comment' => $comment,
      '@route' => $route_name,
    ]);
  }

  /**
   * {@inheritdoc}
   */



  protected function saveFlag($route_name, $type = 'like', $data = null) {
    $flag_id = 'mu_content_like';
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
