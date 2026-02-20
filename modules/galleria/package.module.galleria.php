<?php
/**
 * Class A_Galleria_Controller
 * @mixin C_Display_Type_Controller
 * @adapts I_Display_Type_Controller using "photocrati-galleria" context
 */
class A_Galleria_Controller extends Mixin
{
    function enqueue_frontend_resources($displayed_gallery)
    {
        // Add some properties to the displayed gallery
        $this->object->_compute_aspect_ratio($displayed_gallery);
        // Include ngg_common.js
        $this->call_parent('enqueue_frontend_resources', $displayed_gallery);
        M_Galleria::enqueue_entities($displayed_gallery);
        wp_enqueue_script('ngg_galleria');
        wp_register_script('ngg_intersection_observer_polyfill', $this->get_static_url(NGG_PRO_GALLERIA . '#intersection-observer.js'), array(), '20181023');
        wp_enqueue_script('ngg_galleria_init', $this->get_static_url(NGG_PRO_GALLERIA . '#ngg_galleria.js'), array('ngg_galleria', 'ngg_intersection_observer_polyfill'), '1.0');
        $this->enqueue_ngg_styles();
    }
    function _compute_aspect_ratio($displayed_gallery, $type = null)
    {
        $storage = C_Gallery_Storage::get_instance();
        $list = $displayed_gallery->get_included_entities();
        if ($type == null) {
            $type = !empty($displayed_gallery->display_settings['aspect_ratio']) ? $displayed_gallery->display_settings['aspect_ratio'] : 'image_average';
        }
        switch ($type) {
            case 'first_image':
                if ($list != null) {
                    $image = $list[0];
                    $dims = $storage->get_image_dimensions($image);
                    $ratio = round($dims['width'] / $dims['height'], 2);
                    $displayed_gallery->display_settings['aspect_ratio_computed'] = $ratio;
                }
                break;
            case 'image_average':
                if ($list != null) {
                    $ratio_sum = 0;
                    $image_count = 0;
                    foreach ($list as $image) {
                        $dims = $storage->get_image_dimensions($image);
                        if ($dims) {
                            $ratio = round($dims['width'] / $dims['height'], 2);
                            $ratio_sum += $ratio;
                            $image_count++;
                        }
                    }
                    $computed_ratio = round($ratio_sum / $image_count, 2);
                    if ($computed_ratio > 0) {
                        $displayed_gallery->display_settings['aspect_ratio_computed'] = $computed_ratio;
                    }
                }
                break;
        }
    }
    function index_action($displayed_gallery, $return = FALSE)
    {
        // Yes this is a weird / bad place to put this, but it's just not worth the time to correct existing mistakes just to make this one line cleaner
        $displayed_gallery->display_settings['theme'] = $displayed_gallery->display_type === 'photocrati-nextgen_pro_slideshow' ? 'nextgen_pro_slideshow' : 'nextgen_pro_horizontal_filmstrip';
        $params = array(
            'theme' => $displayed_gallery->display_settings['theme'],
            'displayed_gallery_id' => $displayed_gallery->id(),
            // do not remove: this is not used in the template but without it ecommerce triggers will not display
            'images' => $displayed_gallery->get_entities(),
            'effect_code' => $this->object->get_effect_code($displayed_gallery),
            'storage' => C_Gallery_Storage::get_instance(),
        );
        $params = $this->object->prepare_display_parameters($displayed_gallery, $params);
        return $this->object->render_partial(NGG_PRO_GALLERIA . '#galleria', $params, $return);
    }
}