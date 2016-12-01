<?php

/**
 * @file
 * Contains \Drupal\venga_translator\QuoteInterface.
 */

namespace Drupal\venga_translator;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Quote entities.
 *
 * @ingroup venga_translator
 */
interface QuoteInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the Quote name.
   *
   * @return string
   *   Name of the Quote.
   */
  public function getName();

  /**
   * Sets the Quote name.
   *
   * @param string $name
   *   The Quote name.
   *
   * @return \Drupal\venga_translator\QuoteInterface
   *   The called Quote entity.
   */
  public function setName($name);

  /**
   * Gets the customer project number.
   *
   * @return string
   *   Name of the customer project number.
   */
  public function getCustomerProjectNumber();

  /**
   * Sets the customer project number.
   *
   * @param string $customer_project_number
   *   The customer project number.
   *
   * @return \Drupal\venga_translator\QuoteInterface
   *   The called Quote entity.
   */
  public function setCustomerProjectNumber($customer_project_number);

  /**
   * Gets the Quote creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Quote.
   */
  public function getCreatedTime();

  /**
   * Gets the Quote complete by timestamp.
   *
   * @return int
   *   Complete by timestamp of the Quote.
   */
  public function getCompleteBy();

  /**
   * Sets the Quote creation timestamp.
   *
   * @param int $timestamp
   *   The Quote creation timestamp.
   *
   * @return \Drupal\venga_translator\QuoteInterface
   *   The called Quote entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the translator for this quote.
   *
   * @return \Drupal\tmgmt\TranslatorInterface
   *   The translator entity.
   */
  public function getTranslator();

  /**
   * Returns the translator ID for this quote.
   *
   * @return int|null
   *   The translator ID or NULL if there is none.
   */
  public function getTranslatorId();

  /**
   * Returns the translator plugin of the translator of this quote.
   *
   * @return \Drupal\tmgmt\TranslatorPluginInterface
   *   The translator plugin instance.
   *
   * @throws \Drupal\venga_translator\VengaTranslatorException
   *   Throws an exception when there is no translator assigned or when the
   *   translator is missing the plugin.
   */
  public function getTranslatorPlugin();

  /**
   * Sets the translator for this quote.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator entity.
   *
   * @return \Drupal\venga_translator\QuoteInterface
   *   The called Quote entity.
   */
  public function setTranslator($translator);

  /**
   * Checks if the translator and the plugin exist.
   *
   * @return bool
   *   TRUE if exists, FALSE otherwise.
   */
  public function hasTranslator();

  /**
   * Gets the Quote service.
   *
   * @return string
   *   Service of the Quote.
   */
  public function getService();

  /**
   * Gets the Quote jobs.
   *
   * @return \Drupal\tmgmt\JobInterface[]
   *   Jobs of the Quote.
   */
  public function getJobs();

  /**
   * Sets the Quote service.
   *
   * @param string $service
   *   The Quote service.
   *
   * @return \Drupal\venga_translator\QuoteInterface
   *   The called Quote entity.
   */
  public function setService($service);

  /**
   * Gets the Quote specialization.
   *
   * @return string
   *   Service of the Quote.
   */
  public function getSpecialization();

   /**
   * Sets the Quote specialization.
   *
   * @param string $specialization
   *   The Quote specialization.
   *
   * @return \Drupal\venga_translator\QuoteInterface
   *   The called Quote entity.
   */
  public function setSpecialization($specialization);

  /**
   * Gets auto accept.
   *
   * @return string
   *   Auto accept value.
   */
  public function getAutoAccept();

  /**
   * Sets auto accept.
   *
   * @param string $auto_accept
   *   The auto accept value.
   *
   * @return \Drupal\venga_translator\QuoteInterface
   *   The called Quote entity.
   */
  public function setAutoAccept($auto_accept);

  /**
   * Gets the Quote contact person ID.
   *
   * @return int
   *   Contact person ID of the Quote.
   */
  public function getPerson();

  /**
   * Gets the Quote "send back to" person ID.
   *
   * @return int
   *   "Send back to" person ID of the Quote.
   */
  public function getSendBackTo();

  /**
   * Gets the Quote additional person IDs.
   *
   * @return int[]
   *   Additional person IDs of the Quote.
   */
  public function getAdditionalPersons();

  /**
   * Gets the Quote source language.
   *
   * @return string
   *   Source language of the Quote.
   */
  public function getSourceLanguage();

  /**
   * Gets the Quote target language.
   *
   * Gets target langcode of all the quote jobs.
   *
   * @return string[]
   *   Target language codes.
   */
  public function getTargetLanguages();

  /**
   * Returns remote source language code.
   *
   * Maps the source langcode of the quote from local to remote.
   *
   * @return string
   *   Remote language code.
   */
  public function getRemoteSourceLanguage();

  /**
   * Returns remote target language codes.
   *
   * Maps the target langcode of the quote jobs from local to remote.
   *
   * @return string[]
   *   Remote language codes.
   */
  public function getRemoteTargetLanguages();

  /**
   * Sets the Quote source language.
   *
   * @param string $source_language
   *   The Quote source language.
   *
   * @return \Drupal\venga_translator\QuoteInterface
   *   The called Quote entity.
   */
  public function setSourceLanguage($source_language);

  /**
   * Gets the Quote notes.
   *
   * @return string
   *   Notes of the Quote.
   */
  public function getNotes();

}
