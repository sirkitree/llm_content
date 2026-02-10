<?php

declare(strict_types=1);

namespace Drupal\llm_content\Form;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\llm_content\Service\XmlSitemapLinkManagerInterface;
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
   * The entity display repository.
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The XML Sitemap link manager.
   */
  protected XmlSitemapLinkManagerInterface $xmlSitemapLinkManager;

  /**
   * The route builder.
   */
  protected RouteBuilderInterface $routeBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityDisplayRepository = $container->get('entity_display.repository');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->xmlSitemapLinkManager = $container->get(XmlSitemapLinkManagerInterface::class);
    $instance->routeBuilder = $container->get('router.builder');
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

    // Load view modes dynamically from entity display system.
    $viewModes = $this->entityDisplayRepository->getViewModeOptions('node');

    $form['view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('View mode'),
      '#description' => $this->t('The view mode to use when rendering content for markdown conversion.'),
      '#options' => $viewModes,
      '#default_value' => $config->get('view_mode') ?? 'full',
    ];

    $form['auto_generate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-generate on save'),
      '#description' => $this->t('Automatically generate markdown when content is created or updated.'),
      '#default_value' => $config->get('auto_generate') ?? TRUE,
    ];

    // XML Sitemap integration.
    $xmlsitemapAvailable = $this->xmlSitemapLinkManager->isAvailable();
    $form['xmlsitemap'] = [
      '#type' => 'details',
      '#title' => $this->t('XML Sitemap Integration'),
      '#open' => $xmlsitemapAvailable && ($config->get('xmlsitemap_integration') ?? FALSE),
    ];

    if ($xmlsitemapAvailable) {
      $form['xmlsitemap']['xmlsitemap_status'] = [
        '#markup' => '<p>' . $this->t('The <strong>XML Sitemap</strong> module is installed. You can include LLM content URLs in the main XML sitemap.') . '</p>',
      ];
    }
    else {
      $form['xmlsitemap']['xmlsitemap_status'] = [
        '#markup' => '<p>' . $this->t('The <strong>XML Sitemap</strong> module is not installed. Install it with <code>composer require drupal/xmlsitemap</code> to enable integration.') . '</p>',
      ];
    }

    $form['xmlsitemap']['xmlsitemap_integration'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable XML Sitemap integration'),
      '#description' => $this->t('Add LLM content URLs (node markdown views, llms.txt, llms-full.txt) to the XML sitemap.'),
      '#default_value' => $config->get('xmlsitemap_integration') ?? FALSE,
      '#disabled' => !$xmlsitemapAvailable,
    ];

    $priorityOptions = [];
    for ($i = 0; $i <= 10; $i++) {
      $value = number_format($i / 10, 1);
      $priorityOptions[$value] = $value;
    }

    $form['xmlsitemap']['xmlsitemap_priority'] = [
      '#type' => 'select',
      '#title' => $this->t('Priority for node markdown URLs'),
      '#options' => $priorityOptions,
      '#default_value' => $config->get('xmlsitemap_priority') ?? '0.5',
      '#states' => [
        'visible' => [
          ':input[name="xmlsitemap_integration"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['xmlsitemap']['xmlsitemap_changefreq'] = [
      '#type' => 'select',
      '#title' => $this->t('Change frequency'),
      '#options' => [
        3600 => $this->t('Hourly'),
        86400 => $this->t('Daily'),
        604800 => $this->t('Weekly'),
        2419200 => $this->t('Monthly'),
        31449600 => $this->t('Yearly'),
      ],
      '#default_value' => $config->get('xmlsitemap_changefreq') ?? 604800,
      '#states' => [
        'visible' => [
          ':input[name="xmlsitemap_integration"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['xmlsitemap']['xmlsitemap_index_priority'] = [
      '#type' => 'select',
      '#title' => $this->t('Priority for index endpoints (llms.txt, llms-full.txt)'),
      '#options' => $priorityOptions,
      '#default_value' => $config->get('xmlsitemap_index_priority') ?? '0.7',
      '#states' => [
        'visible' => [
          ':input[name="xmlsitemap_integration"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Built-in sitemap control.
    $form['builtin_sitemap'] = [
      '#type' => 'details',
      '#title' => $this->t('Built-in Sitemap'),
      '#open' => (bool) ($config->get('disable_builtin_sitemap') ?? FALSE),
    ];

    $form['builtin_sitemap']['disable_builtin_sitemap'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable built-in /sitemap-llm.xml'),
      '#description' => $this->t('Disable the built-in LLM sitemap endpoint. Useful when using the XML Sitemap module instead.'),
      '#default_value' => $config->get('disable_builtin_sitemap') ?? FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('llm_content.settings');

    // Track values that trigger side effects.
    $oldXmlsitemapIntegration = (bool) $config->get('xmlsitemap_integration');
    $oldDisableBuiltinSitemap = (bool) $config->get('disable_builtin_sitemap');

    // Filter out unchecked content types.
    $enabled_types = array_values(array_filter($form_state->getValue('enabled_content_types')));

    $newXmlsitemapIntegration = (bool) $form_state->getValue('xmlsitemap_integration');
    $newDisableBuiltinSitemap = (bool) $form_state->getValue('disable_builtin_sitemap');

    $config
      ->set('enabled_content_types', $enabled_types)
      ->set('view_mode', $form_state->getValue('view_mode'))
      ->set('auto_generate', (bool) $form_state->getValue('auto_generate'))
      ->set('xmlsitemap_integration', $newXmlsitemapIntegration)
      ->set('xmlsitemap_priority', $form_state->getValue('xmlsitemap_priority'))
      ->set('xmlsitemap_changefreq', (int) $form_state->getValue('xmlsitemap_changefreq'))
      ->set('xmlsitemap_index_priority', $form_state->getValue('xmlsitemap_index_priority'))
      ->set('disable_builtin_sitemap', $newDisableBuiltinSitemap)
      ->save();

    // Sync xmlsitemap links when integration is toggled.
    if ($newXmlsitemapIntegration && !$oldXmlsitemapIntegration) {
      $this->xmlSitemapLinkManager->syncAllLinks();
      $this->messenger()->addStatus($this->t('LLM content URLs have been added to the XML sitemap.'));
    }
    elseif (!$newXmlsitemapIntegration && $oldXmlsitemapIntegration) {
      $this->xmlSitemapLinkManager->removeAllLinks();
      $this->messenger()->addStatus($this->t('LLM content URLs have been removed from the XML sitemap.'));
    }

    // Rebuild routes when built-in sitemap toggle changes.
    if ($newDisableBuiltinSitemap !== $oldDisableBuiltinSitemap) {
      $this->routeBuilder->rebuild();
    }

    parent::submitForm($form, $form_state);
  }

}
