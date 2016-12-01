<?php

/**
 * @file
 * Provides XTRF Translator ui controller.
 */

/**
 * XTRF translator ui controller.
 */
class TMGMTXTRFTranslatorUIController extends TMGMTDefaultTranslatorUIController {

  /**
   * Overrides TMGMTDefaultTranslatorUIController::pluginSettingsForm().
   */
  public function pluginSettingsForm($form, &$form_state, TMGMTTranslator $translator, $busy = FALSE) {

    $form['url'] = array(
      '#type' => 'textfield',
      '#title' => t('WebService URL'),
      '#default_value' => $translator->getSetting('url'),
      '#description' => t('Please enter the REST URL you received from XTRF.'),
    );
    $form['username'] = array(
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#default_value' => $translator->getSetting('username'),
      '#description' => t('Please enter your XTRF Username.'),
    );
    $form['password'] = array(
      '#type' => 'password',
      '#title' => t('Password'),
      '#default_value' => $translator->getSetting('password'),
      '#description' => t('Please enter your XTRF Password. Only type in if changed.'),
    );
    $form['project_workflow_options'] = array(
      '#type' => 'textarea',
      '#title' => t('Project Workflow options'),
      '#default_value' => $translator->getSetting('project_workflow_options'),
      '#description' => t('Please enter the XTRF project workflow options, one per line.'),
    );
    $form['project_workflow'] = array(
      '#type' => 'textfield',
      '#title' => t('Default Project Workflow'),
      '#default_value' => $translator->getSetting('project_workflow'),
      '#description' => t('Please enter your XTRF project workflow. If you use per entity settings, this setting will not be used. Go to !link to select workflow options for entity types and bundles.', array(
        '!link' => l(t('Entity translation settings'), 'admin/config/regional/entity_translation'),
      )),
    );
    $form['project_currency'] = array(
      '#type' => 'textfield',
      '#title' => t('Project currency'),
      '#default_value' => $translator->getSetting('project_currency'),
      '#description' => t('Please enter your XTRF project currency.'),
    );
    return parent::pluginSettingsForm($form, $form_state, $translator);
  }

  /**
   * Implements TMGMTTranslatorUIControllerInterface::checkoutSettingsForm().
   */
  public function checkoutSettingsForm($form, &$form_state, TMGMTJob $job) {
    $default_values = isset($_SESSION['xtrf_translator_settings']) ? $_SESSION['xtrf_translator_settings'] : array(
      'project_name' => '',
      'workflow' => $this->getDefaultWorkflow($job),
      'complete_by' => NULL,
      'notes' => '',
    );

    $form['project_name'] = array(
      '#type' => 'textfield',
      '#title' => t('Project name'),
      '#default_value' => $job->getSetting('project_name') ?: $default_values['project_name'],
      '#description' => t('Specify the project name.'),
      '#required' => TRUE,
    );
    $form['workflow'] = array(
      '#type' => 'select',
      '#title' => t('Workflow'),
      '#options' => xtrf_translator_settings_workflow_options($job->getTranslator()),
      '#default_value' => $job->getSetting('workflow') ?: $default_values['workflow'],
      '#required' => TRUE,
      '#empty_option' => t('Select a workflow'),
      '#empty_value' => 0,
      '#description' => t('Select a workflow for the project.'),
    );
    $form['complete_by'] = array(
      '#type' => 'date',
      '#title' => t('Complete-By date'),
      '#default_value' => $job->getSetting('complete_by') ?: $default_values['complete_by'],
      '#description' => t('Set a date, when this job should be finished.'),
    );
    $form['notes'] = array(
      '#type' => 'textfield',
      '#title' => t('Notes'),
      '#default_value' => $job->getSetting('notes') ?: $default_values['notes'],
      '#description' => t('(Optional) Add notes for the job.'),
    );
    // Add our own entity builder to react on form submission. We cannot use
    // a #submit handler here as there are multiple buttons triggering
    // submission.
    $form['#after_build'][] = array(__CLASS__, 'checkoutSettingsFormAfterBuild');

    return parent::checkoutSettingsForm($form, $form_state, $job);
  }

  /**
   * After build   callback for the job checkout settings form.
   */
  public static function checkoutSettingsFormAfterBuild($element, $form_state) {
    if (!empty($form_state['input'])) {
      // Keep submitted settings for pre-populating defaults later.
      $_SESSION['xtrf_translator_settings'] = $form_state['values']['settings'];
    }
    return $element;
  }

  /**
   * Returns the default workflow to use for a job.
   */
  protected function getDefaultWorkflow(TMGMTJob $job) {
    $items = $job->getItems();
    $translator = $job->getTranslator();
    $workflows = array();
    foreach ($items as $item) {
      $entity = entity_load_single($item->item_type, $item->item_id);
      $wrapper = entity_metadata_wrapper($item->item_type, $entity);
      $workflowName = xtrf_translator_settings_eval($translator, $item->item_type, $wrapper->getBundle(), 'workflow', $translator->getSetting('project_workflow'));
      $workflows[$workflowName] = $workflowName;
    }
    // Checking if multiple workflows are configured for the job items.
    // For multiple possible workflows we do not preselect any workflow option.
    return count($workflows) > 1 ? 0 : array_shift($workflows);
  }

  /**
   * Implements TMGMTTranslatorUIControllerInterface::checkoutInfo().
   */
  public function checkoutInfo(TMGMTJob $job) {

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Check for new Translations'),
      '#submit' => array(array(__CLASS__, 'checkoutInfoFormSubmit')),
    );

    if ($job->isActive()) {

      $translation_available = TRUE;
      foreach ($job->getItems() as $item) {
        /** @var $item TMGMTJobItem */
        if (!$item->isNeedsReview()) {
          $translation_available = FALSE;
          break;
        }
      }

      if ($translation_available) {
        $form['accept-all'] = array(
          '#type' => 'submit',
          '#value' => t('Accept all'),
          '#submit' => array(array(__CLASS__, 'checkoutInfoFormSubmitAccept')),
        );
      }
    }

    return $form;
  }

  /**
   * Translator form submit callback for checking for new translations.
   */
  public static function checkoutInfoFormSubmit($form, &$form_state) {
    $job = $form_state['tmgmt_job'];
    $translator = $job->getTranslator();

    $client = xtrf_translator_adapter($translator);
    $has_new_files = $client->checkForTranslationOutput($job);

    if ($has_new_files) {
      $client->downloadTranslatedFile($translator, $job);
    }
    else {
      $job->addMessage('No translations have been found.');
    }
  }

  /**
   * Translator form submit callback to accept every item.
   */
  public static function checkoutInfoFormSubmitAccept($form, &$form_state) {
    $job = $form_state['tmgmt_job'];

    foreach ($job->getItems() as $item) {
      $item->acceptTranslation();
    }
  }

}
