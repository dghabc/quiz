<?php

namespace Drupal\quiztools\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 为 Quiz Question 实体设置知识点（field_zsd）。
 *
 * @Action(
 *   id = "set_knowledge_point",
 *   label = @Translation("设置知识点"),
 *   type = "quiz_question",
 *   category = @Translation("Quiz Tools")
 * )
 */
class SetKnowledgePoint extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  /**
   * 实体类型管理器。
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('update', $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if ($entity && $entity->hasField('field_zsd') && !empty($this->configuration['knowledge_point'])) {
      $tid = $this->configuration['knowledge_point'];
      $entity->set('field_zsd', ['target_id' => $tid]);
      $entity->save();
      \Drupal::logger('quiztools')->notice('为 Quiz Question @id 设置知识点 TID: @tid', [
        '@id' => $entity->id(),
        '@tid' => $tid,
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('zhi_shi_dian');
    $options = ['' => $this->t('- 选择知识点 -')];
    foreach ($terms as $term) {
      $options[$term->tid] = str_repeat('—', $term->depth) . ' ' . $term->name;
    }

    $form['knowledge_point'] = [
      '#type' => 'select',
      '#title' => $this->t('知识点'),
      '#options' => $options,
      '#required' => TRUE,
      '#description' => $this->t('选择要设置的知识点术语。'),
      '#default_value' => $this->configuration['knowledge_point'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['knowledge_point'] = $form_state->getValue('knowledge_point');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + ($this->defaultConfiguration());
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'knowledge_point' => '',
    ];
  }

}
