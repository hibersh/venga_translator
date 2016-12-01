<?php

/**
 * @file
 * Contains \Drupal\venga_translator\VengaTranslatorUi.
 */

namespace Drupal\venga_translator;

use drunomics\XtrfClient\XtrfRequestSslInsecure;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\TranslatorPluginUiBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\language\Entity\ContentLanguageSettings;
use GuzzleHttp\Exception\RequestException;

/**
 * Venga translator UI.
 */
class VengaTranslatorUi extends TranslatorPluginUiBase  {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $bundleInfo;

  /**
   * Venga adapter.
   *
   * @var \Drupal\venga_translator\VengaTranslatorAdapter
   */
  protected $vengaAdapter;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // We can not use dependency injection here because it is not implemented in
    // the tmgmt module.
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->bundleInfo = \Drupal::service('entity_type.bundle.info');
  }

  /**
   * Gets Venga adapter for the translator.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator object.
   *
   * @return \Drupal\venga_translator\VengaTranslatorAdapter|null
   *   The Venga adapter for the translator, null if translator is not
   *   available.
   */
  public function getVengaAdapter(TranslatorInterface $translator) {
    if (empty($this->vengaAdapter)) {
      if (!$adapter = venga_translator_adapter($translator)) {
        return NULL;
      }
      $this->setVengaAdapter($adapter);
    }
    return $this->vengaAdapter;
  }

  /**
   * Sets Venga adapter for translator.
   *
   * @param \Drupal\venga_translator\VengaTranslatorAdapter $adapter
   *   The Venga adapter object.
   */
  public function setVengaAdapter(VengaTranslatorAdapter $adapter) {
    $this->vengaAdapter = $adapter;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $form_state->getFormObject()->getEntity();
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => t('WebService URL'),
      '#default_value' => $translator->getSetting('url'),
      '#description' => t('Please enter the REST URL you received from Venga.'),
    ];
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#default_value' => $translator->getSetting('username'),
      '#description' => t('Please enter your Venga Username.'),
    ];
    $form['password'] = [
      // @todo Change to 'password' and do not update password if it is empty.
      '#type' => 'textfield',
      '#title' => t('Password'),
      '#default_value' => $translator->getSetting('password'),
      '#description' => t('Please enter your Venga Password. Only type in if changed.'),
    ];
    $form['allow_insecure_connection'] = [
      '#type' => 'checkbox',
      '#title' => t('Allow insecure HTTPS connections to XTRF server'),
      '#default_value' => $translator->getSetting('allow_insecure_connection'),
      '#description' => t('Skips SSL certificate validation.'),
    ];
    $form['project_service'] = [
      '#type' => 'select',
      '#title' => t('Default Project Service'),
      '#options' => $this->getServiceOptions($translator),
      '#default_value' => $translator->getSetting('project_service'),
      '#description' => t('Please enter your Venga project service. If you use per entity settings, this setting will not be used. Go to @link to select service options for entity types and bundles.', [
        '@link' => Link::fromTextAndUrl(t('Content language and translation'), Url::fromRoute('language.content_settings_page'))->toString(),
      ]),
    ];
    $form['project_currency'] = [
      '#type' => 'textfield',
      '#title' => t('Project currency'),
      '#default_value' => $translator->getSetting('project_currency'),
      '#description' => t('Please enter your Venga project currency.'),
    ];
    $form['person'] = [
      '#type' => 'select',
      '#title' => t('Default contact person'),
      '#description' => t('Please select your default XTRF contact person.'),
      '#options' => $this->getContactPersonOptions($translator),
      '#empty_option' => ' - ',
      '#default_value' => $translator->getSetting('person'),
    ];
    $form['send_back_to'] = [
      '#type' => 'select',
      '#title' => t('Default "send back to" person'),
      '#description' => t('Please select your default XTRF "send back to" person.'),
      '#options' => $this->getContactPersonOptions($translator),
      '#empty_option' => ' - ',
      '#default_value' => $translator->getSetting('send_back_to'),
    ];

    $form += $this->buildServiceSettingsForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Shows a warning message if there was a credentials change.
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $credentials = ['password', 'url', 'username', 'allow_insecure_connection'];
    $credentials_change = FALSE;
    $values = $form_state->getValue('settings');
    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $this->entityTypeManager->getStorage('tmgmt_translator')->load($this->pluginId);
    foreach ($credentials as $setting) {
      if ($translator->getSetting($setting) != $values[$setting]) {
        $credentials_change = TRUE;
        break;
      }
    }
    if ($credentials_change) {
      $storage = $form_state->getStorage();
      $storage['venga_translator_credentials_change'] = TRUE;
      $form_state->setStorage($storage);
      drupal_set_message(t('Credentials were updated, please update values for services and persons settings.'), 'warning');
    }
  }

  /**
   * Gets contact persons.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator object.
   *
   * @return string[]
   *   Contact person names keyed by ids.
   */
  protected function getContactPersonOptions(TranslatorInterface $translator) {
    $options = [];
    if ($adapter = $this->getVengaAdapter($translator)) {
      try {
        $persons = $adapter->getContactPersons();
        foreach ($persons as $person) {
          $options[$person->getId()] = $person->getName();
        }
      }
      catch (XtrfRequestSslInsecure $e) {
        drupal_set_message(t('Cannot get contact persons from XTRF server. A secure SSL connection could not be established. Please adjust your translation provider settings and contact your provider if this error persists.'), 'error');
      }
      catch (RequestException $e) {
        drupal_set_message(t('Unable to connect to the XTRF server. Please verify your translation provider settings and contact your provider if this error persists.'), 'error');
      }
    }
    return $options;
  }

  /**
   * Builds service settings form.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array
   *   Service settings form element.
   */
  protected function buildServiceSettingsForm(array $form, FormStateInterface $form_state) {
    $translator = $form_state->getFormObject()->getEntity();
    $entity_types = $this->entityTypeManager->getDefinitions();
    $service_settings = $translator->getSetting('service_settings');
    $bundles = $this->bundleInfo->getAllBundleInfo();
    $labels = [];

    // Get all the translatable entity types.
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (!$entity_type instanceof ContentEntityTypeInterface || !$entity_type->hasKey('langcode') || !isset($bundles[$entity_type_id])) {
        continue;
      }
      foreach ($bundles[$entity_type_id] as $bundle => $bundle_info) {
        $config = ContentLanguageSettings::loadByEntityTypeBundle($entity_type_id, $bundle);
        // Check if entity type  bundle is translatable.
        if (!$config->isDefaultConfiguration()) {
          $labels[$entity_type_id] = $entity_type->getLabel() ?: $entity_type_id;
        }
      }
    }

    asort($labels);

    $element['service_settings'] = [
      '#title' => t('Default service settings'),
      '#type' => 'details',
      '#tree' => TRUE,
    ];

    foreach ($labels as $entity_type_id => $label) {
      $element['service_settings'][$entity_type_id] = [
        '#title' => $label,
        '#type' => 'fieldset',
        '#group' => 'service_settings',
      ];
      $element['service_settings'][$entity_type_id]['default'] = [
        '#type' => 'select',
        '#title' => t('Default service for @entity_type', ['@entity_type' => $label]),
        '#options' => $this->getServiceOptions($translator) + ['inherit' => t('Inherit from parent')],
        '#default_value' => !empty($service_settings[$entity_type_id]['default']) ? $service_settings[$entity_type_id]['default'] : 'inherit',
      ];
      foreach ($bundles[$entity_type_id] as $bundle => $bundle_info) {
        $element['service_settings'][$entity_type_id]['bundles'][$bundle] = [
          '#type' => 'select',
          '#title' =>  t('Default service for @entity_type of type @bundle', [
            '@entity_type' => $label,
            '@bundle' => $bundle_info['label'],
          ]),
          '#description' => t('If specified, these settings will override the global settings specified for %entity_type', [
            '%entity_type' => $label,
          ]),
          '#options' => $this->getServiceOptions($translator) + ['inherit' => t('Inherit from parent')],
          '#default_value' => !empty($service_settings[$entity_type_id]['bundles'][$bundle]) ? $service_settings[$entity_type_id]['bundles'][$bundle] : 'inherit',
        ];
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function checkoutSettingsForm(array $form, FormStateInterface $form_state, JobInterface $job) {
    $default_values = isset($_SESSION['venga_translator_settings']) && $form_state->getValue('settings') ? $_SESSION['venga_translator_settings'] : [
      'project_name' => '',
      'customer_project_number' => '',
      'service' => $this->getDefaultService($job->getTranslator(), $job->getItems()),
      'specialization' => NULL,
      'complete_by' => NULL,
      'notes' => '',
      'person' => '',
      'send_back_to' => '',
      'auto_accept' => FALSE,
    ];

    $form['project_name'] = [
      '#type' => 'textfield',
      '#title' => t('Project name'),
      '#default_value' => $job->getTranslator()->getSetting('project_name') ?: $default_values['project_name'],
      '#description' => t('Specify the project name.'),
      '#required' => TRUE,
    ];
    $form['customer_project_number'] = [
      '#type' => 'textfield',
      '#title' => t('PO number'),
      '#default_value' => $job->getTranslator()->getSetting('customer_project_number') ?: $default_values['customer_project_number'],
      '#description' => t('Specify the project number.'),
    ];
    $form['service'] = [
      '#type' => 'select',
      '#title' => t('Service'),
      '#options' => $this->getServiceOptions($job->getTranslator()),
      '#default_value' => $job->getTranslator()->getSetting('service') ?: $default_values['service'],
      '#required' => TRUE,
      '#empty_option' => t('Select a service'),
      '#empty_value' => 0,
      '#description' => t('Select a service for the project.'),
    ];
    $form['specialization'] = [
      '#type' => 'select',
      '#title' => t('Specialization'),
      '#options' => self::getSpecializationOptions($job->getTranslator()),
      '#default_value' => $job->getTranslator()->getSetting('specialization') ?: $default_values['specialization'],
      '#required' => TRUE,
      '#empty_option' => t('Select a specialization'),
      '#empty_value' => 0,
      '#description' => t('Select a specialization for the project.'),
    ];

    // Display timezone next to the date field title.
    $timezone = drupal_get_user_timezone();
    $timezone = new \DateTimeZone($timezone);
    $date = new \DateTime("now", $timezone);
    $offset = $timezone->getOffset($date) / 3600;
    $offset_text = " (GMT" . ($offset < 0 ? $offset : "+".$offset) . ')';

    $form['complete_by'] = [
      '#type' => 'datetime',
      '#title' => t('Complete-By date') . $offset_text,
      '#default_value' => $job->getTranslator()->getSetting('complete_by') ?: $default_values['complete_by'],
      '#description' => t('Set a date, when this job should be finished.'),
    ];
    // Attach javascript to pre-populate time.
    $form['#attached']['library'][] = 'venga_translator/datetime';
    $form['notes'] = [
      '#type' => 'textfield',
      '#title' => t('Notes'),
      '#default_value' => $job->getTranslator()->getSetting('notes') ?: $default_values['notes'],
      '#description' => t('(Optional) Add notes for the job.'),
    ];
    $form['person'] = [
      '#type' => 'select',
      '#title' => t('Contact person'),
      '#description' => t('Select the contact person.'),
      '#options' => $this->getContactPersonOptions($job->getTranslator()),
      '#empty_option' => ' - ',
      '#required' => TRUE,
      '#default_value' => $job->getTranslator()->getSetting('person') ?: $default_values['person'],
    ];
    $form['send_back_to'] = [
      '#type' => 'select',
      '#title' => t('Send back to'),
      '#description' => t('Select the "Send back to" person. Defaults to the contact person.'),
      '#options' => $this->getContactPersonOptions($job->getTranslator()),
      '#empty_option' => ' - ',
      '#default_value' => $job->getTranslator()->getSetting('send_back_to') ?: $job->getTranslator()->getSetting('person') ?: $default_values['send_back_to'],
    ];
    $form['additional_persons'] = [
      '#type' => 'select',
      '#title' => t('Additional persons'),
      '#description' => t('Select additional persons.'),
      '#options' => $this->getContactPersonOptions($job->getTranslator()),
      '#empty_option' => ' - ',
      '#multiple' => TRUE,
    ];
    $form['auto_accept'] = [
      '#type' => 'checkbox',
      '#title' => t('Start project without waiting for my approval'),
      '#default_value' => $job->getTranslator()->getSetting('auto_accept') ?: $default_values['auto_accept'],
      '#description' => t('Mark this checkbox if you want Venga to start working on the project right away. The usual terms and rates will be applied automatically and a confirmation will be sent. Your dedicated project manager will contact you if any additional information is needed.'),
    ];

    // Add our own after build to react on form submission. We cannot use a
    // #submit handler here as there are multiple buttons triggering submission.
    $form['#after_build'][] = [__CLASS__, 'checkoutSettingsFormAfterBuild'];

    return parent::checkoutSettingsForm($form, $form_state, $job);
  }

  /**
   * After build callback for the job checkout settings form.
   */
  public static function checkoutSettingsFormAfterBuild($element, FormStateInterface $form_state) {
    if ($form_state->getUserInput()) {
      // Keep submitted settings for pre-populating defaults later.
      $_SESSION['venga_translator_settings'] = $form_state->getValue('settings');
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function checkoutInfo(JobInterface $job) {
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Check for new Translations'),
      '#submit' => [[__CLASS__, 'checkoutInfoFormSubmit']],
    ];

    if ($job->isActive()) {

      $translation_available = TRUE;
      foreach ($job->getItems() as $item) {
        /** @var $item JobItem */
        if (!$item->isNeedsReview()) {
          $translation_available = FALSE;
          break;
        }
      }

      if ($translation_available) {
        $form['accept-all'] = [
          '#type' => 'submit',
          '#value' => t('Accept all'),
          '#submit' => [[__CLASS__, 'checkoutInfoFormSubmitAccept']],
        ];
      }
    }

    return $this->checkoutInfoWrapper($job, $form);
  }

  /**
   * Translator form submit callback for checking for new translations.
   */
  public static function checkoutInfoFormSubmit($form, FormStateInterface $form_state) {
    /** @var $job \Drupal\tmgmt\JobInterface */
    $job = $form_state->getFormObject()->getEntity();
    /** @var $translator \Drupal\tmgmt\TranslatorInterface */
    $translator = $job->getTranslator();

    $adapter = venga_translator_adapter($translator);
    $has_new_files = $adapter->checkForTranslationOutput($job);

    if ($has_new_files) {
      $adapter->downloadTranslatedFile($job);
    }
    else {
      $job->addMessage('No translations have been found.');
    }
  }

  /**
   * Translator form submit callback to accept every item.
   */
  public static function checkoutInfoFormSubmitAccept($form, FormStateInterface $form_state) {
    /** @var $job \Drupal\tmgmt\JobInterface */
    $job = $form_state->getFormObject()->getEntity();
    foreach ($job->getItems() as $item) {
      $item->acceptTranslation();
    }
  }

  /**
   * Provides a list of available service options.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator, which stores the service option setting.
   *
   * @return string[]
   *   A keyed array with service options.
   */
  protected function getServiceOptions(TranslatorInterface $translator) {
    $options = [];
    if ($adapter = $this->getVengaAdapter($translator)) {
      try {
        $services = $adapter->getServices();
        foreach ($services as $service) {
          $options[$service->getName()] = $service->getName();
        }
      }
      catch (RequestException $e) {
        drupal_set_message(t('Unable to connect to the XTRF server. Please verify your translation provider settings and contact your provider if this error persists.'), 'error');
      }
      catch (XtrfRequestSslInsecure $e) {
        drupal_set_message(t('Cannot get contact persons from XTRF server. A secure SSL connection could not be established. Please adjust your translation provider settings and contact your provider if this error persists.'), 'error');
      }
    }
    return $options;
  }

  /**
   * Provides a list of specializations.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator.
   *
   * @return string[]
   *   A keyed string array with specialization options.
   */
  protected function getSpecializationOptions(TranslatorInterface $translator) {
    $options = [];
    if ($adapter = $this->getVengaAdapter($translator)) {
      try{
        $specializations = $adapter->getSpecializations();
        foreach ($specializations as $specialization) {
          $options[$specialization->getName()] = $specialization->getName();
        }
      }
      catch (RequestException $e) {
        drupal_set_message(t('Unable to connect to the XTRF server. Please verify your translation provider settings and contact your provider if this error persists.'), 'error');
      }
      catch (XtrfRequestSslInsecure $e) {
        drupal_set_message(t('Cannot get specializations from XTRF server. A secure SSL connection could not be established. Please adjust your translation provider settings and contact your provider if this error persists.'), 'error');
      }
    }
    return $options;
  }

  /**
   * Returns the default service to use for a job.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator provider.
   * @param \Drupal\tmgmt\JobItemInterface[] $items
   *   The job items.
   *
   * @return string|int
   */
  public static function getDefaultService(\Drupal\tmgmt\TranslatorInterface $translator, array $items) {
    $services = [];
    foreach ($items as $item) {
      $service = self::getDefaultServiceSettings($translator, $item->item_type->value, $item->plugin->value);
      $services[$service] = $service;
    }
    // Checking if multiple services are configured for the job items.
    // For multiple possible services we do not preselect any service option.
    return count($services) > 1 ? 0 : array_shift($services);
  }

  /**
   * Gets default translator's service settings for the entity type and bundle.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator instance.
   * @param string $entity_type
   *   The entity type of the translatable entity.
   * @param string $bundle
   *   The bundle of the translatable entity.
   *
   * @return mixed
   *   Evaluated settings value.
   */
  public static function getDefaultServiceSettings(TranslatorInterface $translator, $entity_type, $bundle) {
    if ($settings = $translator->getSetting('service_settings')) {
      if (!empty($settings[$entity_type])) {
        $setting = $settings[$entity_type];
        if (!empty($setting['bundles'][$bundle]) && $setting['bundles'][$bundle] != 'inherit') {
          return $setting['bundles'][$bundle];
        }
        if (!empty($setting['default']) && $setting['default'] != 'inherit') {
          return $setting['default'];
        }
      }
    }

    $default = $translator->getSetting('project_service');
    if (is_array($default)) {
      $default = reset($default);
    }
    return $default;
  }

}
