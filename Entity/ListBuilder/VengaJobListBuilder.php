<?php
/**
 * @file
 * Contains \Drupal\tmgmt\Entity\ListBuilder\JobListBuilder.
 */

namespace Drupal\venga_translator\Entity\ListBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\tmgmt\Entity\ListBuilder\JobListBuilder;

/**
 * Provides list builder for the tmgmt_job entity type.
 *
 * Overrides \Drupal\tmgmt\Entity\ListBuilder\JobListBuilder to change links.
 */
class VengaJobListBuilder extends JobListBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    /** @var \Drupal\tmgmt\JobInterface $entity */
    $operations = parent::getDefaultOperations($entity);

    if (!empty($operations['delete'])) {
      $operations['delete']['url']->setOption('query', \Drupal::destination()->getAsArray());
    }
    if (!empty($operations['submit'])) {
      $operations['submit']['url'] = $entity->toUrl();
    }
    if (!empty($operations['manage'])) {
      $operations['manage']['url'] = $entity->toUrl();
    }
    $translator = $entity->getTranslator();
    if ($translator->getPluginId() == 'venga_translator') {
      // There is no "abort" command in Venga. @see
      // Drupal\venga_translator\Plugin\tmgmt\Translator\VengaTranslator::abortTranslation().
      unset($operations['abort']);
    }
    return $operations;
  }

}
