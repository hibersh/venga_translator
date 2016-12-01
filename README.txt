Venga translator module
-----------------------

Integrates the Venga translation service module with the TMGMT module.
https://drupal.org/project/tmgmt

Installation
------------

First make sure your PHP version is 5.5 or newer.

Via composer(recommended):
 * To install via composer you should execute next command:
   composer require drupal/venga_translator

Manual installation:
 * For a manual installation you should copy the whole module directory to your
   modules directory(e.g. DRUPAL_ROOT/modules) and activate it "admin/modules".
 * You should also include the https://github.com/drunomics/xtrf-rest-client/
   library into the project.

Configuration
-------------

 * The module provides a new translation source plugin, go to
   "admin/tmgmt/translators" to configure a Venga translator.

Documentation
-------------

The Venga translator module provides the following options:

* url - REST URL you received from Venga.

* username - The username of your Venga API Credentials.

* password - The password of your Venga API Credentials.

* project_service - The service used for Venga projects. You can also use per
  entity settings. If so this setting will not be used.
  Go to 'admin/config/regional/entity_translation' to select service options
  for entity types and bundles.

* project_currency - The currency used for your Venga projects.

When you create a translation job through the tmgmt module, there will be more
options on a per job basis:

* project_name - Enter a project name.

* customer_project_number - Enter a project custom number.

* specialization - Enter a project specialization.

* service - Choose a Venga project service.

* complete_by - Enter a Deadline for the project.

* notes - Enter notes for the project. (optional)
