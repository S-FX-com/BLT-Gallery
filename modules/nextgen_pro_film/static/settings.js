jQuery(function($) {
    $('input[name="photocrati-nextgen_pro_film[override_thumbnail_settings]"]')
        .nextgen_radio_toggle_tr('1', $('#tr_photocrati-nextgen_pro_film_thumbnail_dimensions'))
        .nextgen_radio_toggle_tr('1', $('#tr_photocrati-nextgen_pro_film_thumbnail_crop'));

    $('input[name="photocrati-nextgen_pro_film[alttext_display]"]')
        .nextgen_radio_toggle_tr('1', $('#tr_photocrati-nextgen_pro_film_alttext_font_size'))
        .nextgen_radio_toggle_tr('1', $('#tr_photocrati-nextgen_pro_film_alttext_font_color'));

    $('input[name="photocrati-nextgen_pro_film[description_display]"]')
        .nextgen_radio_toggle_tr('1', $('#tr_photocrati-nextgen_pro_film_description_font_size'))
        .nextgen_radio_toggle_tr('1', $('#tr_photocrati-nextgen_pro_film_description_font_color'));
});
