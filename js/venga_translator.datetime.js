(function ($) {
  Drupal.behaviors.vengaTranslatorDatetime = {
    attach: function (context, settings) {
      // Put in a default time to date-time widget.
      $(context).find('.form-type-date').find('input[type=date]').once('venga_translator').change(function () {
        var $date = $(this);
        var $time = $date.parent().siblings('.form-type-date').find('input[type=time]');
        if ($time.length == 0) {
          return true;
        }
        if ($date.val()) {
          if (!$time.val()) {
            $time.val('17:00:00');
          }
        }
        else {
          $time.val('');
        }
      });
    }
  };
})(jQuery);
