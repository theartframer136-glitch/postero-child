(function( $ ) {
	'use strict';
  $(function () {

    $(document).on('change', 'select#switch_lang_select', function () {
      var new_lang = $(this).find(':selected').data('target');
      window.location.assign(new_lang);
    });

  });

})( jQuery );
