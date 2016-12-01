<?php

/**
 * @file
 * Contains the XtrfRestClient class.
 */

use \GuzzleHttp\Exception\ClientException;
use \drunomics\XtrfClient\XtrfClient;
use \drunomics\XtrfClient\Model\XtrfDate;
use \drunomics\XtrfClient\Model\XtrfQuoteRequest;
use \drunomics\XtrfClient\Model\XtrfLanguage;
use \drunomics\XtrfClient\Model\XtrfWorkflow;
use \drunomics\XtrfClient\Model\XtrfSpecialization;
use \drunomics\XtrfClient\Model\XtrfPerson;

/**
 * Bridges TMGMT plugin to XTRF via its REST service.
 */
class XtrfTranslatorAdaptor {

  /**
   * A configured xtrf client instances.
   *
   * @var \drunomics\XtrfClient\XtrfClient
   */
  private $client;

  /**
   * File Formatter for the XML Export/Import.
   *
   * @var TMGMTFileFormatInterface
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
   * @param \drunomics\XtrfClient\XtrfClient
   *   A configured xtrf client instances.
   */
  public function __construct(XtrfClient $client) {
    $this->client = $client;
  }

  /**
   * Gets a XTRF language by symbol.
   *
   * @param string $symbol
   *   The XTRF language symbol, e.g. "DE-DE".
   *
   * @return XtrfLanguage|null
   *   The language found, or NULL if no language was found.
   */
  protected function getLanguage($symbol) {
    if (!isset($this->languageMap)) {
      foreach ($this->client->getLanguages() as $language) {
        $this->languageMap[$language->getSymbol()] = $language;
      }
    }
    return isset($this->languageMap[$symbol]) ? $this->languageMap[$symbol] : NULL;
  }

  /**
   * Maps a Drupal language to the source language.
   *
   * @param $langcode
   *   The langcode given.
   *
   * @return XtrfLanguage
   *   The language found if no language was found.
   *
   * @throws InvalidArgumentException
   *   Thrown if the langcode cannot be mapped.
   */
  protected function mapLanguage($langcode) {
    $langcode = strtoupper($langcode);
    $language = $this->getLanguage($langcode);
    if (!isset($language)) {
      throw new InvalidArgumentException(format_string('Unknown language @langcode specified. Be sure that XTRF has the respective language configured.', array('@langcode' => $langcode)));
    }
    return $language;
  }

  /**
   * Sends the files of a Job to XTRF.
   *
   * @return bool
   *   TRUE in case of success, FALSE otherwise.
   */
  public function sendFileForTranslation(TMGMTJob $job) {
    if ($job->getSetting('xtrf_translator_quote_id') === NULL) {
      if ($quote_request = $this->createQuote($job)) {
        $job->settings['xtrf_translator_quote_id'] = $quote_request->getCustomerProjectNumber();
      }
    }
    $job->save();
    // In error case, return FALSE.
    return !empty($quote_request);
  }

  /**
   * Clean up after project creation.
   */
  protected function cleanUp($job) {
    $temp_directory = $job->getSetting('temp_directory');
    if (!empty($temp_directory)) {
      file_unmanaged_delete_recursive($temp_directory);
    }
  }



  /**
   * Creates a quote for the given job.
   *
   * @return \drunomics\XtrfClient\Model\XtrfQuoteRequest|false
   *   The quote request, or FALSE if there were any errors.
   */
  protected function createQuote(TMGMTJob $job) {
    try {
      $date = $job->getSetting('complete_by');
      $deadline = strtotime($date['day'] . '-' . $date['month'] . '-' . $date['year']);

      $quote_request = (new XtrfQuoteRequest())
        ->setName($job->getSetting('project_name'))
        ->setNotes($job->getSetting('notes'))
        ->setWorkflow((new XtrfWorkflow())
          ->setName($job->getSetting('workflow'))
        )
        ->setDeliveryDate((new XtrfDate())
          ->setMillisGMT($deadline * 1000)
        )
        ->setSourceLanguage($this->mapLanguage($job->source_language))
        ->setTargetLanguages([
          ($this->mapLanguage($job->target_language))
        ])
        // @todo: Make specialisation configurable?
        ->setSpecialization((new XtrfSpecialization())
          ->setName("General")
        )
        // @todo: Make person configurable?
        ->setPerson((new XtrfPerson())
          ->setId(2174)
        )
        ->setFiles($this->writeFile($job))
        ->setCustomerProjectNumber(ctools_uuid_generate());

      $this->client->createQuote($quote_request);

      // Keep the file name we use for importing translation results later.
      $job->settings['filename'] = $quote_request->getFiles()[0]->getName();

      return $quote_request;
    }
    catch (ClientException $e) {
      $this->addMessage('Quote creation error - @msg', array('@msg' => $e->getMessage()), 'error', $job);
      return FALSE;
    }
    catch (InvalidArgumentException $e) {
      $this->addMessage('Quote creation error - @msg', array('@msg' => $e->getMessage()), 'error', $job);
      return FALSE;
    }
  }

  /**
   * Writes the XLIFF file containing translation sources.
   *
   * @param TMGMTJob $job
   *   The TMGMT job.
   *
   * @return \drunomics\XtrfClient\Model\XtrfFile[]
   *   The created files.
   */
  protected function writeFile(TMGMTJob $job) {
    $controller = new TMGMTFileformatXLIFF();
    $data = $controller->export($job);
    $filename = "job-$job->tjid-$job->source_language-$job->target_language.xliff";
    $files = $this->client->uploadFile($filename, $data);
    return $files;
  }

  /**
   * Add message to job or if no job given log the message.
   */
  protected function addMessage($msg, $variables = array(), $status = 'status', $job = NULL) {
    if (isset($job)) {
      $job->addMessage($msg, $variables, $status);
    }
    else {
      switch ($status) {
        case 'status':
          $severity = WATCHDOG_INFO;
          break;
        case 'error':
          $severity = WATCHDOG_ERROR;
          break;
        case 'warning':
          $severity = WATCHDOG_WARNING;
          break;
        case 'debug':
          $severity = WATCHDOG_DEBUG;
          break;
        default:
          $severity = WATCHDOG_NOTICE;
          break;
      }
      watchdog('xtrf_translator', $msg, $variables, $severity);
    }
  }

  /**
   * Retrieves the XTRF project for the given job.
   *
   * @param \TMGMTJob $job
   *   The tmgmt job.
   *
   * @return \drunomics\XtrfClient\Model\XtrfProject|null
   *   The project or NULL if no project could be retrieved.
   */
  protected function getProjectForJob(TMGMTJob $job) {
    try {
      $projects = $this->client->getProjects([
        'customerProjectNumber' => $job->getSetting('xtrf_translator_quote_id'),
      ]);
      if (isset($projects[0])) {
        return $projects[0];
      }
    }
    catch (ClientException $e) {
      $this->addMessage('Could not retrieve the project - @msg', array('@msg' => $e->getMessage()), 'error', $job);
    }
  }

  /**
   * Checks for new translated files.
   *
   * @return bool
   *   Whether the job has translated files and is ready for further processing.
   */
  public function checkForTranslationOutput(TMGMTJob $job) {
    $return = FALSE;
    if ($project = $this->getProjectForJob($job)) {
      if ($project->getStatus() == 'CLOSED' && $project->getHasOutputFiles()) {
        $return = TRUE;
      }
      elseif ($project->getStatus() == 'CANCELLED') {
        $job->addMessage('Job was cancelled remotely.', array(), 'status');
        $job->cancelled();
        $return = FALSE;
      }
      // Remember the project id, so we know the job has been dealt with.
      // @see xtrf_translator_cron().
      $job->reference = $project->getId();
      $job->save();
    }
    return $return;
  }

  /**
   * Download a translated file and imports the data to the job.
   *
   * @param $translator
   *   The XTRF translator.
   * @param TMGMTJob $job
   *   The job.
   *
   * @return FALSE|null
   *   FALSE, if the operation failed and should be re-queued.
   */
  public function downloadTranslatedFile($translator, TMGMTJob $job) {
    // If download was successfully import the file.
    if ($file_uri = $this->downloadFile($job)) {
      $this->importFile($file_uri, $job);
    }
    else {
      return FALSE;
    }
  }

  /**
   * Returns of a file from a given url.
   */
  protected function downloadFile(TMGMTJob $job) {
    try {
      $project = $this->getProjectForJob($job);
      $file_stream = $this->client->getProjectOutputFilesAsZip($project->getId());

      $filename = 'xtrf.zip';
      $directory = file_build_uri('xtrf_translator_' . $job->tjid);
      $job->settings['temp_directory'] = $directory;
      $file_uri = $directory . '/' . $filename;
      file_prepare_directory($directory, FILE_CREATE_DIRECTORY);
      file_put_contents($file_uri, $file_stream->getContents());
      return $file_uri;
    }
    catch (ClientException $e) {
      $this->addMessage('Could not download the file for the given job - @msg', array('@msg' => $e->getMessage()), 'error', $job);
    }
  }

  /**
   * Imports a xliff translation file.
   */
  protected function importFile($uri, $job) {
    $uri = $this->unzipFile($uri, $job);
    if (empty($uri)) {
      $this->cleanUp($job);
      return FALSE;
    }
    $controller = new TMGMTFileformatXLIFF();
    $validated_job = $controller->validateImport($uri);
    if (!$validated_job) {
      $job->addMessage('Failed to validate file, import aborted.', array(), 'error');
      $this->cleanUp($job);
      return FALSE;
    }
    elseif ($validated_job->tjid != $job->tjid) {
      $uri = $validated_job->uri();
      $label = $validated_job->label();
      $job->addMessage('Import file is from job <a href="@url">@label</a>, import aborted.', array('@url' => url($uri['path']), '@label' => $label));
      $this->cleanUp($job);
      return FALSE;
    }
    else {
      try {
        // Validation successful, start import.
        $job->addTranslatedData($controller->import($uri));
        $this->cleanUp($job);
        return TRUE;
      }
      catch (Exception $e) {
        $job->addMessage('File import failed with the following message: @message', array('@message' => $e->getMessage()), 'error');
        $this->cleanUp($job);
        return FALSE;
      }
    }
  }

  /**
   * Unzips a file.
   */
  protected function unzipFile($uri, $job) {
    // Get the absolute path to $file.
    $uri = drupal_realpath($uri);
    $path = pathinfo(realpath($uri), PATHINFO_DIRNAME);

    $zip = new ZipArchive;
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

}
