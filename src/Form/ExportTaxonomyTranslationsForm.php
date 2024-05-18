<?php

namespace Drupal\taxonomy_translation_imp_exp\Form;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exports taxonomy translations to a CSV file.
 */
class ExportTaxonomyTranslationsForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * Constructs a new ExportTaxonomyTranslationsForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManager $language_manager
   *   The language manager.
   */
  public function __construct(EntityTypeManager $entity_type_manager, LanguageManager $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('entity_type.manager'),
          $container->get('language_manager')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'taxonomy_translation_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
    $site_languages = $this->languageManager->getLanguages();

    // Generate vocabulary options.
    $vocabulary_options = [];
    foreach ($vocabularies as $vocabulary) {
      $vocabulary_options[$vocabulary->id()] = $vocabulary->label();
    }

    // Generate language options.
    $languages_options = [];
    foreach ($site_languages as $language) {
      $languages_options[$language->getId()] = $language->getName();
    }

    // Vocabulary select element.
    $form['vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Vocabulary'),
      '#options' => $vocabulary_options,
      '#required' => TRUE,
    ];

    // Language select element.
    $form['language'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Language'),
      '#options' => $languages_options,
      '#required' => TRUE,
    ];

    // Submit button.
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export Taxonomy Translations'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get vocabulary and language from form state.
    $vocabulary = $form_state->getValue('vocabulary');
    $language = $form_state->getValue('language');

    // Load terms for the selected vocabulary.
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vocabulary);

    // Initialize an array to store term ids.
    $tids = [];
    foreach ($terms as $term) {
      $tids[] = $term->tid;
    }

    // Total number of terms.
    $total_terms = count($tids);

    // Define the batch operations.
    $batch = [
      'title' => $this->t('Exporting Taxonomy Translations'),
      'operations' => [],
      'init_message' => $this->t('Commencing'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'finished' => '\Drupal\taxonomy_translation_imp_exp\Batch\TaxonomyReportGeneration::finished',
    ];

    // Add each term to batch operations.
    foreach ($tids as $tid) {
      $batch['operations'][] = [
        '\Drupal\taxonomy_translation_imp_exp\Batch\TaxonomyReportGeneration::exportTranslations',
        [$tid, $language, $total_terms],
      ];
    }

    // Start the batch process.
    batch_set($batch);
  }

}
