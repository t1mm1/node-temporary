<?php

namespace Drupal\node_temporary\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Node temporary settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The entity type.
   *
   * @var string
   */
  protected string $type = 'node';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['node_temporary.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'node_temporary_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('node_temporary.settings');

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General'),
      '#open' => TRUE,
      '#weight' => -1,
    ];

    $form['general']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#description' => $this->t('Enable node temporary service'),
      '#default_value' => $config->get('enabled'),
      '#return_value' => 1,
      '#empty' => 0,
    ];

    $form['bundles'] = [
      '#type' => 'details',
      '#title' => $this->t('Content type settings'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $bundle_settings = $config->get('bundles') ?: [];
    $definition = $this->entityTypeManager->getDefinition($this->type);
    if ($definition->getBundleEntityType()) {
      $bundles = $this->entityTypeManager
        ->getStorage($definition->getBundleEntityType())
        ->loadMultiple();

      foreach ($bundles as $bundle) {
        $bundle_data = $bundle_settings[$bundle->id()] ?? [];
        $enabled = $bundle_data['enabled'] ?? 0;
        $expire_default = $bundle_data['expire_days'] ?? 7;

        $form['bundles'][$bundle->id()] = [
          '#type' => 'container',
        ];

        $form['bundles'][$bundle->id()]['enabled'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable for %label', ['%label' => $bundle->label()]),
          '#default_value' => $enabled,
        ];

        $form['bundles'][$bundle->id()]['expire_days'] = [
          '#type' => 'number',
          '#title' => $this->t('Expire days'),
          '#min' => 2,
          '#default_value' => $expire_default,
          '#description' => $this->t('Specify how many days after creation the "%type" content will be deleted.', ['%type' => $bundle->label()]),
          '#states' => [
            'visible' => [
              ':input[name="bundles[' . $bundle->id() . '][enabled]"]' => ['checked' => TRUE],
            ],
          ],
        ];
      }
    }

    return parent::buildForm($form, $form_state) + $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $bundles_settings = [];
    $definition = $this->entityTypeManager->getDefinition($this->type);
    if ($definition->getBundleEntityType()) {
      $bundles = $this->entityTypeManager
        ->getStorage($definition->getBundleEntityType())
        ->loadMultiple();

      foreach ($bundles as $bundle) {
        $values = $form_state->getValue(['bundles', $bundle->id()]);

        $expire_days = (!empty($values['expire_days']) && $values['expire_days'] > 0)
          ? (int)$values['expire_days']
          : 7;

        $bundles_settings[$bundle->id()] = [
          'enabled' => $values['enabled'] ?? 0,
          'expire_days' => $expire_days,
        ];
      }
    }

    $this->config('node_temporary.settings')
      ->set('enabled', $form_state->getValue('enabled') ? 1 : 0)
      ->set('bundles', $bundles_settings)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
