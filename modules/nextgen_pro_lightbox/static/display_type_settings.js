jQuery(function($) {
    // TODO: This file contains many SoC violations that should be resolved for debugging purposes and code integrity

    // Reposition each of the 'open gallery in Pro Lightbox' rows and place them above their respective
    // "Display galleries as" setting so that the latter may be hidden when the former is enabled
    var $row_one   = $('#tr_photocrati-nextgen_basic_compact_album_open_gallery_in_lightbox');
    var $row_two   = $('#tr_photocrati-nextgen_basic_extended_album_open_gallery_in_lightbox');
    var $row_three = $('#tr_photocrati-nextgen_pro_list_album_open_gallery_in_lightbox');
    var $row_four  = $('#tr_photocrati-nextgen_pro_grid_album_open_gallery_in_lightbox');

    $row_one.detach();
    $row_two.detach();
    $row_three.detach();
    $row_four.detach();

    var $child_one   = $('#photocrati-nextgen_basic_compact_album_gallery_display_type').parents('tr').first();
    var $child_two   = $('#photocrati-nextgen_basic_extended_album_gallery_display_type').parents('tr').first();
    var $child_three = $('#tr_photocrati-nextgen_pro_list_album_gallery_display_type');
    var $child_four  = $('#tr_photocrati-nextgen_pro_grid_album_gallery_display_type');

    $row_one.insertBefore($child_one);
    $row_two.insertBefore($child_two);
    $row_three.insertBefore($child_three);
    $row_four.insertBefore($child_four);

    var $field_one   = $('input[name="photocrati-nextgen_basic_compact_album[open_gallery_in_lightbox]"]');
    var $field_two   = $('input[name="photocrati-nextgen_basic_extended_album[open_gallery_in_lightbox]"]');
    var $field_three = $('input[name="photocrati-nextgen_pro_list_album[open_gallery_in_lightbox]"]');
    var $field_four  = $('input[name="photocrati-nextgen_pro_grid_album[open_gallery_in_lightbox]"]');

    // Because it's way easier to do this in JS than overriding the form adapters for NextGen (not Pro) albums
    if ($('input[name="photocrati-nextgen_basic_compact_album[open_gallery_in_lightbox]"]:checked').val() == '1')
        $child_one.addClass('hidden');
    if ($('input[name="photocrati-nextgen_basic_extended_album[open_gallery_in_lightbox]"]:checked').val() == '1')
        $child_two.addClass('hidden');
    if ($('input[name="photocrati-nextgen_pro_list_album[open_gallery_in_lightbox]"]:checked').val() == '1')
        $child_three.addClass('hidden');
    if ($('input[name="photocrati-nextgen_pro_grid_album[open_gallery_in_lightbox]"]:checked').val() == '1')
        $child_four.addClass('hidden');

    // Finally add the toggle to hide the child rows
    $field_one.nextgen_radio_toggle_tr('0', $child_one);
    $field_two.nextgen_radio_toggle_tr('0', $child_two);
    $field_three.nextgen_radio_toggle_tr('0', $child_three);
    $field_four.nextgen_radio_toggle_tr('0', $child_four);
});