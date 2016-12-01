<?php

/**
 * @file
 * Contains \Drupal\venga_translator\Form\QuoteForm.
 */

namespace Drupal\venga_translator\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\venga_translator\VengaTranslatorUi;

/**
 * Form controller for the Quote form.
 *
 * @ingroup venga_translator
 */
class QuoteForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#value'] = t('Request quote');

    // Attach javascript to pre-populate time.
    $form['#attached']['library'][] = 'venga_translator/datetime';
    // Attach javascript to toggle "Request project/quote" button title.
    $form['#attached']['library'][] = 'venga_translator/request_button';
    $form['#attached']['drupalSettings']['venga_translator']['quote_title'] = $this->t('Request quote');
    $form['#attached']['drupalSettings']['venga_translator']['project_title'] = $this->t('Request project');

    // Pre-populate quote fields from request parameters.
    if ($this->operation == 'add') {
      try {
        $form = $this->addForm($form, $form_state);
      }
      catch (\LogicException $e) {
        drupal_set_message($e->getMessage(), 'error');
        return array();
      }
    }

    return $form;
  }

  /**
   * Adds "add form" form elements.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form with added elements.
   */
  protected function addForm(array $form, FormStateInterface $form_state) {
    $request = $this->getRequest();
    $job_item_ids = $request->get('items');
    $source_language = $request->get('source');
    $target_languages = $request->get('targets');
    if (!$job_item_ids || !$source_language || !$target_languages) {
      throw new \InvalidArgumentException('One of the required arguments is missing.');
    }
    if (!$translator_id = $this->config('venga_translator.settings')->get('translator')) {
      throw new \LogicException('venga_translator.settings:translator config is not set.');
    }

    /* @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $this->entityTypeManager->getStorage('tmgmt_translator')->load($translator_id);
    if ($translator->getPluginId() != 'venga_translator') {
      throw new \LogicException('Translator should have venga_translator plugin.');
    }

    $job_items = $this->loadJobItems($job_item_ids);
    if (count($job_items ) != count($job_item_ids)) {
      throw new \LogicException('Can not load the referenced job items. The URL might be outdated.');
    }

    // Validate job items.
    foreach ($job_items as $job_item) {
      if ($job_item->getJobId()) {
        throw new \LogicException('Only unprocessed job items are allowed.');
      }
    }

    $job_titles = [];
    $job_suffix_parts = [];
    foreach ($job_items as $id => $job_item) {
      $job_suffix_parts[] = $job_item->getSourceLabel();
    }
    $job_suffix = implode(', ', $job_suffix_parts);

    // Loop over all target languages, create a job title for each source and
    // target language combination.
    foreach ($target_languages as $target_language) {
      // Skip in case the source language is the same as the target language.
      if ($source_language == $target_language) {
        continue;
      }
      $job_titles[] = $job_suffix . strtoupper(" ($source_language-$target_language)");
    }
    $form['jobs'] = [
      '#theme' => 'item_list',
      '#title' => t('Jobs preview'),
      '#items' => $job_titles,
    ];

    // Set default service value from translator settings.
    $form['service']['widget']['#default_value'] = VengaTranslatorUi::getDefaultService($translator, $job_items);

    // Set default contact person.
    $form['person']['widget']['#default_value'] = $translator->getSetting('person');
    $form['send_back_to']['widget']['#default_value'] = $translator->getSetting('send_back_to') ?: $translator->getSetting('person');
    if (count($form['additional_persons']['widget']['#options']) <= 1) {
      // Shown additional persons only when multiple contact persons exist.
      $form['additional_persons']['#access'] = FALSE;
    }

    // Display timezone next to the date field title.
    $timezone = drupal_get_user_timezone();
    $timezone = new \DateTimeZone($timezone);
    $date = new \DateTime("now", $timezone);
    $offset = $timezone->getOffset($date) / 3600;
    $offset_text = " (GMT" . ($offset < 0 ? $offset : "+".$offset) . ')';
    $form['complete_by']['widget'][0]['value']['#title'] .= $offset_text;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if ($form_state->getValue('person')) {
      $contact_person = $form_state->getValue('person')[0]['value'];
      foreach ($form_state->getValue('additional_persons') as $additional_person) {
        if ($additional_person['value'] == $contact_person) {
          $form_state->setErrorByName('additional_persons', 'You can not assign the contact person as an additional person.');
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\venga_translator\Entity\Quote */
    $entity = $this->entity;

    // Do not save complete by value if it is not set.
    if (!$form_state->getValue('complete_by')) {
      $entity->complete_by = NULL;
    }

    $translator_id = $this->config('venga_translator.settings')->get('translator');
    /* @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $this->entityTypeManager->getStorage('tmgmt_translator')->load($translator_id);

    if ($this->operation == 'add') {
      $request = $this->getRequest();
      $job_item_ids = $request->get('items');
      $source_language = $request->get('source');
      $target_languages = $request->get('targets');
      $job_values = [];
      $job_items = $this->loadJobItems($job_item_ids);
      $remove_job_item_ids = [];

      $entity->setTranslator($translator);
      $entity->setSourceLanguage($source_language);
      $target_language_values = [];
      foreach($target_languages as $target_language){
        $target_language_values[]['value'] = $target_language;
      }
      $entity->set('target_languages', $target_language_values);

      // Loop over all target languages, create a job for each source and target
      // language combination add add the relevant job items to it.
      foreach ($target_languages as $target_language) {
        // Skip in case the source language is the same as the target language.
        if ($source_language == $target_language) {
          continue;
        }

        /** @var \Drupal\tmgmt\JobInterface $job */
        $label = $entity->getName() . strtoupper(" ($source_language-$target_language)");
        $job = $this->entityTypeManager->getStorage('tmgmt_job')->create([
          'label' => $label,
          'source_language' => $source_language,
          'target_language' => $target_language,
          'uid' => $this->currentUser()->id(),
          'translator' => $translator,
          'settings' => [
            'project_name' => $label,
            'customer_project_number' => $entity->getCustomerProjectNumber(),
            'service' => $entity->getService(),
            'auto_accept' => $entity->getAutoAccept(),
            'person' => $entity->getPerson(),
            'send_back_to' => $entity->getSendBackTo(),
            'additional_persons' => $entity->getAdditionalPersons(),
            'specialization' => $entity->getSpecialization(),
            'complete_by' => $entity->getCompleteBy() ? date('Y-m-d H:i:s', $entity->getCompleteBy()) : NULL,
            'notes' => $entity->getNotes(),
          ],
        ]);

        foreach ($job_items as $job_item) {
          // As the same item might be added to multiple jobs, we need to
          // re-create them and delete the old ones, after removing them from
          // the cart.
          $job->addItem($job_item->getPlugin(), $job_item->getItemType(), $job_item->getItemId());
          $remove_job_item_ids[$job_item->id()] = $job_item->id();
        }

        $job_values[] = ['entity' => $job];
      }

      // Remove job items from the cart.
      if ($remove_job_item_ids) {
        tmgmt_cart_get()->removeJobItems($remove_job_item_ids);
        entity_delete_multiple('tmgmt_job_item', $remove_job_item_ids);
      }

      $entity->jobs = $job_values;
    }

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('%label quote was requested.', [
          '%label' => $entity->label(),
        ]));

        /* @var \Drupal\venga_translator\Plugin\tmgmt\Translator\VengaTranslator $venga_translator */
        $venga_translator = $translator->getPlugin();

        // Request quote.
        $venga_translator->requestQuoteTranslation($entity);
        break;

      default:
        drupal_set_message($this->t('Saved the %label Quote.', [
          '%label' => $entity->label(),
        ]));
    }

    $form_state->setRedirect('entity.venga_translator_quote.canonical', ['venga_translator_quote' => $entity->id()]);
  }

  /**
   * Loads multiple job item entities.
   *
   * @param $ids
   *   An array of job item entity IDs, or NULL to load all the entities.
   *
   * @return \Drupal\tmgmt\JobItemInterface[]
   *   An array of entity objects indexed by their IDs. Returns an empty array
   *   if no matching entities are found.
   */
  protected function loadJobItems($ids) {
    return $this->entityTypeManager->getStorage('tmgmt_job_item')->loadMultiple($ids);
  }

}
