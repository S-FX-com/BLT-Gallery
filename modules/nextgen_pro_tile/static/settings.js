jQuery(function($) {
    $('input[name="photocrati-nextgen_pro_tile[override_maximum_width]"]')
        .nextgen_radio_toggle_tr('1', $('#tr_photocrati-nextgen_pro_tile_maximum_width'));

    $('input[name="photocrati-nextgen_pro_tile[captions_enabled]"]')
        .nextgen_radio_toggle_tr('1', $('#tr_photocrati-nextgen_pro_tile_captions_animation'))
        .nextgen_radio_toggle_tr('1', $('#tr_photocrati-nextgen_pro_tile_captions_display_sharing'))
        .nextgen_radio_toggle_tr('1', $('#tr_photocrati-nextgen_pro_tile_captions_display_title'))
        .nextgen_radio_toggle_tr('1', $('#tr_photocrati-nextgen_pro_tile_captions_display_description'));
});
