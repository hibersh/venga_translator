(function ($) {
  Drupal.behaviors.vengaTranslatorRequestButton = {
    attach: function (context, settings) {
      // Refreshes "Request project/quote" button content on page load.
      refreshRequestButton();
      // Toggle "Request project/quote" button title.
      $(context).find('#edit-auto-accept-value').once('venga_translator').change(function () {
        refreshRequestButton();
      });

      /**
       * Refreshes "Request project/quote" button content.
       */
      function refreshRequestButton() {
        var $input = $(context).find('#edit-auto-accept-value');
        var $button = $(context).find('#edit-submit');
        if ($input.is(':checked')) {
          $button.val(settings.venga_translator.project_title);
        }
        else {
          $button.val(settings.venga_translator.quote_title);
        }
      }
    }
  };
})(jQuery);
