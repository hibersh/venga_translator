XTRF translator module
-----------------------

Integrates the XTRF translation service module with the TMGMT module.
https://drupal.org/project/tmgmt

Dependencies
------------

 * PHP must provide the ZipArchive class (PHP 5 >= 5.2.0, PECL zip >= 1.1.0)
   (http://www.php.net/manual/de/class.ziparchive.php)

Installation
------------

 * Copy the whole module directory to your modules directory
   (e.g. DRUPAL_ROOT/sites/all/modules) and activate it "admin/modules".
 * The module provides a new translation source plugin, go to
   "admin/config/regional/tmgmt_translator" to configure a XTRF translator.
 * If the entity translation module is used as translation source, per entity
   type and bundle workflow options can be configured at
   "admin/config/regional/entity_translation".
 * Make sure your PHP version is 5.0.1 or newer. (PHP 5 >= 5.0.1)

Documentation
-------------

The XTRF translator module provides the following options:

* wsdlUrl - This wsdl file will be used for the SOAP client. If you want to
  use a local wsdl file use local-MODULE. The file has to be named "MODULE.wsdl"
  and needs to be in the root directory of MODULE. Where MODULE is the name of
  the module which provides the wsdl file.

* username - The username of your XTRF API Credentials.

* password - The password of your XTRF API Credentials.

* project_workflow_options - A list of XTRF project workflow options available
  to your account, one per line.

* project_workflow - The Workflow used for XTRF projects. You can also use per
  entity settings. If so this setting will not be used.
  Go to 'admin/config/regional/entity_translation' to select workflow options
  for entity types and bundles.

* project_currency - The currency used for your XTRF projects.

When you create a translation job through the tmgmt module, there will be more
options on a per job basis:

* project_name - Enter a project name.

* complete_by - Enter a Deadline for the project.

* notes - Enter notes for the project. (optional)

