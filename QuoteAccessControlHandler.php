<?php

/**
 * @file
 * Contains \Drupal\venga_translator\QuoteAccessControlHandler.
 */

namespace Drupal\venga_translator;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Quote entity.
 *
 * @see \Drupal\venga_translator\Entity\Quote.
 */
class QuoteAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\venga_translator\QuoteInterface $entity */
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view quote entities');

      case 'update':
        // Update access should be forbidden. Quotes should not be edited once
        // they are created and sent to translation provider.
        return AccessResult::forbidden();

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete quote entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add quote entities');
  }

}
