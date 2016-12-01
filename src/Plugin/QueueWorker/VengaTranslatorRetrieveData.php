<?php
/**
 * @file
 * Contains \Drupal\venga_translator\Plugin\QueueWorker\VengaTranslatorRetrieveData.
 */

namespace Drupal\venga_translator\Plugin\QueueWorker;

use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Url;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\venga_translator\VengaTranslatorAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\File\FileSystemInterface;
use psr\Log\LoggerInterface;

/**
 * @QueueWorker(
 *   id = "venga_translator_retrieve_data",
 *   title = @Translation("Venga translator retrieve data"),
 *   cron = {"time" = 120}
 * )
 */
class VengaTranslatorRetrieveData extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The queue object.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

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
   * Venga adapters keyed by translator ids.
   *
   * @var \Drupal\venga_translator\VengaTranslatorAdapter[]
   */
  protected $vengaAdapter;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, QueueInterface $queue, FileSystemInterface $file_system, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->queue = $queue;
    $this->logger = $logger;
    $this->fileSystem = $file_system;
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
      $container->get('queue')->get('venga_translator_retrieve_data', TRUE),
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
   * @return \Drupal\venga_translator\VengaTranslatorAdapter
   *   The Venga adapter for the translator.
   */
  public function getVengaAdapter(TranslatorInterface $translator) {
    if (empty($this->vengaAdapter)) {
      $adapter = venga_translator_adapter($translator, $this->logger, $this->fileSystem);
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
   *
   * Downloads and imports files available from Venga.
   */
  public function processItem($data) {
    $translator = $this->entityTypeManager->getStorage('tmgmt_translator')->load($data->translator);
    if ($translator instanceof Translator && $translator->checkAvailable()) {
      /* @var \Drupal\tmgmt\JobInterface $job */
      if ($job = $this->entityTypeManager->getStorage('tmgmt_job')->load($data->tjid)) {
        $adapter = $this->getVengaAdapter($translator);
        $result = $adapter->downloadTranslatedFile($job);
        if ($result !== FALSE) {
          $this->logger->info('Finished processing translation file for Job <a href="@url">@label</a>.', [
            '@url' => $job->toUrl()->toString(),
            '@label' => $job->label(),
          ]);
        }
        else {
          // If download failed, add it to the queue again, for a later retry.
          // Cancel it after 3 retries.
          $data->retries = empty($data->retries) ? 0 : $data->retries + 1;
          if ($data->retries <= 3) {
            $this->queue->createItem($data);
          }
          else {
            $this->logger->error('@job could not be downloaded after 3 retries.', [
              '@job' => Link::fromTextAndUrl(t('Job @id', ['@id' => 1]), Url::fromRoute('entity.tmgmt_job.canonical', ['tmgmt_job' => 1]))->toString(),
            ]);
          }
        }
      }
      else {
        $this->logger->info('Removed job !job from queue - Failed to load job.', [
          '!job' => $data->tjid,
        ]);
      }
    }
  }

}