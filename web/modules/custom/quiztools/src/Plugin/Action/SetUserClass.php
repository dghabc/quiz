<?php

namespace Drupal\quiztools\Plugin\Action;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Term;

/**
 * Action to set the ban_ji field for users.
 *
 * @Action(
 *   id = "quiztools_set_user_class",
 *   label = @Translation("Set User Class (ban_ji)"),
 *   type = "user",
 *   confirm = TRUE
 * )
 */
class SetUserClass extends ViewsBulkOperationsActionBase implements PluginFormInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Load terms from the ban_ji vocabulary.
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'ban_ji']);

    $options = [];
    foreach ($terms as $term) {
      $options[$term->id()] = $term->getName();
    }

    $form['ban_ji'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Class (ban_ji)'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select a class -'),
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue('ban_ji')) {
      $form_state->setErrorByName('ban_ji', $this->t('You must select a class.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['ban_ji'] = $form_state->getValue('ban_ji');
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity instanceof User || !$entity->hasField('field_bj')) {
      return;
    }

    $tid = $this->configuration['ban_ji'];
    $entity->set('field_bj', $tid);
    $entity->save();

    return $this->t('Updated class for user %name.', ['%name' => $entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object instanceof User) {
      $access = $object->access('update', $account, TRUE)
        ->andIf($object->field_bj->access('edit', $account, TRUE));
      return $return_as_object ? $access : $access->isAllowed();
    }
    return FALSE;
  }
}
