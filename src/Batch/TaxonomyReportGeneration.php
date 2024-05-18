<?php

namespace Drupal\taxonomy_translation_imp_exp\Batch;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Batch process for exporting taxonomy translations to a CSV file.
 */
class TaxonomyReportGeneration {

  use StringTranslationTrait;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new TaxonomyReportGeneration object.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * Exports translations of taxonomy terms to a CSV file.
   */
  public static function exportTranslations($tid, $language, $total_terms, &$context) {
    // Initialize an array to store term data.
    $csv_data = [];

    // Load the taxonomy term by its ID.
    $term = Term::load($tid);

    // Get term name and taxonomy type.
    $term_name = $term->get('name')->value;
    $taxonomy_type = $term->bundle();

    if ($term) {
      // Check if the term has a translation in the specified language.
      if ($term->hasTranslation($language)) {
        // Get the translated term in the specified language.
        $translated_term = $term->getTranslation($language);

        // Access the translated term's properties.
        $translated_term_name = $translated_term->get('name')->value;

        // Store the term and its translation in the array.
        $csv_data[] = [
          'original_name' => $term_name,
          'translated_name' => $translated_term_name,
          // Add more fields as needed.
        ];
      }
    }

    // Store the results in the batch context.
    $context['results']['taxonomy_type'] = $taxonomy_type;
    $context['results']['file_contents'][] = $csv_data;

    // Provide a message indicating the current term being processed.
    $context['message'] = t('Exporting term: %term', ['%term' => $term_name]);
  }

  /**
   * Callback function executed when the batch process is finished.
   */
  public static function finished($success, $results, $operations) {
    // Get the messenger service.
    $messenger = \Drupal::messenger();

    if (!empty($results['file_contents'])) {
      // Define CSV file details.
      $csv_directory = 'public://taxonomy_report_logs';
      $current_time_stamp = (new DrupalDateTime())->getTimestamp();
      $taxonomy_type = $results['taxonomy_type'];
      $csv_filename = 'taxonomy_translations-' . $taxonomy_type . '-' . $current_time_stamp . '.csv';

      $csv_file_path = $csv_directory . '/' . $csv_filename;
      $file_contents = $results['file_contents'];
      $csv_file_info = [
        '%csv_url'      => \Drupal::service('file_system')->realpath($csv_file_path),
        '%csv_filename' => $csv_filename,
      ];

      // Prepare the directory to save the CSV file.
      if (\Drupal::service('file_system')->prepareDirectory($csv_directory, FileSystemInterface::CREATE_DIRECTORY)) {
        // Open the CSV file for writing.
        $file = fopen($csv_file_path, 'w+');

        if ($file) {
          // Write the CSV header.
          fputcsv($file, ['Original Name', 'Translated Name']);

          // Write the data for each term.
          foreach ($file_contents as $term) {
            fputcsv(
                  $file, [
                    $term['original_name'],
                    $term['translated_name'],
                  ]
              );
          }
          // Close the CSV file.
          fclose($file);
          $messenger->addStatus(t('Taxonomy translations have been exported. Please download it here: <a href="%csv_url">%csv_filename</a>', $csv_file_info));
        }
        else {
          $messenger->addStatus(t('Unable to write CSV file to @csv_file_path', ['@csv_file_path' => $csv_file_path]));
        }
      }
      else {
        $messenger->addError(t('Failed to create directory for file.'));
      }
    }
    else {
      $messenger->addError(t('Taxonomy translations export encountered an error.'));
    }
  }

}
