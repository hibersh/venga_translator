<?php

/**
 * @file
 * Contains \Drupal\venga_translator\Form\QuoteSettingsForm.
 */

namespace Drupal\venga_translator\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tmgmt\TranslatorManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class QuoteSettingsForm.
 *
 * @package Drupal\venga_translator\Form
 *
 * @ingroup venga_translator
 */
class QuoteSettingsForm extends ConfigFormBase {

  /**
   * Translator plugin manager.
   *
   * @var \Drupal\tmgmt\TranslatorManager
   */
  protected $translatorManager;

  /**
   * QuoteSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\tmgmt\TranslatorManager $translator_manager
   *   The translator plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TranslatorManager $translator_manager) {
    parent::__construct($config_factory);
    $this->translatorManager = $translator_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.tmgmt.translator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'quote_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['venga_translator.settings'];
  }

  /**
   * Defines the settings form for Quote entities.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Form definition array.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('venga_translator.settings');

    $options = array();
    foreach ($this->translatorManager->getDefinitions() as $plugin => $definition) {
      if ($plugin == 'venga_translator') {
        $options[$plugin] = $definition['label'];
      }
    }
    $form['translator'] = array(
      '#type' => 'select',
      '#title' => $this->t('Venga translator'),
      '#default_value' => $config->get('translator'),
      '#options' => $options,
      '#description' => $this->t('Choose a Venga translator provider which will be used for translation quotes.'),
      '#required' => TRUE,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('venga_translator.settings')
      ->set('translator', $form_state->getValue('translator'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
