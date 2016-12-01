<?php

/**
 * @file
 * Contains \Drupal\venga_translator\Entity\Quote.
 */

namespace Drupal\venga_translator\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\venga_translator\QuoteInterface;
use Drupal\venga_translator\VengaTranslatorException;
use Drupal\user\UserInterface;

/**
 * Defines the Quote entity.
 *
 * @ingroup venga_translator
 *
 * @ContentEntityType(
 *   id = "venga_translator_quote",
 *   label = @Translation("Quote"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\venga_translator\QuoteListBuilder",
 *     "views_data" = "Drupal\venga_translator\Entity\QuoteViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\venga_translator\Form\QuoteForm",
 *       "add" = "Drupal\venga_translator\Form\QuoteForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "access" = "Drupal\venga_translator\QuoteAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\venga_translator\QuoteHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "venga_translator_quote",
 *   admin_permission = "administer quote entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *   },
 *   links = {
 *     "canonical" = "/quote/venga_translator_quote/{venga_translator_quote}",
 *     "add-form" = "/quote/venga_translator_quote/add",
 *     "delete-form" = "/quote/venga_translator_quote/{venga_translator_quote}/delete",
 *   },
 *   field_ui_base_route = "venga_translator_quote.settings"
 * )
 */
class Quote extends ContentEntityBase implements QuoteInterface {
  use EntityChangedTrait;
  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += array(
      'user_id' => \Drupal::currentUser()->id(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomerProjectNumber() {
    return $this->get('customer_project_number')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCustomerProjectNumber($customer_project_number) {
    $this->set('customer_project_number', $customer_project_number);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCompleteBy() {
    return $this->get('complete_by')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslator() {
    if ($this->hasTranslator()) {
      return $this->translator->entity;
    }
    else if (!$this->translator->entity) {
      throw new VengaTranslatorException('The quote has no provider assigned.');
    }
    else if (!$this->translator->entity->hasPlugin()) {
      throw new VengaTranslatorException('The translator assigned to this quote is missing the plugin.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslatorId() {
    return $this->get('translator')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslatorPlugin() {
    return $this->getTranslator()->getPlugin();
  }

  /**
   * {@inheritdoc}
   */
  public function setTranslator($translator) {
    $this->set('translator', $translator);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasTranslator() {
    return $this->translator->entity && $this->translator->target_id && $this->translator->entity->hasPlugin();
  }

  /**
   * {@inheritdoc}
   */
  public function getAutoAccept() {
    return $this->get('auto_accept')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setAutoAccept($auto_accept) {
    $this->set('auto_accept', $auto_accept);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getService() {
    return $this->get('service')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setService($service) {
    $this->set('service', $service);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSpecialization() {
    return $this->get('specialization')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSpecialization($specialization) {
    $this->set('specialization', $specialization);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPerson() {
    return $this->get('person')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSendBackTo() {
    return $this->get('send_back_to')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getAdditionalPersons() {
    $persons = [];
    foreach ($this->get('additional_persons')->getValue() as $value) {
      $persons[] = $value['value'];
    }
    return $persons;
  }

  /**
   * {@inheritdoc}
   */
  public function getJobs() {
    $jobs = [];
    foreach ($this->jobs as $job) {
      if ($job->entity) {
        $jobs[] = $job->entity;
      }
    }
    return $jobs;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceLanguage() {
    return $this->get('source_language')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteSourceLanguage() {
    return $this->getTranslator()->mapToRemoteLanguage($this->getSourceLanguage());
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetLanguages() {
    $languages = [];
    foreach ($this->getJobs() as $job) {
      $languages[] = $job->getSourceLangcode();
    }
    return $languages;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteTargetLanguages() {
    $languages = [];
    foreach ($this->getJobs() as $job) {
      $languages[] = $job->getRemoteTargetLanguage();
    }
    return $languages;
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceLanguage($source_language) {
    $this->set('source_language', $source_language);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getNotes() {
    return $this->get('notes')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Quote entity.'))
      ->setReadOnly(TRUE);
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Quote entity.'))
      ->setReadOnly(TRUE);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setDescription(t('The user ID of author of the quote.'))
      ->setSettings(array(
        'target_type' => 'user',
      ))
      ->setDefaultValue(0);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the quote.'))
      ->setRequired(TRUE)
      ->setSettings(array(
        'max_length' => 50,
        'text_processing' => 0,
      ))
      ->setDefaultValue('')
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => 0,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 0,
      ))
      ->setDisplayConfigurable('view', TRUE);

    $fields['customer_project_number'] = BaseFieldDefinition::create('string')
      ->setLabel(t('PO number'))
      ->setDescription(t('Specify the project number.'))
      ->setSettings(array(
        'max_length' => 250,
        'text_processing' => 0,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => 0,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'inline',
        'type' => 'string',
        'weight' => 0,
      ))
      ->setDisplayConfigurable('view', TRUE);

    $fields['source_language'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Source language'))
      ->setDescription(t('The source language code.'))
      ->setDisplayOptions('view', array(
        'label' => 'inline',
        'type' => 'string',
        'weight' => 1,
      ))
      ->setDisplayConfigurable('view', TRUE);

    $fields['target_languages'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Target languages'))
      ->setDescription(t('The source target language codes.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', array(
        'label' => 'Above',
        'type' => 'string',
        'weight' => 1,
      ))
      ->setDisplayConfigurable('view', TRUE);

    $fields['service'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Service'))
      ->setDescription(t('Select a service for the project.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values_function', 'venga_translator_get_service_options')
      ->setDefaultValue('')
      ->setDisplayOptions('form', array(
        'type' => 'select',
        'weight' => 2,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'inline',
        'weight' => 2,
      ))
      ->setDisplayConfigurable('view', TRUE);

    $fields['specialization'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Specialization'))
      ->setDescription(t('Select a specialization.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values_function', 'venga_translator_get_specialization_options')
      ->setDefaultValue('')
      ->setDisplayOptions('form', array(
        'type' => 'select',
        'weight' => 2,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'inline',
        'weight' => 2,
      ))
      ->setDisplayConfigurable('view', TRUE);

    $fields['complete_by'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Complete-By date'))
      ->setDescription(t('Set a date, when this quote should be finished.'))
      ->setDisplayOptions('form', array(
        'type' => 'datetime_timestamp',
        'weight' => 3,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'inline',
        'weight' => 3,
        'type' => 'timestamp',
      ))
      ->setDisplayConfigurable('view', TRUE);

    $fields['person'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('Contact person'))
      ->setDescription(t('Select the contact person.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values_function', 'venga_translator_get_person_options')
      ->setDefaultValue('')
      ->setDisplayOptions('form', array(
        'type' => 'select',
        'weight' => 4,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'weight' => 4,
      ))
      ->setDisplayConfigurable('view', TRUE);

    $fields['send_back_to'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('Send back to'))
      ->setDescription(t('Select the "Send back to" person. Defaults to the contact person.'))
      ->setSetting('allowed_values_function', 'venga_translator_get_person_options')
      ->setDefaultValue('')
      ->setDisplayOptions('form', array(
        'type' => 'select',
        'weight' => 4,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'weight' => 4,
      ))
      ->setDisplayConfigurable('view', TRUE);

    $fields['additional_persons'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('Additional persons'))
      ->setDescription(t('Select additional persons.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('allowed_values_function', 'venga_translator_get_person_options')
      ->setDefaultValue('')
      ->setDisplayOptions('form', array(
        'type' => 'select',
        'weight' => 5,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'weight' => 5,
      ))
      ->setDisplayConfigurable('view', TRUE);

    $fields['jobs'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Jobs'))
      ->setSettings(array(
        'target_type' => 'tmgmt_job',
      ))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', array(
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => array(
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ),
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'weight' => 5,
        'type' => 'entity_reference_label',
      ))
      ->setDisplayConfigurable('view', TRUE);

    $fields['translator'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Provider'))
      ->setDescription(t('The selected provider'))
      ->setSettings(array(
        'target_type' => 'tmgmt_translator',
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'weight' => 6,
        'type' => 'entity_reference_label',
      ))
      ->setDisplayConfigurable('view', TRUE);

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('(Optional) Add notes for the job.'))
      ->setDisplayOptions('form', array(
        'type' => 'string_textarea',
        'weight' => 7,
        'settings' => array(
          'rows' => 3,
        ),
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', array(
        'type' => 'string',
        'weight' => 7,
        'label' => 'above',
      ))
      ->setDisplayConfigurable('view', TRUE);

    $fields['auto_accept'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Start project without waiting for my approval'))
      ->setDescription(t('Mark this checkbox if you want Venga to start working on the project right away. The usual terms and rates will be applied automatically and a confirmation will be sent. Your dedicated project manager will contact you if any additional information is needed.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'settings' => [
          'display_label' => TRUE
        ],
        'weight' => 7,
      ])
      ->setDisplayConfigurable('view', TRUE);;

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

}
