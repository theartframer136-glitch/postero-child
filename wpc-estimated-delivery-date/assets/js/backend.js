'use strict';

(function ($) {
    var wpced_timer = 0;

    $(function () {
        // ready
        init_terms();
        init_sortable();
        init_zone();
        init_date();
        init_time();
        init_scheduled();
        init_date_format();
    });

    $(document).on('keyup change keypress', '.wpced-rule-name-input', function () {
        let $this = $(this), value = $this.val();

        if (value !== '') {
            $this.closest('.wpced-rule').find('.wpced-item-name-key').text(value);
        } else {
            $this.closest('.wpced-rule').find('.wpced-item-name-key').text($this.data('key'));
        }
    });

    $(document).on('change', '.wpced-date-format', function () {
        init_date_format();
    });

    $(document).on('keyup change', '.wpced-date-format-custom', function () {
        let value = $(this).val();

        if (value !== '') {
            if (wpced_timer != null) {
                clearTimeout(wpced_timer);
            }

            wpced_timer = setTimeout(date_format_preview, 300);
        } else {
            $('.wpced-date-format-preview').html('');
        }
    });

    $(document).on('woocommerce_variations_added woocommerce_variations_loaded',
        function () {
            init_scheduled();
        });

    $(document).on('change', '.wpced_apply, .wpced_apply_val', function () {
        init_apply_label($(this).closest('.wpced-item'));
    });

    $(document).on('change', '.wpced_apply', function () {
        init_terms();
    });

    $(document).on('change', '.wpced_apply_val', function () {
        var apply = $(this).closest('.wpced-item').find('.wpced_apply').val();

        $(this).data(apply, $(this).val().join());
    });

    $(document).on('change', '.wpced_zone', function () {
        init_zone();
    });

    $(document).on('change', '.wpced-date-type', function () {
        init_date();
    });

    $(document).on('change', '.wpced-select-enable', function () {
        init_single($(this));
    });

    $(document).on('click touch', '.wpced-item-header', function (e) {
        if (($(e.target).closest('.wpced-item-duplicate').length === 0) &&
            ($(e.target).closest('.wpced-item-remove').length === 0)) {
            $(this).closest('.wpced-item').toggleClass('active');
        }
    });

    $(document).on('click touch', '.wpced-item-remove', function () {
        var r = confirm(
            'Do you want to remove this role? This action cannot undo.');

        if (r == true) {
            $(this).closest('.wpced-item').remove();
        }
    });

    $(document).on('click touch', '.wpced-item-new', function () {
        let $this = $(this), product_id = $this.data('product_id'),
            is_variation = $this.data('is_variation'),
            $rules = $this.closest('.wpced-settings').find('.wpced-rules');

        $this.prop('disabled', true);
        $rules.addClass('wpced-items-loading');

        $.post(ajaxurl, {
            action: 'wpced_add_rule',
            product_id: product_id,
            is_variation: is_variation,
        }, function (response) {
            $rules.append(response);
            $this.prop('disabled', false);
            $rules.find('.wpced-item:last-child').addClass('active');
            $rules.removeClass('wpced-items-loading');
            init_terms();
            init_scheduled();
        });
    });

    $(document).on('click touch', '.wpced-item-duplicate', function () {
        let $this = $(this), product_id = $this.data('product_id'),
            is_variation = $this.data('is_variation'),
            $rules = $this.closest('.wpced-rules'),
            $rule = $this.closest('.wpced-rule'),
            rule_data = $rule.find('input, select, button, textarea').serialize() ||
                0;

        $rules.addClass('wpced-items-loading');

        $.post(ajaxurl, {
            action: 'wpced_add_rule',
            product_id: product_id,
            is_variation: is_variation,
            rule_data: rule_data,
        }, function (response) {
            $(response).addClass('active').insertAfter($rule);
            $rules.removeClass('wpced-items-loading');
            init_terms();
            init_scheduled();
        });
    });

    $(document).on('click touch', '.wpced-add-date-btn', function () {
        let $this = $(this);

        $this.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'wpced_add_date',
        }, function (response) {
            $('.wpced-skipped-dates').append(response);
            init_date();
            $this.prop('disabled', false);
        });
    });

    $(document).on('click touch', '.wpced-date-remove', function (e) {
        $(this).closest('.wpced-skipped-date').remove();
        e.preventDefault();
    });

    $(document).on('click touch', '.wpced-update-dates', function (e) {
        var order_id = $('#post_ID').val();

        var data = {
            action: 'wpced_get_order_dates',
            nonce: wpced_vars.nonce,
            order_id: order_id,
        };

        $('#wpced_update_dates_dialog').addClass('loading');
        $('#wpced_update_dates_dialog').dialog({
            minWidth: 460,
            modal: true,
            dialogClass: 'wpc-dialog',
            open: function () {
                $('.ui-widget-overlay').bind('click', function () {
                    $('#wpced_update_dates_dialog').dialog('close');
                });
            },
        });

        $.post(ajaxurl, data, function (response) {
            $('#wpced_update_dates_dialog').html(response);
            $('#wpced_update_dates_dialog').removeClass('loading');
        });

        e.preventDefault();
    });

    $(document).on('click touch', '.wpced-order-items-save-btn', function (e) {
        var order_id = $('.wpced-order-items').data('id');
        var order_dates = [];

        $('#wpced_update_dates_dialog').addClass('loading');

        $('.wpced-order-item-date-val').each(function () {
            var $this = $(this), id = $this.data('id'), date = $this.val();

            order_dates.push({id: id, date: date});
        });

        var data = {
            action: 'wpced_save_order_dates',
            nonce: wpced_vars.nonce,
            order_id: order_id,
            order_dates: order_dates,
        };

        $.post(ajaxurl, data, function (response) {
            $('#wpced_update_dates_dialog').dialog('close');
            $('#woocommerce-order-items').trigger('wc_order_items_reload');
        });

        e.preventDefault();
    });

    function init_sortable() {
        $('.wpced-rules').sortable({
            handle: '.wpced-item-move',
        });
    }

    function init_single($select) {
        let state = $select.val(), $single = $select.closest('.wpced-settings').find('.wpced-single-product');

        if (state === 'override') {
            $single.show();
        } else {
            $single.hide();
        }
    }

    function init_apply_label($item) {
        let apply = $item.find('.wpced_apply').val(),
            apply_val = $item.find('.wpced_apply_val').val().join(),
            apply_label = '';

        if (apply === 'all' || $item.hasClass('wpced-item-default')) {
            apply_label = 'all';
        } else {
            apply_label = apply + ': ' + apply_val;
        }

        $item.find('.wpced-item-name-apply').html(apply_label);
    }

    function init_zone() {
        $('.wpced_zone').each(function () {
            var $this = $(this);
            var zone = $this.val();
            var $methods = $this.closest('.wpced-item').find('.wpced_method');

            if (zone === '' || zone === 'none' || zone === 'all') {
                $methods.val('all').trigger('change').prop('disabled', true);
            } else {
                $methods.prop('disabled', false);
                $methods.find('option').attr('disabled', 'disabled');
                $methods.find('option[data-zone="' + zone + '"]').removeAttr('disabled');
                $methods.find('option[value="all"]').removeAttr('disabled');
                $methods.find('option[value="none"]').removeAttr('disabled');
            }
        });
    }

    function init_scheduled() {
        $('.wpced_scheduled:not(.wpced_dpk_init)').wpcdpk({clearButton: true}).addClass('wpced_dpk_init');
    }

    function init_date_format() {
        if ($('.wpced-date-format').val() === 'custom') {
            $('.wpced-date-format-custom').show();
            $('.wpced-date-format-preview').show();
        } else {
            $('.wpced-date-format-custom').hide();
            $('.wpced-date-format-preview').hide();
        }
    }

    function init_date() {
        $('.wpced-date-val:not(.wpced_dpk_init)').wpcdpk().addClass('wpced_dpk_init');

        $('.wpced-date-type').each(function () {
            if ($(this).val() === 'cus') {
                $(this).closest('.wpced-skipped-date').find('.wpced-date-val').show();
            } else {
                $(this).closest('.wpced-skipped-date').find('.wpced-date-val').hide();
            }
        });
    }

    function init_time() {
        $('.wpced-time-val:not(.wpced_dpk_init)').wpcdpk({
            timepicker: true, onlyTimepicker: true, clearButton: true,
        }).addClass('wpced_dpk_init');
    }

    function init_terms() {
        $('.wpced_terms').each(function () {
            var $this = $(this);
            var apply = $this.closest('.wpced-item').find('.wpced_apply').val();

            if (apply === 'all' || apply === 'instock' || apply === 'outofstock' ||
                apply === 'backorder') {
                $this.closest('.wpced-item').find('.hide_if_apply_all').hide();
            } else if (apply === 'stock') {
                $this.closest('.wpced-item').find('.hide_if_apply_all').hide();
                $this.closest('.wpced-item').find('.show_if_apply_stock').show();
            } else {
                $this.closest('.wpced-item').find('.hide_if_apply_all').hide();
                $this.closest('.wpced-item').find('.show_if_apply_terms').show();
            }

            $this.selectWoo({
                ajax: {
                    url: ajaxurl, dataType: 'json', delay: 250, data: function (params) {
                        return {
                            q: params.term, action: 'wpced_search_term', taxonomy: apply,
                        };
                    }, processResults: function (data) {
                        var options = [];

                        if (data) {
                            $.each(data, function (index, text) {
                                options.push({id: text[0], text: text[1]});
                            });
                        }
                        return {
                            results: options,
                        };
                    }, cache: true,
                }, minimumInputLength: 1,
            });

            if ($this.data(apply) !== undefined && $this.data(apply) !== '') {
                $this.val(String($this.data(apply)).split(',')).change();
            } else {
                $this.val([]).change();
            }
        });
    }

    function date_format_preview() {
        $('.wpced-date-format-preview').html('...');
        wpced_timer = null;

        $.post(ajaxurl, {
            action: 'wpced_date_format_preview',
            date_format: $('.wpced-date-format-custom').val(),
        }, function (response) {
            $('.wpced-date-format-preview').html(response);
        });
    }
})(jQuery);
