(function ($) {
  Drupal.behaviors.vengaTranslatorCartTweaks = {
    attach: function (context, settings) {
      if (settings.venga_translator == undefined || settings.venga_translator.cart_languages_map == undefined) {
        return;
      }
      var map = settings.venga_translator.cart_languages_map;
      var $cart = $('#edit-items', context).find('tbody');
      var $targetLanguages = $('#edit-target-language', context);

      // Refreshes cart items on page load.
      refreshCartItems();

      // Toggle enabled cart items.
      $cart.find('input:checkbox').once('venga_translator').change(function (e) {
        refreshCartItems();
      });

      /**
       * Refreshes enabled items in the cart.
       */
      function refreshCartItems() {
        var $activeCheckbox = $cart.find('input:checkbox:checked:first');
        if ($activeCheckbox.length == 0) {
          // Enable all rows if there is no item selected.
          $cart.find('input:checkbox').each(function() {
            var $input = $(this);
            var $row = $input.closest('tr');
            $input.attr('disabled', false);
            $row.removeClass('is-disabled');
            $targetLanguages.find('option').show();
          });
          return;
        }

        var language = null;
        var activeItemId = $activeCheckbox.val();
        // Find the language value from the mapping by item ID.
        $.each(map, function(languageKey, itemIds) {
          if (itemIds[activeItemId] != undefined) {
            language = languageKey;
            return false;
          }
        });

        // Hide the source language in the select list of languages for
        // translation.
        $targetLanguages.find('option').show();
        $targetLanguages.find('option:contains("' + language + '")').hide();

        // Find items that have the current source language.
        var languageItems = map[language];

        // Loop cart items, enable only items with the same source language as
        // the active item.
        $cart.find('input:checkbox').each(function() {
          var $input = $(this);
          var itemId = $input.val();
          var $row = $input.closest('tr');

          // Check if language matches the language of the checked item.
          if (languageItems[itemId] != undefined) {
            // Enable cart item row.
            $input.attr('disabled', false);
            $row.removeClass('is-disabled');
          }
          else {
            // Disable cart item row.
            $input.attr('disabled', true);
            $row.addClass('is-disabled');
          }
        });
      }
    }
  };
})(jQuery);
