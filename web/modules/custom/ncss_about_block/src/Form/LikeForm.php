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

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $route_name = NULL) {
    $form['#attributes']['class'][] = 'w-100';

    $form['route_name'] = [
      '#type' => 'hidden',
      '#value' => $route_name,
    ];

    // Create the row container
    $form['row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['row']],
    ];

    // Left column (col-md-6)
    $form['row']['left_column'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['col-md-6']],
    ];

    $form['row']['left_column']['title'] = [
      '#markup' => '<h4 class="mb-1">' . $this->t('من فضلك أخبرنا بالسبب') . '</h4>',
    ];

    $form['row']['left_column']['subtitle'] = [
      '#markup' => '<p class="text-muted small mb-3">' . $this->t('(يمكنك تحديد خيارات متعددة)') . '</p>',
    ];

    // Checkboxes
    $form['row']['left_column']['reasons_wrapper'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    $form['row']['left_column']['reasons_wrapper']['relevant'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('المحتوى ذو صلة'),
      '#return_value' => 1,
      '#prefix' => '<div class="form-check mb-2">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['form-check-input'], 'id' => 'checkRelevant'],
      '#title_display' => 'after',
      '#label_attributes' => ['class' => ['form-check-label'], 'for' => 'checkRelevant'],
    ];

    $form['row']['left_column']['reasons_wrapper']['well_written'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('لقد كانت مكتوبة بشكل جيد'),
      '#return_value' => 1,
      '#prefix' => '<div class="form-check mb-2">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['form-check-input'], 'id' => 'checkWellWritten'],
      '#title_display' => 'after',
      '#label_attributes' => ['class' => ['form-check-label'], 'for' => 'checkWellWritten'],
    ];

    $form['row']['left_column']['reasons_wrapper']['easy_read'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('جعل التخطيط من السهل القراءة'),
      '#return_value' => 1,
      '#prefix' => '<div class="form-check mb-2">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['form-check-input'], 'id' => 'checkEasyRead'],
      '#title_display' => 'after',
      '#label_attributes' => ['class' => ['form-check-label'], 'for' => 'checkEasyRead'],
    ];

    $form['row']['left_column']['reasons_wrapper']['other'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('شيء آخر'),
      '#return_value' => 1,
      '#prefix' => '<div class="form-check mb-4">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['form-check-input'], 'id' => 'checkOther'],
      '#title_display' => 'after',
      '#label_attributes' => ['class' => ['form-check-label'], 'for' => 'checkOther'],
    ];

    // Gender selection
    $form['row']['left_column']['gender_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['d-flex', 'align-items-center']],
    ];

    $form['row']['left_column']['gender_wrapper']['label'] = [
      '#markup' => '<span class="me-3">' . $this->t('أنا') . '</span>',
    ];

    $form['row']['left_column']['gender_wrapper']['gender'] = [
      '#type' => 'radios',
      '#options' => [
        'male' => $this->t('ذكر'),
        'female' => $this->t('أنثى'),
      ],
      '#required' => TRUE,
      '#attributes' => ['class' => ['form-check-inline']],
      '#prefix' => '<div class="form-check form-check-inline">',
      '#suffix' => '</div>',
      '#title_display' => 'invisible',
      '#id' => 'gender-radios',
    ];

    // Right column (col-md-6)
    $form['row']['right_column'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['col-md-6']],
    ];

    // Comment textarea
    $form['row']['right_column']['comment_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['mb-3']],
    ];

    $form['row']['right_column']['comment_wrapper']['comment'] = [
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
    ];

    // Footer with info text and submit button
    $form['footer'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['d-flex', 'justify-content-between', 'align-items-center', 'mt-5']],
    ];

    $form['footer']['info_text'] = [
      '#markup' => '<div><p class="mb-0 small text-muted">' .
        $this->t('لمزيد من المعلومات يمكنك مراجعة <a href="#">rules of engagement</a> و <a href="#">e-participation statement</a>') .
        '</p></div>',
    ];

    $form['footer']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('إرسال'),
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'px-4'],
      ],
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
