<?php

namespace Drupal\node_temporary\Hook;

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

    if (!$this->nodeTemporaryHelper->isEnabled()) {
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
      $description = $this->t('You can mark current node as temporary content that will be removed via cron in @expire_days days.', [
        '@expire_days' => $bundles[$node->bundle()]['expire_days'],
        '@node_type' => $node->bundle(),
      ]);
    }

    if ($this->currentUser->hasPermission('administer site configuration')) {
      $description .= '<br /><br />' . $this->t('To change the default settings go to @settings_link.', [
        '@settings_link' => Link::fromTextAndUrl(t('settings page'), Url::fromRoute('node_temporary.settings', [], [
          'attributes' => [
            'target' => '_blank',
          ],
        ]))->toString(),
      ]);
    }

    $form['node_temporary_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Temporary node settings'),
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

    $form['node_temporary_options']['selected'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set as temporary'),
      '#description' => $this->t('Set this node as temporary.'),
      '#default_value' => $temporary ? 1 : 0,
    ];

    $form['node_temporary_options']['update'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Update expiration date to @expire_days days', [
        '@expire_days' => $bundles[$node->bundle()]['expire_days'],
      ]),
      '#description' => $this->t('Update the expiration date to @expire_days days from today.', [
        '@expire_days' => $bundles[$node->bundle()]['expire_days'],
      ]),
      '#default_value' => 0,
      '#states' => [
        'visible' => [
          ':input[name="selected"]' => ['checked' => TRUE],
        ],
      ],
      '#disabled' => $temporary ? 0 : 1,
    ];

    foreach (array_keys($form['actions']) as $action) {
      if ($action !== 'preview' && isset($form['actions'][$action]['#type']) && $form['actions'][$action]['#type'] === 'submit') {
        $form['actions'][$action]['#submit'][] = [self::class, 'nodeTemporaryFormSubmit'];
      }
    }
  }

  /**
   * Help function for submit advanced form section.
   */
  public static function nodeTemporaryFormSubmit(array $form, FormStateInterface $form_state): void {
    \Drupal::service('node_temporary.helper')->handleTemporaryEntity(
      $form_state->getFormObject()->getEntity(),
      $form_state->getValue('selected'),
      $form_state->getValue('update'),
    );
  }

}
