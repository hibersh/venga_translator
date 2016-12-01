<?php

/**
 * @file
 * Contains XTRF admin settings.
 */

/**
 * Implements hook_form_alter for entity translation admin form.
 */
function xtrf_translator_form_entity_translation_admin_form_alter(&$form, &$form_state, $form_id) {
  $enabled_types = array_filter(variable_get('entity_translation_entity_types', array()));

  foreach ($enabled_types as $entity_type) {
    $entity_info = entity_get_info($entity_type);
    $bundles = !empty($entity_info['bundles']) ? $entity_info['bundles'] : array($entity_type => array('label' => $entity_info['label']));
    $enabled_bundles = 0;

    // Form for settings on the entity type level.
    $translators = xtrf_translator_get_translator();
    foreach ($translators as $translator_name => $translator) {
      $settings_key = xtrf_translator_settings_key($translator_name, $entity_type);
      $settings = xtrf_translator_settings($translator_name, $entity_type);
      $default_workflow = !empty($settings['workflow']) ? $settings['workflow'] : 'inherit';
      $form['settings'][$entity_type][$settings_key] = array(
        '#type' => 'fieldset',
        '#title' => t('XTRF translator "@translator" settings for @entity_type', array(
          '@translator' => $translator->label,
          '@entity_type' => $entity_info['plural label'],
        )),
        '#collapsible' => TRUE,
        '#collapsed' => $default_workflow == 'inherit',
        '#weight' => '-1',
        '#tree' => TRUE,
      );
      $form['settings'][$entity_type][$settings_key]['workflow'] = array(
        '#type' => 'select',
        '#title' => t('Workflow'),
        '#options' => xtrf_translator_settings_workflow_options($translator) + array(
          'inherit' => t('Inherit from parent')
        ),
        '#default_value' => $default_workflow,
      );
    }

    // Form for settings on the bundle level.
    foreach ($bundles as $bundle => $bundle_info) {
      if (entity_translation_enabled_bundle($entity_type, $bundle)) {
        $enabled_bundles++;

        if (count($bundles) > 1 || $bundle != $entity_type) {
          $translators = xtrf_translator_get_translator();
          foreach ($translators as $translator_name => $translator) {
            $settings = xtrf_translator_settings($translator_name, $entity_type, $bundle);
            $et_settings_key = 'entity_translation_settings_' . $entity_type . '__' . $bundle;
            $settings_key = xtrf_translator_settings_key($translator_name, $entity_type, $bundle);
            $default_workflow = !empty($settings['workflow']) ? $settings['workflow'] : 'inherit';
            $form['settings'][$entity_type][$et_settings_key][$settings_key] = array(
              '#type' => 'fieldset',
              '#title' => t('XTRF translator "@translator" settings for @entity_type of type @bundle', array(
                '@translator' => $translator->label,
                '@entity_type' => $entity_info['plural label'],
                '@bundle' => $bundle_info['label'],
              )),
              '#collapsible' => TRUE,
              '#collapsed' => $default_workflow == 'inherit',
              '#tree' => TRUE,
            );
            $form['settings'][$entity_type][$et_settings_key][$settings_key]['workflow'] = array(
              '#type' => 'select',
              '#title' => t('Workflow'),
              '#description' => t('If specified, these settings will override the global settings specified for %entity_type', array('%entity_type' => $entity_info['plural label'])),
              '#options' => xtrf_translator_settings_workflow_options($translator) + array(
                  'inherit' => t('Inherit from parent')
                ),
              '#default_value' => $default_workflow,
            );
          }
        }
      }
    }
  }
  return $form;
}

/**
 * Returns the xtrf translator settings key.
 */
function xtrf_translator_settings_key($translator_name, $entity_type, $bundle = NULL) {
  return 'xtrf_translator_settings_' . $translator_name . '__' . $entity_type . (!empty($bundle) ? '__' . $bundle : '');
}

/**
 * Return the xtrf translator settings.
 */
function xtrf_translator_settings($translator_name, $entity_type, $bundle = NULL) {
  $settings_key = xtrf_translator_settings_key($translator_name, $entity_type, $bundle);

  // Entity-specific settings are stored separately.
  if ($bundle == NULL) {
    $settings = variable_get(xtrf_translator_settings_key($translator_name, $entity_type), array());
  }
  // Bundle-specific settings are stored together with entity translation settings for the bundle.
  else {
    $settings = entity_translation_settings($entity_type, $bundle);
    $settings = isset($settings[$settings_key]) ? $settings[$settings_key] : array();
  }

  $settings += array(
    'workflow' => '',
  );

  return $settings;
}

/**
 * Returns an evaluated settings value.
 *
 * The order of precedence is:
 *
 *   1) bundle specific
 *   2) entity type level
 *   3) provided default
 */
function xtrf_translator_settings_eval($translator, $entity_type, $bundle = NULL, $key, $default) {
  $settings = xtrf_translator_settings($translator->name, $entity_type, $bundle);
  if (!empty($settings[$key]) && $settings[$key] != 'inherit') {
    return $settings[$key];
  }
  $settings = xtrf_translator_settings($translator->name, $entity_type);
  if (!empty($settings[$key]) && $settings[$key] != 'inherit') {
    return $settings[$key];
  }
  return $default;
}

/**
 * Provides a list of available workflow options
 *
 * @param $translator
 *   The translator, which stores the workflow option setting.
 *
 * @return array
 *   A keyed array with workflow options.
 */
function xtrf_translator_settings_workflow_options($translator) {
  if (!empty($translator->settings['project_workflow_options'])) {
    $options = explode("\r\n", $translator->settings['project_workflow_options']);
    $options = array_combine($options, $options);
  }
  else {
    $options = array();
  }
  return $options;
}
