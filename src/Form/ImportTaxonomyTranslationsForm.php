<?php

namespace Drupal\taxonomy_translation_imp_exp\Form;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Imports taxonomy translations from a CSV file.
 */
class ImportTaxonomyTranslationsForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $languageManager;

  /**
   * Constructs a new ImportTaxonomyTranslationsForm object.
   */
  public function __construct(EntityTypeManager $entity_type_manager, LanguageManager $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * Instantiates this form class.
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('entity_type.manager'),
          $container->get('language_manager'),
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'taxonomy_translation_report_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
    $site_languages = $this->languageManager->getLanguages();

    // Generate vocabulary options.
    $vocab_options = [];
    foreach ($vocabularies as $vocabulary) {
      $vocab_options[$vocabulary->id()] = $vocabulary->label();
    }

    // Generate language options.
    $lang_options = [];
    foreach ($site_languages as $language) {
      $lang_options[$language->getId()] = $language->getName();
    }

    // Vocabulary select element.
    $form['vocabulary'] = [
      '#type' => 'select',
      '#options' => $vocab_options,
      '#title' => $this->t('Taxonomy Vocabulary'),
      '#required' => TRUE,
    ];

    // Language select element.
    $form['languages'] = [
      '#type' => 'select',
      '#options' => $lang_options,
      '#title' => $this->t('Languages'),
      '#required' => TRUE,
    ];

    // Submit button.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Report'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get vocabulary name from form state.
    $vocab_name = $form_state->getValue('vocabulary');

    // Load terms for the selected vocabulary.
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vocab_name);

    // Initialize an array to store term ids.
    $tids = [];
    foreach ($terms as $term) {
      $tids[] = $term->tid;
    }

    // Define the batch operations.
    $batch = [
      'title'            => $this->t('Processing contents ...'),
      'operations'       => [],
      'init_message'     => $this->t('Commencing'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'error_message'    => $this->t('An error occurred during processing'),
      'finished'         => '\Drupal\taxonomy_translation_imp_exp\Batch\TaxonomyReportGeneration::finished',
    ];

    // Add each term to batch operations.
    foreach ($tids as $tid) {
      $batch['operations'][] = [
        '\Drupal\taxonomy_translation_imp_exp\Batch\TaxonomyReport::importTranslations',
        [$tid],
      ];
    }

    // Start the batch process.
    batch_set($batch);
  }

}
