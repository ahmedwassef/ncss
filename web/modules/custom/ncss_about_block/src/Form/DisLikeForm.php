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

  public function buildForm(array $form, FormStateInterface $form_state, $route_name = NULL) {


    $form['#attributes']['class'][] = 'w-[300px] bg-white p-3 text-start font-bold text-gray-500';

    $form['route_name'] = [
      '#type' => 'hidden',
      '#value' => $route_name,
    ];

    $form['reason_title'] = [
      '#markup' => '<p class="mb-3">'.$this->t('Please Write Your Notes').'</p><div class="my-4 flex items-end">
      <hr class="w-[90px] border-t-[2px] border-secondary-500">
      <hr class="flex-1">
    </div><br>',
    ];

    $form['reasons_wrapper'] = [
      '#type' => 'container',
      '#tree' => TRUE, // This enables nested structure
    ];

    $form['reasons_wrapper']['technical_issue'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('There is a technical issue'),
      '#return_value' => 'technical_issue',
      '#prefix' => '<div class="flex items-center gap-2">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['checkbox', 'checkbox-primary']],
    ];

    $form['reasons_wrapper']['no_relevant_answer'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Couldn’t find a relevant answer'),
      '#return_value' => 'no_relevant_answer',
      '#prefix' => '<div class="flex items-center gap-2">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['checkbox', 'checkbox-primary']],
    ];

    $form['reasons_wrapper']['poorly_written'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Poorly written content'),
      '#return_value' => 'written',
      '#prefix' => '<div class="flex items-center gap-2">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['checkbox', 'checkbox-primary']],
    ];

    $form['reasons_wrapper']['hard_to_read_design'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Design made it hard to read'),
      '#return_value' =>'hard_to_read_design',
      '#prefix' => '<div class="flex items-center gap-2">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['checkbox', 'checkbox-primary']],
    ];

    $form['reasons_wrapper']['other'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Other'),
      '#return_value' => 'other',
      '#prefix' => '<div class="flex items-center gap-2">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['checkbox', 'checkbox-primary']],
    ];

    $form['gender'] = [
      '#type' => 'radios',
      '#title' => $this->t('I am '),
      '#options' => [
        'male' => $this->t('Male'),
        'female' => $this->t('Female'),
      ],
      '#attributes' => ['class' => ['flex', 'flex-col', 'gap-2']],
    ];

    $form['notes'] = [
      '#type' => 'textarea',
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['textarea', 'textarea-primary', 'w-full', 'mt-4'],
        'placeholder' =>$this->t('Notes'),
        'label' =>$this->t('Notes'),
        'aria-label' =>$this->t('Notes'),
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['yes'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#attributes' => [
        'class' => ['gradient-primary', 'btn', 'btn-sm', 'mt-3', 'px-8', 'font-bold', 'text-white'],
      ],
      '#submit' => ['::submitForm'],
    ];

    $form['actions']['no'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#attributes' => [
        'class' => ['gradient-secondary', 'btn', 'btn-sm', 'mt-3', 'px-8', 'font-bold', 'text-white'],
        'data-overlay' => '#page-dislike-modal',
      ],
    ];


    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    $reasons = array_filter($form_state->getValue('reasons_wrapper') ?? []);

    $gender = $form_state->getValue('gender');
    $notes = $form_state->getValue('notes');
    $route_name = $form_state->getValue('route_name');
    $data=[
      "reasons"=>$reasons,
      "gender"=>$gender,
      "notes"=>$notes,
    ];
    $this->saveFlag($route_name, 'dislike',$data);

    // Example: log the values (replace with saving to DB or another action as needed)
    \Drupal::logger('ncss_about_block')->notice('Feedback submitted: rating = @rating, gender = @gender, reasons = @reasons, notes = @notes, route = @route', [
      '@rating' => 1,
      '@gender' => $gender,
      '@reasons' => implode(', ', $reasons),
      '@notes' => $notes,
      '@route' => $route_name,
    ]);

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
