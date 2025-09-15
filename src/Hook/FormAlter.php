<?php

namespace Drupal\node_temporary\Hook;

use DateTime;
use DateTimeZone;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node_temporary\NodeTemporaryHelper;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Generic form alter hook implementation for the Node Temporary module.
 */
class FormAlter {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    private ConfigFactoryInterface $configFactory,
    private RequestStack $requestStack,
    private MessengerInterface $messenger,
    private AccountInterface $currentUser,
    private NodeTemporaryHelper $nodeTemporaryHelper,
  ) {
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, $form_id): void {
    if (!$form_state->getFormObject() instanceof EntityFormInterface) {
      return;
    }

    // Is service enabled.
    if (!$this->nodeTemporaryHelper->isEnabled()) {
      return;
    }

    $node = $form_state->getFormObject()->getEntity();
    if (!$this->nodeTemporaryHelper->isEnabled($node->bundle())) {
      return;
    }

    // Show message for user on edit form.
    $this->nodeTemporaryHelper->setMessage($node, $form_state->isProcessingInput());
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(array &$form, FormStateInterface $form_state, $form_id): void {
    if (!$form_state->getFormObject() instanceof EntityFormInterface) {
      return;
    }

    if (!isset($form['advanced'])) {
      return;
    }

    // Is service enabled.
    if (!$this->nodeTemporaryHelper->isEnabled()) {
      return;
    }

    // Check user access to the service.
    if (!$this->currentUser->hasPermission('access node temporary')) {
      return;
    }

    $node = $form_state->getFormObject()->getEntity();
    if (!$this->nodeTemporaryHelper->isEnabled($node->bundle())) {
      return;
    }

    $bundles = $this->nodeTemporaryHelper->getSettingsBundles();
    $temporary = $this->nodeTemporaryHelper->getTemporaryEntity($node);

    if ($temporary) {
      $description = $this->nodeTemporaryHelper->getMessage($node);
    }
    else {
      $description = $this->t('You can mark current node as temporary content that will unpublished and removed via cron in @number @days from today.', [
        '@number' => $bundles[$node->bundle()]['expire_days'],
        '@days' => $bundles[$node->bundle()]['expire_days'] < 2 ? 'day' : 'days',
      ]);
    }

    $form['node_temporary_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Temporary'),
      '#description' => $description ?? '',
      '#group' => 'advanced',
      '#weight' => 20,
      '#attributes' => [
        'class' => ['node-form-temporary-options'],
      ],
      '#attached' => [
        'library' => ['node_temporary/temporary'],
      ],
      '#open' => $temporary ? 1 : 0,
    ];

    $form['node_temporary_options']['select'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Temporary'),
      '#description' => $this->t('Automatically unpublish the node after expiration date.'),
      '#default_value' => $temporary ? 1 : 0,
    ];

    $delete_value = ($temporary && !$temporary->get('delete')->isEmpty()) ? $temporary->get('delete')->value : 0;
    $form['node_temporary_options']['delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically delete node'),
      '#description' => $this->t('Automatically delete the node after expiration date.'),
      '#default_value' => $delete_value,
      '#disabled' => empty($form['#disabled']) ? 0 : 1,
      '#states' => [
        'visible' => [
          ':input[name="select"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $date_value = ($temporary && !$temporary->get('date_expire')->isEmpty()) ?
      $temporary->get('date_expire')->value :
      (new DateTime('now', new DateTimeZone('UTC')))
        ->setTime(0, 0, 0)
        ->modify('+' . $bundles[$node->bundle()]['expire_days'] . ' days')
        ->format('Y-m-d');

    $form['node_temporary_options']['date_expire'] = [
      '#type' => 'date',
      '#description' => $this->t('Select expiration date. The date cannot be today or earlier.'),
      '#default_value' => substr($date_value, 0, 10),
      '#disabled' => empty($form['#disabled']) ? 0 : 1,
      '#states' => [
        'visible' => [
          ':input[name="select"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="select"]' => ['checked' => TRUE],
        ],
      ],
    ];

    if ($this->currentUser->hasPermission('administer node temporary configuration')) {
      $form['node_temporary_options']['description'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('To change the default settings go to @settings_link.', [
          '@settings_link' => Link::fromTextAndUrl($this->t('settings page'), Url::fromRoute('node_temporary.settings', [], [
            'attributes' => [
              'target' => '_blank',
            ],
          ]))->toString(),
        ]),
        '#attributes' => [
          'class' => ['form-item__description'],
        ]
      ];
    }

    foreach (array_keys($form['actions']) as $action) {
      if ($action !== 'preview' && isset($form['actions'][$action]['#type']) && $form['actions'][$action]['#type'] === 'submit') {
        $form['actions'][$action]['#validate'][] = [self::class, 'validateDateExpireField'];
        $form['actions'][$action]['#submit'][] = [self::class, 'nodeTemporaryFormSubmit'];
      }
    }
  }

  /**
   * Help function for validate advanced form section.
   */
  public static function validateDateExpireField(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('select')) {
      $date_expire = $form_state->getValue('date_expire');
      if (empty($date_expire)) {
        $form_state->setErrorByName('date_expire', t('Expiration date is required when marking node as temporary.'));
      }
      else {
        $date_min = (new \DateTime('now', new \DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
        if ($date_expire <= $date_min) {
          $form_state->setErrorByName('date_expire', t('The selected expiration date cannot be today or earlier. Please select a date in the future.'));
        }
      }
    }

    $form_state->setTemporaryValue('entity_validated', TRUE);
  }

  /**
   * Help function for submit advanced form section.
   */
  public static function nodeTemporaryFormSubmit(array $form, FormStateInterface $form_state): void {
    \Drupal::service('node_temporary.helper')->handleTemporaryEntity(
      $form_state->getFormObject()->getEntity(),
      $form_state->getValue('select'),
      $form_state->getValue('delete'),
      $form_state->getValue('date_expire'),
    );
  }

}
