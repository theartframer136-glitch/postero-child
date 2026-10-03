(function($) {
  'use strict';

  $(function() {
    wpcsm_location_init();
    wpcsm_term_init();
    wpcsm_dpk_init();
    wpcsm_design_init();
    wpcsm_color_init();
  });

  $(document).on('click touch', '.wpcsm-shortcode-input', function() {
    $(this).select();
  });

  if ($('.wpcsm-shortcode-input').length) {
    $(document).on('keyup change', '#title', function() {
      var _title = $(this).
          val().
          replace(/&/g, '&amp;').
          replace(/>/g, '&gt;').
          replace(/</g, '&lt;').
          replace(/"/g, '&quot;');
      var _id = $('.wpcsm-shortcode-input').attr('data-id');

      $('.wpcsm-shortcode-input').
          val('[wpc_smart_message id="' + _id + '" name="' + _title + '"]');
    });
  }

  $(document).on('change', '#wpcsm_location', function() {
    let value = $(this).val();

    if (value === 'custom') {
      $('.wpcsm-custom-location').removeClass('hidden');
    } else {
      $('.wpcsm-custom-location').addClass('hidden');
    }
  });

  $(document).on('change', '.wpcsm_design', function() {
    wpcsm_design_init();
  });

  $(document).on('change', '.wpcsm-condition-type-select', function(e) {
    let $this = $(this), type = $this.val(),
        group = $this.find(':selected').data('group'),
        $panel = $this.closest('.input-panel'),
        $value = $panel.find('.input-value'), key = $panel.data('key');

    $.ajax({
      url: ajaxurl, method: 'POST', data: {
        action: 'wpcsm_get_condition_value',
        group: group,
        type: type,
        index: key,
        nonce: wpcsm_vars.nonce,
      }, dataType: 'html', beforeSend: function() {
        $value.html('<div class="spinner is-active"></div>');
      }, complete: function() {
        $this.prop('disabled', false);
      }, success: function(response) {
        $value.html(response);
        wpcsm_term_init();
        wpcsm_dpk_init();
      },
    });
  });

  $(document).on('click touch', '.wpcsm-remove-condition', function(e) {
    let $this = $(this);
    $this.closest('.input-panel').remove();
  });

  $(document).on('click touch', '.wpcsm-add-condition', function(e) {
    let $this = $(this), index = $this.prev('.wpcsm-conditions').
        find('.input-panel').
        last().
        data('key') || 0;

    $.ajax({
      url: ajaxurl, method: 'POST', data: {
        action: 'wpcsm_add_condition',
        index: index + 1,
        nonce: wpcsm_vars.nonce,
      }, dataType: 'html', beforeSend: function() {
        $this.prop('disabled', true);
      }, complete: function() {
        $this.prop('disabled', false);
      }, success: function(response) {
        $this.prev('.wpcsm-conditions').append(response);
      },
    });
  });

  $(document).on('click touch', '.wpcsm-activate-btn', function(e) {
    e.preventDefault();

    let $this = $(this), id = $this.data('id'),
        act = $this.hasClass('deactivate') ? 'deactivate' : 'activate';

    $.ajax({
      url: ajaxurl, method: 'POST', data: {
        action: 'wpcsm_activate', id: id, act: act, nonce: wpcsm_vars.nonce,
      }, dataType: 'html', beforeSend: function() {
        $this.addClass('updating');
      }, complete: function() {
        $this.removeClass('updating');
      }, success: function(response) {
        if (response === 'activate') {
          $this.removeClass('activate').addClass('deactivate button-primary');
        } else if (response === 'deactivate') {
          $this.removeClass('deactivate button-primary').addClass('activate');
        }
      },
    });
  });

  function wpcsm_location_init() {
    $('#wpcsm_location').
        selectWoo({
          containerCssClass: 'wpc-select2',
          dropdownCssClass: 'wpc-select2-dropdown',
        });
  }

  function wpcsm_term_init() {
    $('.wpcsm_term_selector').each(function() {
      var $this = $(this);
      var taxonomy = $this.data('taxonomy');

      $this.selectWoo({
        containerCssClass: 'wpc-select2',
        dropdownCssClass: 'wpc-select2-dropdown',
        ajax: {
          url: ajaxurl, dataType: 'json', delay: 250, data: function(params) {
            return {
              q: params.term,
              action: 'wpcsm_search_term',
              taxonomy: taxonomy,
              nonce: wpcsm_vars.nonce,
            };
          }, processResults: function(data) {
            var options = [];
            if (data) {
              $.each(data, function(index, text) {
                options.push({id: text[0], text: text[1]});
              });
            }
            return {
              results: options,
            };
          }, cache: true,
        },
        minimumInputLength: 1,
      });
    });
  }

  function wpcsm_dpk_init() {
    $('.wpcsm_date_time:not(.wpcsm_dpk_init)').wpcdpk({
      timepicker: true,
    }).addClass('wpcsm_dpk_init');

    $('.wpcsm_date:not(.wpcsm_dpk_init)').wpcdpk().addClass('wpcsm_dpk_init');

    $('.wpcsm_date_range:not(.wpcsm_dpk_init)').wpcdpk({
      range: true, multipleDatesSeparator: ' - ',
    }).addClass('wpcsm_dpk_init');

    $('.wpcsm_date_multi:not(.wpcsm_dpk_init)').wpcdpk({
      multipleDates: 5, multipleDatesSeparator: ', ',
    }).addClass('wpcsm_dpk_init');

    $('.wpcsm_time:not(.wpcsm_dpk_init)').wpcdpk({
      timepicker: true, onlyTimepicker: true, classes: 'only-time',
    }).addClass('wpcsm_dpk_init');

    $('.wpcsm_multiple').selectWoo();
  }

  function wpcsm_design_init() {
    var design = $('.wpcsm_design').val();

    if (design === 'no') {
      $('.wpcsm-design-box-row:not(.wpcsm-design-box-row-design)').
          hide();
    } else if (design === 'custom_css') {
      $('.wpcsm-design-box-row:not(.wpcsm-design-box-row-design)').
          hide();
      $('.wpcsm-design-box-row-custom-css').show();
    } else {
      $('.wpcsm-design-box-row').show();
      $('.wpcsm-design-box-row-custom-css').hide();
    }
  }

  function wpcsm_color_init() {
    $('.wpcsm_color_input').wpColorPicker();
  }
})(jQuery);
