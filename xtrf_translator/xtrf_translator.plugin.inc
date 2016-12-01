<?php

/**
 * @file
 * Provides XTRF translator plugin controller.
 */

/**
 * XTRF translator plugin controller.
 */
class TMGMTXTRFTranslatorPluginController extends TMGMTDefaultTranslatorPluginController {

  /**
   * Overrides TMGMTDefaultTranslatorPluginController::isAvailable().
   */
  public function isAvailable(TMGMTTranslator $translator) {
    if ($translator->getSetting('username') && $translator->getSetting('password') &&
      $translator->getSetting('project_workflow') && $translator->getSetting('project_currency') &&
      $translator->getSetting('url')
    ) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Implements TMGMTTranslatorPluginControllerInterface::requestTranslation().
   */
  public function requestTranslation(TMGMTJob $job) {
    $adapter = xtrf_translator_adapter($job->getTranslator());

    if ($adapter->sendFileforTranslation($job)) {
      $job->submitted('The translation job has been submitted.');
    }

  }

  /**
   * Overrides TMGMTDefaultTranslatorPluginController::hasCheckoutSettings().
   */
  public function hasCheckoutSettings(TMGMTJob $job) {
    return TRUE;
  }

  /**
   * Overrides TMGMTDefaultTranslatorPluginController::cancelTranslation().
   */
  public function cancelTranslation(TMGMTJob $job) {
    $job->addMessage('XTRF has no cancel option.');
    return FALSE;
  }

}
