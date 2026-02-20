<?php
/**
 * Provides rendering logic
 * @class A_NextGen_Pro_Masonry_Controller
 * @mixin C_Display_Type_Controller
 * @adapts I_Display_Type_Controller using "photocrati-nextgen_pro_masonry" context
 */
class A_NextGen_Pro_Masonry_Controller extends Mixin
{
    /**
     * Renders the front-end display for the masonry display type
     *
     * @param C_Displayed_Gallery $displayed_gallery
     * @param bool $return
     * @return string
     */
    function index_action($displayed_gallery, $return = FALSE)
    {
        $images = $displayed_gallery->get_included_entities();
        // This display type was never meant to work with NextGen's trigger icons but the setting was set to 'always' as
        // a default value in the pro-masonry-mapper. Prevent users from getting an improperly rendered gallery trigger:
        $displayed_gallery->display_settings['ngg_triggers_display'] = 'never';
        if (!$images) {
            return $this->object->render_partial("photocrati-nextgen_gallery_display#no_images_found", array(), $return);
        } else {
            $params = $displayed_gallery->display_settings;
            $params['images'] = $images;
            $params['storage'] = C_Gallery_Storage::get_instance();
            $params['effect_code'] = $this->object->get_effect_code($displayed_gallery);
            $params['displayed_gallery_id'] = $displayed_gallery->id();
            $params['thumbnail_size_name'] = C_Dynamic_Thumbnails_Manager::get_instance()->get_size_name(array('width' => $params['size'], 'crop' => FALSE));
            $params = $this->object->prepare_display_parameters($displayed_gallery, $params);
            return $this->object->render_view('photocrati-nextgen_pro_masonry#index', $params, $return);
        }
    }
    /**
     * Enqueues all static resources required by this display type
     *
     * @param C_Displayed_Gallery $displayed_gallery
     */
    function enqueue_frontend_resources($displayed_gallery)
    {
        global $wp_version;
        wp_enqueue_style('nextgen_pro_masonry_style', $this->get_static_url('photocrati-nextgen_pro_masonry#style.css'));
        // WordPress 5.3 still only has Masonry version 3.3.2. To prevent conflicts we use the WordPress provided
        // masonry JS *if* it is already enqueued by something else, but we prefer to use our own at version 4.2.2
        if (wp_script_is('jquery-masonry', 'enqueued')) {
            wp_enqueue_script('nextgen_pro_masonry_script', $this->get_static_url('photocrati-nextgen_pro_masonry#nextgen_pro_masonry_compat.js'), array('jquery-masonry', 'ngg_waitforimages'));
            wp_localize_script('nextgen_pro_masonry_script', 'nextgen_pro_masonry_settings', array('columnWidth' => $displayed_gallery->display_settings['size'], 'gutterWidth' => $displayed_gallery->display_settings['padding']));
        } else {
            M_Gallery_Display::enqueue_fontawesome();
            // WordPress' masonry isn't being used so use our own
            wp_enqueue_script('nextgen_pro_masonry_masonry_script', $this->get_static_url('photocrati-nextgen_pro_masonry#masonry.min.js'), array('jquery', 'ngg_waitforimages'));
            wp_enqueue_script('nextgen_pro_masonry_script', $this->get_static_url('photocrati-nextgen_pro_masonry#nextgen_pro_masonry.js'), array('nextgen_pro_masonry_masonry_script'));
            wp_localize_script('nextgen_pro_masonry_masonry_script', 'nextgen_pro_masonry_settings', array('center_gallery' => $displayed_gallery->display_settings['center_gallery']));
        }
        $this->call_parent('enqueue_frontend_resources', $displayed_gallery);
        $this->enqueue_ngg_styles();
    }
}
/**
 * Class A_NextGen_Pro_Masonry_Form
 * @mixin C_Form
 * @adapts I_Form using "photocrati-nextgen_pro_masonry" context
 */
class A_NextGen_Pro_Masonry_Form extends Mixin_Display_Type_Form
{
    function get_display_type_name()
    {
        return NGG_PRO_MASONRY;
    }
    /**
     * Returns a list of fields to render on the settings page
     */
    function _get_field_names()
    {
        return array('nextgen_pro_masonry_size', 'nextgen_pro_masonry_padding', 'nextgen_pro_masonry_center_gallery', 'display_type_view');
    }
    function _render_nextgen_pro_masonry_size_field($display_type)
    {
        return $this->object->_render_number_field($display_type, 'size', __('Maximum image width', 'nextgen-gallery-pro'), $display_type->settings['size'], __('Measured in pixels', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_masonry_padding_field($display_type)
    {
        return $this->object->_render_number_field($display_type, 'padding', __('Image padding', 'nextgen-gallery-pro'), $display_type->settings['padding'], __('Measured in pixels', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_masonry_center_gallery_field($display_type)
    {
        return $this->object->_render_radio_field($display_type, 'center_gallery', __('Center the gallery', 'nextgen-gallery-pro'), $display_type->settings['center_gallery']);
    }
}
/**
 * Class A_NextGen_Pro_Masonry_Mapper
 * @mixin C_Display_Type_Mapper
 * @adapts I_Display_Type_Mapper
 */
class A_NextGen_Pro_Masonry_Mapper extends Mixin
{
    function set_defaults($entity)
    {
        $this->call_parent('set_defaults', $entity);
        if ($entity->name == NGG_PRO_MASONRY) {
            $this->object->_set_default_value($entity, 'settings', 'size', 180);
            $this->object->_set_default_value($entity, 'settings', 'padding', 10);
            $this->object->_set_default_value($entity, 'settings', 'center_gallery', 0);
            $this->_set_default_value($entity, 'settings', 'display_type_view', 'default');
        }
    }
}