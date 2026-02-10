<?php

declare(strict_types=1);

namespace Drupal\llm_content\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure LLM Content settings.
 */
final class LlmContentSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'llm_content_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['llm_content.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('llm_content.settings');

    // Get all content types.
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $options = [];
    foreach ($node_types as $type) {
      $options[$type->id()] = $type->label();
    }

    $form['enabled_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled content types'),
      '#description' => $this->t('Select which content types should have markdown views generated.'),
      '#options' => $options,
      '#default_value' => $config->get('enabled_content_types') ?? [],
    ];

    $form['view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('View mode'),
      '#description' => $this->t('The view mode to use when rendering content for markdown conversion.'),
      '#options' => [
        'full' => $this->t('Full'),
        'teaser' => $this->t('Teaser'),
        'default' => $this->t('Default'),
      ],
      '#default_value' => $config->get('view_mode') ?? 'full',
    ];

    $form['auto_generate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-generate on save'),
      '#description' => $this->t('Automatically generate markdown when content is created or updated.'),
      '#default_value' => $config->get('auto_generate') ?? TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Filter out unchecked content types.
    $enabled_types = array_values(array_filter($form_state->getValue('enabled_content_types')));

    $this->config('llm_content.settings')
      ->set('enabled_content_types', $enabled_types)
      ->set('view_mode', $form_state->getValue('view_mode'))
      ->set('auto_generate', (bool) $form_state->getValue('auto_generate'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
