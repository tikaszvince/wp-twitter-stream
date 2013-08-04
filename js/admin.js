(function ($) {
  "use strict";
  $(function () {
    $('body').on('change', '.wp-twitter-stream-filter-mode', function(){
      var hashtags = $(this).parents('form:first').find('.wp-twitter-stream-hashtag-list');
      if ($(this).val() == 0) {
        hashtags.hide();
      }
      else {
        hashtags.show();
      }
    });
  });
}(jQuery));