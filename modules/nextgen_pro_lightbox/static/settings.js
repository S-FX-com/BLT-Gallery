jQuery(function($) {
    $('#photocrati-nextgen_pro_lightbox_router_slug').prop('required', true);

    // These two <select> fields have length <option> entries which are difficult to style with plain HTML elements
    $('#photocrati-nextgen_pro_lightbox_image_crop, #photocrati-nextgen_pro_lightbox_style, #photocrati-nextgen_pro_lightbox_transition_effect').select2({
        width: '350px',
        minimumResultsForSearch: -1 // hides the search box
    });

    // All of the social settings are dependent on enable_routing being set to on
    // Further twitter_username needs both enable_twitter_cards and enable_sharing to be on
    $('input[name="photocrati-nextgen_pro_lightbox[enable_routing]"]').on('change', function() {
        if ($(this).val() == 0) {
            $('#tr_photocrati-nextgen_pro_lightbox_social, ' +
              '#tr_photocrati-nextgen_pro_lightbox_enable_comments, ' +
              '#tr_photocrati-nextgen_pro_lightbox_display_comments, ' +
              '#tr_photocrati-nextgen_pro_lightbox_enable_sharing, ' +
              '#tr_photocrati-nextgen_pro_lightbox_facebook_app_id, ' +
              '#tr_photocrati-nextgen_pro_lightbox_enable_twitter_cards, ' +
              '#tr_photocrati-nextgen_pro_lightbox_twitter_username').hide();
        } else {
            $('#tr_photocrati-nextgen_pro_lightbox_social, ' +
              '#tr_photocrati-nextgen_pro_lightbox_enable_comments, ' +
              '#tr_photocrati-nextgen_pro_lightbox_enable_sharing').show();

            if ($('input[name="photocrati-nextgen_pro_lightbox[enable_sharing]"]:checked').val() == '1') {
                $('#tr_photocrati-nextgen_pro_lightbox_enable_twitter_cards').show();
                $('#tr_photocrati-nextgen_pro_lightbox_facebook_app_id').show();
            }

            if ($('input[name="photocrati-nextgen_pro_lightbox[enable_comments]"]:checked').val() == '1') {
                console.log("I can't even");
                $('#tr_photocrati-nextgen_pro_lightbox_display_comments').show();
            }

            if ($('input[name="photocrati-nextgen_pro_lightbox[enable_twitter_cards]"]:checked').val() == '1'
            &&  $('input[name="photocrati-nextgen_pro_lightbox[enable_sharing]"]:checked').val() == '1') {
                console.log("stop");
                $('#tr_photocrati-nextgen_pro_lightbox_twitter_username').show();
            }
        }
    });

    // Again we must treat the fields dependent on this and its dependents as well
    $('input[name="photocrati-nextgen_pro_lightbox[enable_sharing]"]')
        .nextgen_radio_toggle_tr('1', $('#tr_photocrati-nextgen_pro_lightbox_facebook_app_id'))
        .on('change', function() {
            if ($(this).val() == 1) {
                $('#tr_photocrati-nextgen_pro_lightbox_enable_twitter_cards').show();
                if ($('input[name="photocrati-nextgen_pro_lightbox[enable_twitter_cards]"]:checked').val() == '1') {
                    $('#tr_photocrati-nextgen_pro_lightbox_twitter_username').show();
                }
            } else {
                $('#tr_photocrati-nextgen_pro_lightbox_enable_twitter_cards').hide();
                $('#tr_photocrati-nextgen_pro_lightbox_twitter_username').hide();
            }
        });

    // Now back to your regularly scheduled if/then hide/show
    $('input[name="photocrati-nextgen_pro_lightbox[enable_comments]"]')
        .nextgen_radio_toggle_tr('1', $('#tr_photocrati-nextgen_pro_lightbox_display_comments'));
    $('input[name="photocrati-nextgen_pro_lightbox[enable_twitter_cards]"]')
        .nextgen_radio_toggle_tr('1', $('#tr_photocrati-nextgen_pro_lightbox_twitter_username'));
    $('input[name="photocrati-nextgen_pro_lightbox[icon_background_enabled]"]')
        .nextgen_radio_toggle_tr('1', $('#tr_photocrati-nextgen_pro_lightbox_icon_background'))
        .nextgen_radio_toggle_tr('1', $('#tr_photocrati-nextgen_pro_lightbox_icon_background_rounded'));

    // wpColorPicker doesn't make it easy to clear the value so we just trigger a click on the 'clear' button
    var clear_wp_color = function(elements) {
        $.each(elements, function(index, $el) {
            $el.parent().siblings('input.wp-picker-clear').first().trigger('click');
            $el.siblings('input.wp-picker-clear').first().trigger('click');
        });
    };

    // Prevent admins from setting so high a padding that it breaks the ability for Galleria to instantiate
    var $padding  = $('#photocrati-nextgen_pro_lightbox_padding');
    var $pad_unit = $('#photocrati-nextgen_pro_lightbox_padding_unit');
    var adjust_padding = function() {
        var padding = $padding.val();
        var unit = $pad_unit.val();
        if (unit === '%' && padding > 25) {
            $padding.val('25');
        }
    };
    $padding.on('change keyup',  function() { adjust_padding(); });
    $pad_unit.on('change', function() { adjust_padding(); });

    $('select[name="photocrati-nextgen_pro_lightbox[style]"]').on('change', function() {
        var $this = $(this);

        clear_wp_color([$('input[name="photocrati-nextgen_pro_lightbox[carousel_text_color]"]'),
                        $('input[name="photocrati-nextgen_pro_lightbox[carousel_background_color]"]'),
                        $('input[name="photocrati-nextgen_pro_lightbox[sidebar_background_color]"]'),
                        $('input[name="photocrati-nextgen_pro_lightbox[icon_background]"]'),
                        $('input[name="photocrati-nextgen_pro_lightbox[overlay_icon_color]"]'),
                        $('input[name="photocrati-nextgen_pro_lightbox[sidebar_button_color]"]'),
                        $('input[name="photocrati-nextgen_pro_lightbox[sidebar_button_background]"]')]);

        // TODO: make these dynamicly provided
        if ($this.val() == 'white.css' || $this.val() == 'white') {
            $('input[name="photocrati-nextgen_pro_lightbox[icon_color]"]').wpColorPicker('color', '#ffffff');
            $('input[name="photocrati-nextgen_pro_lightbox[icon_background]"]').wpColorPicker('color', '#444444');
            $('input[name="photocrati-nextgen_pro_lightbox[carousel_text_color]"]').wpColorPicker('color', '#7a7a7a');
            $('input[name="photocrati-nextgen_pro_lightbox[background_color]"]').wpColorPicker('color', '#ffffff');
        } else {
            $('input[name="photocrati-nextgen_pro_lightbox[icon_color]"]').wpColorPicker('color', '#dddddd');
            $('input[name="photocrati-nextgen_pro_lightbox[icon_background]"]').wpColorPicker('color', '#333333');
            clear_wp_color([$('input[name="photocrati-nextgen_pro_lightbox[carousel_text_color]"]'),
                            $('input[name="photocrati-nextgen_pro_lightbox[background_color]"]')]);
        }
    });
});
