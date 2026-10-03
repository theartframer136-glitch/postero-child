(function ($) {
    'use strict';

    $(function () {
        // ready, maybe need to reload dates
        if (wpced_vars.reload_dates) {
            let ids = [];

            $('.wpced').each(function () {
                let id = $(this).attr('data-id');

                if (typeof id !== typeof undefined && id !== false) {
                    ids.push(id);
                }
            });

            if (ids.length > 0) {
                // request reloaded dates
                var data = {
                    action: 'wpced_reload_dates', ids: ids, nonce: wpced_vars.nonce,
                };

                $.post(wpced_vars.wc_ajax_url.toString().replace('%%endpoint%%', 'wpced_reload_dates'), data, function (response) {
                    if (!$.isEmptyObject(response)) {
                        $.each(response, function (key, value) {
                            $('.' + key).each(function () {
                                if (!$(this).closest('.order_item').length) {
                                    $(this).replaceWith(value);
                                }
                            });
                        });
                    }
                });
            }
        }
    });

    $(document).on('found_variation', function (e, t) {
        if (t.wpced_enable !== undefined) {
            var pid = $(e['target']).closest('.variations_form').data('product_id');

            if (t.wpced_enable === 'disable') {
                $('.wpced-' + pid).html('');
            } else if (t.wpced_enable === 'override' && t.wpced_date !== undefined && t.wpced_date !== '') {
                $('.wpced-' + pid).replaceWith(wpced_decode_entities(t.wpced_date));
            } else {
                if (t.wpced_date !== '') {
                    $('.wpced-' + pid).replaceWith(wpced_decode_entities(t.wpced_date));
                } else {
                    var variable_date = $('.wpced-variable-' + pid).data('wpced');

                    if (variable_date !== undefined) {
                        $('.wpced-' + pid).replaceWith(wpced_decode_entities(variable_date));
                    }
                }
            }

            $(document.body).trigger('wpced_found_variation', [t]);
        }
    });

    $(document).on('reset_data', function (e) {
        var pid = $(e['target']).closest('.variations_form').data('product_id');
        var variable_date = $('.wpced-variable-' + pid).data('wpced');

        if (variable_date !== undefined) {
            $('.wpced-' + pid).replaceWith(wpced_decode_entities(variable_date));
        }

        $(document.body).trigger('wpced_reset_data');
    });

    $(document).on('updated_shipping_method', function () {
        $(document.body).trigger('wc_update_cart');
    });
})(jQuery);

function wpced_decode_entities(encodedString) {
    var textArea = document.createElement('textarea');
    textArea.innerHTML = encodedString;

    return textArea.value;
}