<?php

declare(strict_types=1);

namespace Drupal\quiztools\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\Entity\File;

/**
 * Provides a quiztools form.
 */
final class ImporterMultichoiceForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a new ImporterMultichoiceForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, FileSystemInterface $file_system) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'quiztools_importer_multichoice';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload CSV file'),
      '#description' => $this->t('Upload a CSV file with multichoice questions. Columns: title,body,choice_multi,choice_random,alternatives1,correct1,alternatives2,correct2,... (up to 4 alternatives).'),
      '#required' => TRUE,
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'csv'],
      ],
      '#upload_location' => 'private://quiztools_csv_import/',  // 恢复私有文件路径
      '#autoupload' => TRUE, // 显式开启自动上传
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Import Questions'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @todo Validate the form here.
    // Example:
    // @code
    //   if (mb_strlen($form_state->getValue('message')) < 10) {
    //     $form_state->setErrorByName(
    //       'message',
    //       $this->t('Message should be at least 10 characters.'),
    //     );
    //   }
    // @endcode
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $fids = $form_state->getValue('csv_file');
    if (empty($fids[0])) {
      $this->messenger()->addError($this->t('No CSV file uploaded.'));
      return;
    }
    $fid = $fids[0];
    /** @var \Drupal\file\FileInterface $file */
    $file = File::load($fid);
    if (!$file) {
      $this->messenger()->addError($this->t('Could not load the uploaded file.'));
      return;
    }
    $file_to_delete_later = $file; // Keep a reference to delete later.

    $file_path = $this->fileSystem->realpath($file->getFileUri());

    if (($handle = fopen($file_path, "r")) !== FALSE) {
      $question_storage = $this->entityTypeManager->getStorage('quiz_question');
      $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');

      // Read and discard header row.
      $header = fgetcsv($handle);
      if ($header === FALSE) {
        $this->messenger()->addError($this->t('Could not read the CSV header.'));
        fclose($handle);
        // $file_to_delete_later will be deleted at the end of the function if it still exists.
        return;
      }

      $imported_count = 0;
      $row_num = 1; // Start after header.

      while (($data = fgetcsv($handle)) !== FALSE) {
        $row_num++;
        // Expected columns: title,body,choice_multi,choice_random,alt1,cor1,alt2,cor2,alt3,cor3,alt4,cor4
        // Minimum 6 columns for title, body, and one alternative pair.
        if (count($data) < 6) {
          $this->messenger()->addWarning($this->t('Skipping row @num: not enough columns. Expected at least 6, found @count.', ['@num' => $row_num, '@count' => count($data)]));
          continue;
        }

        $question_title = trim($data[0]);
        $question_body = trim($data[1]);
        // Default choice_multi to 0 (single choice) if empty.
        $choice_multi = (isset($data[2]) && trim($data[2]) !== '') ? (int) trim($data[2]) : 0;
        // Default choice_random to 1 (randomize) if empty or not '0'.
        $choice_random = (isset($data[3]) && trim($data[3]) !== '' && trim($data[3]) === '0') ? 0 : 1;

        $alternatives_paragraphs = [];
        // Alternatives start at index 4, in pairs of (text, correct_flag). Max 4 alternatives.
        for ($i = 0; $i < 4; $i++) {
          $alt_text_index = 4 + ($i * 2);
          $alt_correct_index = 5 + ($i * 2);

         if (isset($data[$alt_text_index]) && trim($data[$alt_text_index]) !== '') {
            $alt_text = trim($data[$alt_text_index]);
            $is_correct = (isset($data[$alt_correct_index]) && trim($data[$alt_correct_index]) === '1') ? 1 : 0;

            $paragraph = $paragraph_storage->create([
              'type' => 'multichoice', // Standard bundle name for Quiz multichoice alternatives.
              'multichoice_answer' => ['value' => $alt_text, 'format' => 'basic_html'],
              'multichoice_correct' => $is_correct,
              'multichoice_score_chosen' => $is_correct ? 1 : 0, // 暂时注释掉
              'multichoice_score_not_chosen' => 0, // 暂时注释掉
            ]);
            $alternatives_paragraphs[] = $paragraph;
          }
        }

        if (empty($alternatives_paragraphs)) {
          $this->messenger()->addWarning($this->t('Skipping question "@title" from row @num: no valid alternatives found.', ['@title' => $question_title, '@num' => $row_num]));
          continue;
        }

        /** @var \Drupal\quiz\Entity\QuizQuestion $question */
        $question = $question_storage->create([
          'type' => 'multichoice',
          'title' => $question_title,
          'body' => ['value' => $question_body ?: $question_title, 'format' => 'basic_html'], // Use title if body is empty.
          'choice_multi' => $choice_multi,
          'choice_random' => $choice_random,
          'alternatives' => $alternatives_paragraphs, // This will hold Paragraph entities.
          'uid' => $this->currentUser->id(),
          'status' => 1, // Published.
        ]);

        try {
          $question->save();
          $imported_count++;
        }
        catch (\Exception $e) {
          $this->messenger()->addError($this->t('Error importing question "@title" from row @num: @error', ['@title' => $question_title, '@num' => $row_num, '@error' => $e->getMessage()]));
        }
      }
      fclose($handle);
      $this->messenger()->addStatus($this->t('Successfully imported @count multiple choice questions.', ['@count' => $imported_count]));
    }
    else {
      $this->messenger()->addError($this->t('Could not open the CSV file for reading.'));
    }

    // Delete the uploaded file after processing, if it still exists.
    if ($file_to_delete_later && File::load($file_to_delete_later->id())) {
      $file_to_delete_later->delete();
      $this->messenger()->addStatus($this->t('Temporary CSV file has been deleted.'));
    }
  }

}
