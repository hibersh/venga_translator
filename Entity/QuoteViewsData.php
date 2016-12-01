<?php

/**
 * @file
 * Contains \Drupal\venga_translator\Entity\Quote.
 */

namespace Drupal\venga_translator\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for Quote entities.
 */
class QuoteViewsData extends EntityViewsData implements EntityViewsDataInterface {
  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['venga_translator_quote']['table']['base'] = array(
      'field' => 'id',
      'title' => $this->t('Quote'),
      'help' => $this->t('The Quote ID.'),
    );

    return $data;
  }

}
