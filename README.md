Venga Gateway Connect for Drupal
--------------------------------

Venga Gateway Connect for Drupal is Venga’s integration solution for a fast, 
efficient, and error free website translation process. Venga Gateway Connect
seamlessly integrates with your Drupal Installation to eliminate copy & paste
and error prone email transfers. This integration gives your team full access
to Venga’s suite of resources including our network of qualified linguists and
subject matter experts, knowledge management tools and assets, and our Venga
Gateway Client  Portal, customized to solve your unique project and budget
tracking needs.

Installation
------------

First make sure your PHP version is 5.5 or newer.

It is recommended to install the module via Composer, which will download the
required libraries.

1. Add the Drupal Packagist repository:

    ```sh
    composer config repositories.drupal composer https://packages.drupal.org/8
    ```
This allows Composer to find the module as well as other Drupal modules.

2. Download the module:

   ```sh
   composer require "drupal/venga_translator ~1.0"
   ```
This will download the latest release of Venga Gateway Connect.
Use 1.x-dev instead of ~1.0 to get the -dev release instead.

See [Using Composer in a Drupal project](https://www.drupal.org/node/2404989) for more information.

It is also possible to install the module manually:
1. Copy the whole module directory to the modules directory (e.g. DRUPAL_ROOT/modules) and activate it "admin/modules".
1. You should also include the https://github.com/drunomics/xtrf-rest-client/
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

Credits
-------

Development:
- Wolfgang Ziegler https://www.drupal.org/u/fago
- Alex Milkovskyi https://www.drupal.org/u/amilkovsky

Initial development:
- drunomics https://drunomics.com/en
