<?php

/**
 * @file
 * Contains \Drupal\venga_translator\Plugin\tmgmt\Translator\VengaTranslator.
 */

namespace Drupal\venga_translator\Plugin\tmgmt\Translator;

use drunomics\XtrfClient\XtrfRequestSslInsecure;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt\TranslatorPluginBase;
use Drupal\tmgmt\Translator\AvailableResult;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\venga_translator\VengaTranslatorAdapter;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileSystemInterface;
use psr\Log\LoggerInterface;

/**
 * Venga translator.
 *
 * @TranslatorPlugin(
 *   id = "venga_translator",
 *   label = @Translation("Venga translator"),
 *   description = @Translation("Venga translator that exports and imports files."),
 *   ui = "Drupal\venga_translator\VengaTranslatorUi"
 * )
 */
class VengaTranslator extends TranslatorPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * File system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Venga adapter.
   *
   * @var \Drupal\venga_translator\VengaTranslatorAdapter
   */
  protected $vengaAdapter;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('logger.factory')->get('venga_translator')
    );
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
      if (!$adapter = venga_translator_adapter($translator, $this->fileSystem, $this->logger)) {
        return NULL;
      }
      $this->setVengaAdapter($adapter);
    }
    return $this->vengaAdapter;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedRemoteLanguages(TranslatorInterface $translator) {
    $languages = [];
    if ($adapter = $this->getVengaAdapter($translator)) {
      try {
        if ($language_map = $adapter->getLanguages()) {
          foreach ($language_map as $langcode => $language) {
            $languages[$langcode] = $language->getName();
          }
        }
      }
      catch (XtrfRequestSslInsecure $e) {
        drupal_set_message(t('Cannot get languages from XTRF server. A secure SSL connection could not be established. Please adjust your translation provider settings and contact your provider if this error persists.'), 'error');
      }
      catch (RequestException $e) {
        drupal_set_message(t('Unable to connect to the XTRF server. Please verify your translation provider settings and contact your provider if this error persists.'), 'error');
      }
    }
    return $languages;
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
  public function checkAvailable(TranslatorInterface $translator) {
    if (
      $translator->getSetting('username') &&
      $translator->getSetting('password') &&
      $translator->getSetting('project_service') &&
      $translator->getSetting('project_currency') &&
      $translator->getSetting('url')
    ) {
      return AvailableResult::yes();
    }
    return AvailableResult::no(t('Please check the Venga translator configuration at @link.', [
      '@link' => Link::fromTextAndUrl(t('Content language and translation'), Url::fromRoute('language.content_settings_page'))->toString(),
    ]));
  }

  /**
   * Gets the quote of the job.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The job entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The quote entity of the job, null if does not exist.
   */
  public function getQuoteFromJob(JobInterface $job) {
    $quote = NULL;
    $result = $this->entityTypeManager->getStorage('venga_translator_quote')
      ->getQuery()
      ->condition('jobs', $job->id())
      ->execute();
    if ($result) {
      $quote_id = reset($result);
      $quote = $this->entityTypeManager->getStorage('tmgmt_translator')->load($quote_id);
    }

    return $quote;
  }

  /**
   * {@inheritdoc}
   */
  public function requestTranslation(JobInterface $job) {
    $adapter = $this->getVengaAdapter($job->getTranslator());
    if ($adapter->sendJobForTranslation($job)) {
      $job->submitted('The translation job has been submitted.');

      // Manually create a quote if the job does not have it.
      if (!$quote = $this->getQuoteFromJob($job)) {
        $quote = $this->entityTypeManager->getStorage('venga_translator_quote')->create();
        $quote->name = $job->getSetting('project_name');
        $quote->source_language = $job->getSourceLanguage();
        $quote->jobs = ['entity' => $job];
        $quote->user_id = $job->getOwner();
        $quote->translator = $job->getTranslator();
        $quote->workflow = $job->getSetting('workflow');
        $quote->notes = $job->getSetting('notes');
        if ($date = $job->getSetting('complete_by')) {
          $deadline = strtotime($date);
          $quote->complete_by = $deadline;
        }
        $quote->save();
      }
    }
  }

  /**
   * Requests a Venga quote translation for the given quote entity.
   *
   * @param \Drupal\venga_translator\QuoteInterface $quote
   *   The given quote entity.
   */
  public function requestQuoteTranslation(\Drupal\venga_translator\QuoteInterface $quote) {
    foreach ($quote->getJobs() as $job) {
      $adapter = $this->getVengaAdapter($job->getTranslator());
      if ($adapter->sendJobForTranslation($job)) {
        $job->submitted('The translation job has been submitted.');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasCheckoutSettings(JobInterface $job) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function abortTranslation(JobInterface $job) {
    $job->addMessage('Venga has no cancel option.');
    return FALSE;
  }

}
