<?php

// 确保你的代码在 Drupal 环境下运行
// 例如，在一个 .module 文件的方法中，或者一个 Drush 命令中

use Drupal\paragraphs\Entity\Paragraph; // 用于创建题目选项
// QuizQuestion 实体通常通过实体管理器操作，而不是直接 new
// use Drupal\quiz_multichoice\Plugin\quiz\QuizQuestion\MultichoiceQuestion; // 这是插件类，实体对象会使用它的方法

// 1. 获取实体存储服务
$entity_type_manager = \Drupal::entityTypeManager();
$question_storage = $entity_type_manager->getStorage('quiz_question');
$paragraph_storage = $entity_type_manager->getStorage('paragraph');

// 2. 创建题目选项 (Paragraphs)
// 注意：'quiz_multichoice_alternative' 是 Paragraph 的 bundle (类型名)，
// 具体名称可能因你的 Quiz 模块版本或配置而略有不同。
$paragraph_bundle = 'quiz_multichoice_alternative';

// 选项 A
$alternative1 = $paragraph_storage->create([
  'type' => $paragraph_bundle,
  'multichoice_answer' => ['value' => '选项 A 的内容', 'format' => 'basic_html'],
  'multichoice_correct' => 1, // 1 表示此选项正确
  'multichoice_score_chosen' => 1, // 选中此选项得分
  'multichoice_score_not_chosen' => 0, // 未选中此选项得分
  'multichoice_feedback_chosen' => ['value' => '你选择了正确的选项 A！', 'format' => 'basic_html'],
  'multichoice_feedback_not_chosen' => ['value' => '选项 A 是正确的，但你没选。', 'format' => 'basic_html'],
]);
// Paragraph 实体通常在宿主实体保存时自动保存，或者你可以显式保存。
// MultichoiceQuestion 的 forgive() 方法会保存它们。

// 选项 B
$alternative2 = $paragraph_storage->create([
  'type' => $paragraph_bundle,
  'multichoice_answer' => ['value' => '选项 B 的内容', 'format' => 'basic_html'],
  'multichoice_correct' => 0, // 0 表示此选项不正确
  'multichoice_score_chosen' => 0, // 选中此选项得分 (或负分，如 -1)
  'multichoice_score_not_chosen' => 0, // 未选中此选项得分
  'multichoice_feedback_chosen' => ['value' => '你选择了错误的选项 B。', 'format' => 'basic_html'],
  'multichoice_feedback_not_chosen' => ['value' => '很好，你没有选择错误的选项 B。', 'format' => 'basic_html'],
]);

// 3. 创建多项选择题实体 (MultichoiceQuestion)
/** @var \Drupal\quiz\Entity\QuizQuestion $question */
$question = $question_storage->create([
  'type' => 'multichoice', // 这是插件定义中的 ID，也是实体的 bundle
  'title' => '一个通过代码创建的多选题',
  'uid' => \Drupal::currentUser()->id(), // 设置题目创建者
  'status' => 1, // 1 表示已发布

  // MultichoiceQuestion 特有的字段 (在 quiz_question 实体上)
  'choice_multi' => 0,       // 0: 单选, 1: 多选
  'choice_boolean' => 0,     // 0: 常规计分, 1: 简单计分 (全对或全错得1分或0分)
  'choice_random' => 0,      // 0: 固定选项顺序, 1: 随机选项顺序
  // 其他 QuizQuestion 实体可能需要的字段...
]);

// 4. 将选项关联到题目
// 'alternatives' 字段是一个实体引用字段，引用 Paragraphs
$question->set('alternatives', [$alternative1, $alternative2]);

// 5. 调用 save() 方法
// 这会触发 /home/dghabc/quiz/web/modules/contrib/quiz/modules/quiz_multichoice/src/Plugin/quiz/QuizQuestion/MultichoiceQuestion.php 中的 save()
$result_status = $question->save();

// 6. 检查保存结果
if ($result_status === SAVED_NEW) {
  \Drupal::messenger()->addStatus(t('新的多项选择题 "@title" (ID: @id) 已成功创建。', [
    '@title' => $question->label(),
    '@id' => $question->id(),
  ]));
  // $question->save() 内部的 forgive() 方法会确保 alternative 也被保存。
  // warn() 方法可能会添加一些警告信息。
}
elseif ($result_status === SAVED_UPDATED) {
  \Drupal::messenger()->addStatus(t('多项选择题 "@title" (ID: @id) 已成功更新。', [
    '@title' => $question->label(),
    '@id' => $question->id(),
  ]));
}
else {
  \Drupal::messenger()->addError(t('保存多项选择题失败。'));
}

/*
// 如果是加载并修改现有题目：
$existing_question_id = 123; // 假设这是已存在题目的 ID
$existing_question = $question_storage->load($existing_question_id);

if ($existing_question && $existing_question->bundle() === 'multichoice') {
  // $existing_question 现在是一个 MultichoiceQuestion 类型的实体对象
  $existing_question->set('title', '这是更新后的题目标题');

  // 你可以修改它的选项，例如添加一个新的选项：
  // $new_alternative = $paragraph_storage->create([...]);
  // $existing_question->get('alternatives')->appendItem($new_alternative);

  $existing_question->save(); // 再次调用 save 方法
}
*/

?>
