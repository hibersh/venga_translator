<?php

/**
 * @file
 * Contains \Drupal\venga_translator\XtrfTranslatorAdapter.
 */

namespace Drupal\venga_translator;

use Drupal\tmgmt_file\Format\FormatInterface;
use Drupal\tmgmt_file\Plugin\tmgmt_file\Format\Xliff;
use drunomics\XtrfClient\XtrfClient;
use drunomics\XtrfClient\Model\XtrfDate;
use drunomics\XtrfClient\Model\XtrfQuoteRequest;
use drunomics\XtrfClient\Model\XtrfLanguage;
use drunomics\XtrfClient\Model\XtrfWorkflow;
use drunomics\XtrfClient\Model\XtrfSpecialization;
use drunomics\XtrfClient\Model\XtrfPerson;
use Drupal\tmgmt\JobInterface;
use Drupal\Core\File\FileSystemInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Drupal\Core\Render\Element;
use Drupal\Component\Utility\Xss;

/**
 * Bridges TMGMT plugin to Venga via its REST service.
 */
class VengaTranslatorAdapter {

  /**
   * A configured xtrf client instances.
   *
   * @var \drunomics\XtrfClient\XtrfClient
   */
  protected $client;

  /**
   * A file system instance.
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
   * File Formatter for the XML Export/Import.
   *
   * @var FormatInterface
   */
  protected $formatter;

  /**
   * Array of available languages, keyed by language symbol (langcode).
   *
   * @var XtrfLanguage[]
   */
  protected $languageMap;

  /**
   * Object constructor.
   *
   * @param \drunomics\XtrfClient\XtrfClient $client
   *   The configured xtrf client instances.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(XtrfClient $client, FileSystemInterface $file_system, LoggerInterface $logger) {
    $this->client = $client;
    $this->fileSystem = $file_system;
    $this->logger = $logger;
  }

  /**
   * Gets the Default office.
   *
   * @return \drunomics\XtrfClient\Model\XtrfOffice|null
   *   The default office, or null if no office was found.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   *   Thrown if there are HTTP connection problems.
   */
  public function getDefaultOffice() {
    $office = $this->client->getDefaultOffice();
    return $office;
  }

  /**
   * Gets the specializations list.
   *
   * @return \drunomics\XtrfClient\Model\XtrfSpecialization[]
   *   The specializations array.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   *   Thrown if there are HTTP connection problems.
   */
  public function getSpecializations() {
    $specializations = $this->client->getSpecializations();
    return $specializations;
  }

  /**
   * Gets the services list.
   *
   * @return \drunomics\XtrfClient\Model\XtrfService[]
   *   The services array.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   *   Thrown if there are HTTP connection problems.
   */
  public function getServices() {
    $specializations = $this->client->getServices();
    return $specializations;
  }

  /**
   * Gets contact persons.
   *
   * @return \drunomics\XtrfClient\Model\XtrfPerson[]|null
   *   The contact persons of current user, or null if no person was found.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   *   Thrown if there are HTTP connection problems.
   */
  public function getContactPersons() {
    $persons = $this->client->getContactPersons();
    return $persons;
  }

  /**
   * Gets a Venga language by symbol.
   *
   * @param string $symbol
   *   The Venga language symbol, e.g. "DE-DE".
   *
   * @return XtrfLanguage|null
   *   The language found, or null if no language was found.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   *   Thrown if there are HTTP connection problems.
   */
  protected function getLanguage($symbol) {
    $languages = $this->getLanguages();
    return isset($languages[$symbol]) ? $languages[$symbol] : NULL;
  }

  /**
   * Gets all the available Venga languages.
   *
   * @return \drunomics\XtrfClient\Model\XtrfLanguage[]|null
   *   Array of available languages, keyed by language symbol (langcode).
   *
   * @throws \GuzzleHttp\Exception\RequestException
   *   Thrown if there are HTTP connection problems.
   */
  public function getLanguages() {
    if (!isset($this->languageMap)) {
      foreach ($this->client->getLanguages() as $language) {
        $this->languageMap[$language->getSymbol()] = $language;
      }
    }
    return $this->languageMap;
  }

  /**
   * Maps a Drupal language to the source language.
   *
   * @param $langcode
   *   The langcode given.
   *
   * @return \drunomics\XtrfClient\Model\XtrfLanguage
   *   The language found if no language was found.
   *
   * @throws \InvalidArgumentException
   *   Thrown if the langcode cannot be mapped.
   */
  protected function mapLanguage($langcode) {
    $langcode = strtoupper($langcode);
    $language = $this->getLanguage($langcode);
    if (!isset($language)) {
      throw new \InvalidArgumentException(format_string('Unknown language @langcode specified. Be sure that Venga has the respective language configured.', array('@langcode' => $langcode)));
    }
    return $language;
  }

  /**
   * Sends the files of a Job to Venga.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The translation job.
   *
   * @return bool
   *   TRUE in case of success, FALSE otherwise.
   */
  public function sendJobForTranslation(JobInterface $job) {
    if ($job->getSetting('venga_translator_quote_id_number') === NULL) {
      if ($result = $this->createQuoteFromJob($job)) {
        // Save quote/project id number to make it searchable at the
        // \Drupal\venga_translator\VengaTranslatorAdapter::getProjectForJob().
        $this->setJobSetting($job, 'venga_translator_quote_id_number', $result->getIdNumber());
      }
    }
    $job->save();
    // In error case, return FALSE.
    return !empty($result);
  }

  /**
   * Clean up after project creation.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The translation job.
   */
  protected function cleanUp(JobInterface $job) {
    $temp_directory = $job->getSetting('temp_directory');
    if (!empty($temp_directory)) {
      file_unmanaged_delete_recursive($temp_directory);
    }
  }

  /**
   * Creates a quote for the given job.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The translation job.
   *
   * @return \drunomics\XtrfClient\Model\XtrfQuote
   *   The created quote.
   */
  public function createQuoteFromJob(JobInterface $job) {
    $params = [
      'project_name' => $job->getSetting('project_name'),
      'customer_project_number' => $job->getSetting('customer_project_number'),
      'service' => $job->getSetting('service'),
      'auto_accept' => $job->getSetting('auto_accept'),
      'source_language' => $this->mapLanguage($job->getRemoteSourceLanguage()),
      'target_languages' => [($this->mapLanguage($job->getRemoteTargetLanguage()))],
      'files' => $this->writeFile($job),
      'notes' => $job->getSetting('notes'),
      'specialization' => $job->getSetting('specialization'),
      'person' => $job->getSetting('person'),
      'send_back_to' => $job->getSetting('send_back_to') ?: $job->getSetting('person'),
      'additional_persons' => $job->getSetting('additional_persons'),
    ];
    if ($date = $job->getSetting('complete_by')) {
      $deadline = strtotime($date);
      $params['complete_by'] = $deadline;
    }

    if ($result = $this->createQuote($params)) {
      // Keep the file name we use for importing translation results later.
      $this->setJobSetting($job, 'filename', $this->composeJobFileName($job));
    }

    return $result;
  }

  /**
   * Creates a quote for the given job.
   *
   * @param string[] $params
   *   Array with quote parameters:
   *     - project_name: Quote project name.
   *     - customer_project_number: Quote project number.
   *     - service: Quote service.
   *     - auto_accept: Boolean flag to auto convert the quote into a project.
   *     - source_language: Source translation language.
   *     - target_languages: Target translation languages array.
   *     - files: Translation files.
   *     - notes: Quote notes.
   *     - specialization: Quote specialization.
   *     - person: Quote contact person ID.
   *     - send_back_to: Quote "send back to" person ID.
   *     - additional_persons: (optional) Quote additional persons IDs.
   *     - complete_by: (optional) Quote complete by date timestamp.
   *
   * @return \drunomics\XtrfClient\Model\XtrfQuote
   *   The created quote.
   */
  protected function createQuote(array $params) {
    try {
      $quote_request = (new XtrfQuoteRequest())
        ->setName($params['project_name'])
        ->setAutoAccept($params['auto_accept'])
        ->setNotes($params['notes'])
        ->setService((new XtrfWorkflow())
          ->setName($params['service'])
        )
        ->setSourceLanguage($params['source_language'])
        ->setTargetLanguages($params['target_languages'])
        ->setSpecialization((new XtrfSpecialization())
          ->setName($params['specialization'])
        )
        ->setFiles($params['files'])
        ->setPerson((new XtrfPerson())->setId($params['person']))
        ->setSendBackTo((new XtrfPerson())->setId($params['send_back_to']))
        ->setCustomerProjectNumber($params['customer_project_number']);

      if (!empty($params['additional_persons'])) {
        $additional_persons = [];
        foreach ($params['additional_persons'] as $additional_person) {
          $additional_persons[] = (new XtrfPerson())->setId($additional_person);
        }
        $quote_request->setAdditionalPersons($additional_persons);
      }

      if (!empty($params['complete_by']) && $date = $params['complete_by']) {
        $quote_request->setDeliveryDate((new XtrfDate())
          ->setMillisGMT($date * 1000)
        );
      }

      $result = $this->client->createQuote($quote_request);

      return $result;
    }
    catch (RequestException $e) {
      $this->addMessage('Quote creation error - @msg', array('@msg' => $e->getMessage()), 'error');
      return FALSE;
    }
    catch (\InvalidArgumentException $e) {
      $this->addMessage('Quote creation error - @msg', array('@msg' => $e->getMessage()), 'error');
      return FALSE;
    }
  }

  /**
   * Writes the XLIFF file containing translation sources.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The translation job.
   *
   * @return \drunomics\XtrfClient\Model\XtrfFile[]
   *   The created files.
   */
  protected function writeFile(JobInterface $job) {
    $controller = new Xliff();
    $data = $controller->export($job);
    $filename = $this->composeJobFileName($job);
    $files = $this->client->uploadFile($filename, $data);
    return $files;
  }

  /**
   * Composes XLIFF file name for job translation.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The translation job.
   *
   * @return string
   *   File name.
   */
  protected function composeJobFileName(JobInterface $job) {
    return "job-{$job->id()}-{$job->getRemoteSourceLanguage()}-{$job->getRemoteTargetLanguage()}.xliff";
  }

  /**
   * Add message to job or if no job given log the message.
   *
   * @param string $message
   *   The log message.
   * @param array $context
   *   (optional) Message context.
   * @param string $status
   *   (optional) Log message status. Allowed values: status, error, warning,
   *   debug, notice. 'status' by default.
   * @param \Drupal\tmgmt\JobInterface $job
   *   The translation job.
   */
  protected function addMessage($message, $context = [], $status = 'status', $job = NULL) {
    if (isset($job)) {
      $job->addMessage($message, $context, $status);
    }
    else {
      switch ($status) {
        case 'status':
          $this->logger->info($message, $context);
          break;
        case 'error':
          $this->logger->error($message, $context);
          break;
        case 'warning':
          $this->logger->warning($message, $context);
          break;
        case 'debug':
          $this->logger->debug($message, $context);
          break;
        case 'notice':
        default:
          $this->logger->notice($message, $context);
          break;
      }
    }
  }

  /**
   * Retrieves the Venga project for the given job.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The translation job.
   *
   * @return \drunomics\XtrfClient\Model\XtrfProject|null
   *   The project or NULL if no project could be retrieved.
   */
  protected function getProjectForJob(JobInterface $job) {
    try {
      $projects = $this->client->getProjects([
        'search' => $job->getSetting('venga_translator_quote_id_number'),
      ]);
      if (isset($projects[0])) {
        return $projects[0];
      }
    }
    catch (RequestException $e) {
      $this->addMessage('Could not retrieve the project - @msg', array('@msg' => $e->getMessage()), 'error', $job);
    }
  }

  /**
   * Checks for new translated files.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The translation job.
   *
   * @return bool
   *   Whether the job has translated files and is ready for further processing.
   */
  public function checkForTranslationOutput(JobInterface $job) {
    $return = FALSE;
    if ($project = $this->getProjectForJob($job)) {
      $processed = FALSE;
      if ($project->getStatus() == 'CLOSED' && $project->getHasOutputFiles()) {
        $return = TRUE;
        $processed = TRUE;
      }
      elseif ($project->getStatus() == 'CANCELLED') {
        $job->addMessage('Job was aborted remotely.', array(), 'status');
        $job->aborted();
        $return = FALSE;
        $processed = TRUE;
      }

      if ($processed){
        // Remember the project id, so we know the job has been dealt with.
        // @see venga_translator_cron().
        $job->reference = $project->getId();
        $job->save();
      }
    }
    return $return;
  }

  /**
   * Download a translated file and imports the data to the job.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The translation job.
   *
   * @return FALSE|null
   *   FALSE, if the operation failed and should be re-queued.
   */
  public function downloadTranslatedFile(JobInterface $job) {
    // If download was successfully import the file.
    if ($file_uri = $this->downloadFile($job)) {
      return $this->importFile($file_uri, $job);
    }
    else {
      return FALSE;
    }
  }

  /**
   * Returns of a file from a given url.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The translation job.
   *
   * @return string
   *   File uri.
   */
  protected function downloadFile(JobInterface $job) {
    try {
      $project = $this->getProjectForJob($job);
      $file_stream = $this->client->getProjectOutputFilesAsZip($project->getId());

      $filename = 'venga.zip';
      $directory = file_build_uri('venga_translator_' . $job->id());
      $this->setJobSetting($job, 'temp_directory', $directory);
      $file_uri = $directory . '/' . $filename;
      file_prepare_directory($directory, FILE_CREATE_DIRECTORY);
      file_put_contents($file_uri, $file_stream->getContents());
      return $file_uri;
    }
    catch (RequestException $e) {
      $this->addMessage('Could not download the file for the given job - @msg', array('@msg' => $e->getMessage()), 'error', $job);
    }
  }

  /**
   * Imports a xliff translation file.
   *
   * @param string $uri
   *   The file uri.
   * @param \Drupal\tmgmt\JobInterface $job
   *   The translation job.
   *
   * @return bool
   *   FALSE on failure.
   */
  protected function importFile($uri, JobInterface $job) {
    $uri = $this->unzipFile($uri, $job);
    if (empty($uri)) {
      $this->cleanUp($job);
      return FALSE;
    }
    $controller = new Xliff();
    $validated_job = $controller->validateImport($uri);
    if (!$validated_job) {
      $job->addMessage('Failed to validate file, import aborted.', array(), 'error');
      $this->cleanUp($job);
      return FALSE;
    }
    elseif ($validated_job->id() != $job->id()) {
      $job->addMessage('Import file is from job <a href="@url">@label</a>, import aborted.', [
        '@url' => $validated_job->toUrl()->toString(),
        '@label' => $validated_job->label(),
      ]);
      $this->cleanUp($job);
      return FALSE;
    }
    else {
      try {
        // Validation successful, start import.
        $data = $controller->import($uri);
        // If the translation contains HTML entities, decode them to have proper
        // HTML back in the content.
        $data_decoded = $this->decodeHtmlRecursive($data);
        $job->addTranslatedData($data_decoded);
        $this->cleanUp($job);
        return TRUE;
      }
      catch (\Exception $e) {
        $job->addMessage('File import failed with the following message: @message', array('@message' => $e->getMessage()), 'error');
        $this->cleanUp($job);
        return FALSE;
      }
    }
  }

  /**
   * Decodes HTML entities in the nested tmgmt translation data.
   *
   * If the translation contains HTML entities, decode them to have a proper
   * HTML back in the content.
   *
   * @param array $data
   *   The tmgmt translation data.
   *
   * @return array
   *   The tmgmt translation data with decoded HTML entities.
   */
  protected function decodeHtmlRecursive(array $data) {
    foreach (Element::children($data) as $key) {
      $data_item = $data[$key];
      if (!empty($data_item['#text'])) {
        $data[$key]['#text'] = Xss::filterAdmin(html_entity_decode($data_item['#text']));
      }
      else {
        $data[$key] = $this->decodeHtmlRecursive($data_item);
      }
    }
    return $data;
  }

  /**
   * Unzips a file.
   *
   * @param string $uri
   *   The file uri.
   * @param \Drupal\tmgmt\JobInterface $job
   *   The translation job.
   *
   * @return string|boolean
   *   Unzipped file, false on failure.
   */
  protected function unzipFile($uri, JobInterface $job) {
    // Get the absolute path to $file.
    $uri = $this->fileSystem->realpath($uri);
    $path = pathinfo(realpath($uri), PATHINFO_DIRNAME);

    $zip = new \ZipArchive;
    $resource = $zip->open($uri);
    if ($resource === TRUE) {
      // Extract it to the path we determined above.
      $zip->extractTo($path);
      $zip->close();

      // Look for translation files within zip subfolders.
      $translation_file_name = $job->getSetting('filename');
      $translation_file_prefix = $job->getSetting('temp_directory');
      if ($handle = opendir($path)) {
        while (false !== ($entry = readdir($handle))) {
          $sub_path = "$path/$entry";
          if (is_dir($sub_path) && $entry != '.' && $entry != '..') {
            $translation_file_path = "$sub_path/$translation_file_name";
            if (is_file($translation_file_path)) {
              // Build a drupal path.
              $unzipped_file = $translation_file_prefix . substr($translation_file_path, strlen($path));
              break;
            }
          }
        }
        closedir($handle);
      }
      if (!empty($unzipped_file)) {
        return $unzipped_file;
      }
      else {
        $job->addMessage('No file found in provided zip archive, import aborted.', array(), 'error');
        return FALSE;
      }
    }
    else {
      $job->addMessage('Failed to open zipped archive, import aborted.', array(), 'error');
      return FALSE;
    }
  }

  /**
   * Sets value of the job settings.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The translation job.
   * @param string $name
   *   The setting name.
   * @param mixed $value
   *   The setting value.
   */
  protected function setJobSetting(JobInterface $job, $name, $value) {
    $settings = $job->settings->getValue();
    $settings[0][$name] = $value;
    $job->settings->setValue($settings);
  }

}
