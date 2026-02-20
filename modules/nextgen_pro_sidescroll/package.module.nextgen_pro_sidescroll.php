<?php
class A_NextGen_Pro_Sidescroll_Controller extends Mixin
{
    function enqueue_frontend_resources($displayed_gallery)
    {
        $this->call_parent('enqueue_frontend_resources', $displayed_gallery);
        wp_enqueue_script('jquery-easing', $this->object->get_static_url(NGG_PRO_SIDESCROLL . '#jquery.easing.1.4.1.js'), array('jquery'), '1.4.1', TRUE);
        wp_enqueue_script('jquery-scrollto', $this->object->get_static_url(NGG_PRO_SIDESCROLL . '#jquery.scrollTo.js'), array('jquery'), '2.1.2', TRUE);
        wp_enqueue_script('jquery-touchswipe', $this->object->get_static_url(NGG_PRO_SIDESCROLL . '#jquery.touchSwipe.js'), array('jquery'), '1.6.18', TRUE);
        wp_enqueue_script('spin', $this->object->get_static_url(NGG_PRO_SIDESCROLL . '#spin.js'), array('jquery'), '2.3.2', TRUE);
        /* Enqueue sidescroll.js (a fork of portfolio.js) */
        wp_enqueue_script('sidescroll', $this->object->get_static_url(NGG_PRO_SIDESCROLL . '#sidescroll.js'), array('jquery', 'jquery-easing', 'nextgen_pro_captions_imagesloaded', 'jquery-scrollto', 'jquery-touchswipe', 'spin'), '1.0', TRUE);
        /* Finally enqueue the JS to initialize portfolio.js and localize some vars */
        wp_enqueue_script('ngg_pro_sidescroll_js', $this->object->get_static_url(NGG_PRO_SIDESCROLL . '#nextgen_pro_sidescroll.js'), array('jquery', 'underscore', 'sidescroll', 'nextgen_pro_captions_imagesloaded'), defined('NGG_PRO_PLUGIN_VERSION') ? NGG_PRO_PLUGIN_VERSION : NGG_PLUS_PLUGIN_VERSION, TRUE);
        /* Enqueue the associated stylesheet */
        wp_enqueue_style('nextgen_pro_sidescroll', $this->get_static_url('photocrati-nextgen_pro_sidescroll#nextgen_pro_sidescroll.css'));
        $this->enqueue_ngg_styles();
    }
    function index_action($displayed_gallery, $return = FALSE)
    {
        $id = $displayed_gallery->id();
        $display_settings = $displayed_gallery->display_settings;
        $total = $displayed_gallery->get_entity_count();
        $images = $displayed_gallery->get_included_entities();
        // Get named size of thumbnail images
        $thumbnail_size_name = 'full';
        $params = array('images' => $images, 'storage' => C_Gallery_Storage::get_instance(), 'thumbnail_size_name' => $thumbnail_size_name, 'effect_code' => $this->object->get_effect_code($displayed_gallery), 'id' => $id);
        $params = $this->object->prepare_display_parameters($displayed_gallery, $params);
        // Render view/template
        return preg_replace('~>\\s*\\n\\s*<~', '><', $this->render_partial('photocrati-nextgen_pro_sidescroll#nextgen_pro_sidescroll', $params, $return));
    }
}
class A_NextGen_Pro_Sidescroll_Form extends Mixin_Display_Type_Form
{
    function get_display_type_name()
    {
        return NGG_PRO_SIDESCROLL;
    }
    /**
     * Returns a list of fields to render on the settings page
     */
    function _get_field_names()
    {
        return array('nextgen_pro_sidescroll_height', 'display_type_view');
    }
    /**
     * Renders the images_per_page settings field
     *
     * @param C_Display_Type $display_type
     * @return string
     */
    function _render_nextgen_pro_sidescroll_height_field($display_type)
    {
        return $this->_render_number_field($display_type, 'height', __('Gallery Height', 'nextgen-gallery-pro'), $display_type->settings['height'], __('Provide desired gallery height in pixels.', 'nextgen-gallery-pro'), FALSE, '', 0);
    }
}
class A_NextGen_Pro_Sidescroll_Mapper extends Mixin
{
    function set_defaults($entity)
    {
        $this->call_parent('set_defaults', $entity);
        if ($entity->name == NGG_PRO_SIDESCROLL) {
            $this->_set_default_value($entity, 'settings', 'height', 400);
            $this->_set_default_value($entity, 'settings', 'display_type_view', 'default');
        }
    }
}