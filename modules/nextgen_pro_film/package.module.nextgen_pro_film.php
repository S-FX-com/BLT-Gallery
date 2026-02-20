<?php
/**
 * @property C_MVC_Controller|C_Display_Type_Controller|Mixin_NextGen_Basic_Pagination $object
 */
class A_NextGen_Pro_Film_Controller extends Mixin
{
    /** @var array Allows index_action() and enqueue_frontend_resources() shared access to $displayed_gallery->get_included_entities() results */
    public static $cached_images = array();
    public function initialize()
    {
        $this->object->add_mixin('Mixin_NextGen_Basic_Pagination');
    }
    /**
     * Prepares a pro-film displayed gallery for rendering and caches the result.
     *
     * Because the dyncss controller needs to know the widest and tallest images in the gallery it must share the
     * results of $displayed_gallery->get_included_entities() with this controller's index_action(). To avoid repeating
     * possibly expensive database lookups this method caches the results. $this->enqueue_frontend_resources() should
     * run first but it is not guaranteed.
     *
     * @param C_Displayed_Gallery $displayed_gallery
     * @return array
     */
    public function prepare_display($displayed_gallery)
    {
        $id = $displayed_gallery->id();
        if (!empty(self::$cached_images[$id])) {
            return self::$cached_images[$id];
        }
        $display_settings = $displayed_gallery->display_settings;
        $current_page = (int) $this->object->param('nggpage', $displayed_gallery->id(), 1);
        if (!isset($display_settings['images_per_page'])) {
            $display_settings['images_per_page'] = C_NextGen_Settings::get_instance()->images_per_page;
        }
        $offset = $display_settings['images_per_page'] * ($current_page - 1);
        $total = $displayed_gallery->get_entity_count();
        $images = $displayed_gallery->get_included_entities($display_settings['images_per_page'], $offset);
        if (in_array($displayed_gallery->source, array('random', 'recent'))) {
            $display_settings['disable_pagination'] = TRUE;
        }
        if ($images) {
            if ($display_settings['images_per_page'] && !$display_settings['disable_pagination']) {
                $pagination_result = $this->object->create_pagination($current_page, $total, $display_settings['images_per_page']);
            }
        }
        $pagination = !empty($pagination_result['output']) ? $pagination_result['output'] : NULL;
        // Get named size of thumbnail images
        $thumbnail_size_name = 'thumbnail';
        if ($display_settings['override_thumbnail_settings']) {
            $dynthumbs = C_Dynamic_Thumbnails_Manager::get_instance();
            $dyn_params = array('width' => $display_settings['thumbnail_width'], 'height' => $display_settings['thumbnail_height']);
            if ($display_settings['thumbnail_quality']) {
                $dyn_params['quality'] = $display_settings['thumbnail_quality'];
            }
            if ($display_settings['thumbnail_crop']) {
                $dyn_params['crop'] = true;
            }
            if ($display_settings['thumbnail_watermark']) {
                $dyn_params['watermark'] = true;
            }
            $thumbnail_size_name = $dynthumbs->get_size_name($dyn_params);
        }
        // Calculate image statistics
        $stats = $this->object->get_entity_statistics($images, $thumbnail_size_name, TRUE);
        $images = $stats['entities'];
        $display_settings['longest'] = $stats['longest'];
        $display_settings['widest'] = $stats['widest'];
        self::$cached_images[$id] = ['display_settings' => $display_settings, 'effect_code' => $this->object->get_effect_code($displayed_gallery), 'id' => $id, 'images' => $images, 'pagination' => $pagination, 'storage' => C_Gallery_Storage::get_instance(), 'thumbnail_size_name' => $thumbnail_size_name];
        return self::$cached_images[$id];
    }
    /**
     * @param C_Displayed_Gallery $displayed_gallery
     * @param bool $return
     * @return string
     */
    public function index_action($displayed_gallery, $return = FALSE)
    {
        $params = $this->object->prepare_display_parameters($displayed_gallery, $this->prepare_display($displayed_gallery));
        // Render & remove spaces between HTML tags
        return preg_replace('~>\\s*\\n\\s*<~', '><', $this->object->render_partial('photocrati-nextgen_pro_film#nextgen_pro_film', $params, $return));
    }
    /**
     * @return false
     */
    function is_cachable()
    {
        return FALSE;
    }
    function enqueue_frontend_resources($displayed_gallery)
    {
        $this->call_parent('enqueue_frontend_resources', $displayed_gallery);
        wp_enqueue_style('nextgen_pagination_style', $this->object->get_static_url('photocrati-nextgen_pagination#style.css'));
        wp_enqueue_style('nextgen_pro_film', $this->object->get_static_url('photocrati-nextgen_pro_film#nextgen_pro_film.css'));
        $params = $this->object->prepare_display_parameters($displayed_gallery, $this->prepare_display($displayed_gallery));
        // Enqueue dynamic stylesheet
        $dyn_styles = C_Dynamic_Stylesheet_Controller::get_instance('all');
        $dyn_styles->register('nextgen_pro_film', 'photocrati-nextgen_pro_film#nextgen_pro_film_dyncss');
        $dyn_styles->enqueue('nextgen_pro_film', $this->array_merge_assoc($params['display_settings'], array('id' => $displayed_gallery->id())));
        $this->object->enqueue_ngg_styles();
    }
}
/**
 * @mixin C_Form
 * @property C_Form|Mixin_Display_Type_Form $object
 */
class A_NextGen_Pro_Film_Form extends Mixin_Display_Type_Form
{
    public function get_display_type_name()
    {
        return NGG_PRO_FILM;
    }
    public function enqueue_static_resources()
    {
        $atp = C_Attach_Controller::get_instance();
        $name = $this->object->get_display_type_name() . '-js';
        wp_enqueue_script($name, $this->object->get_static_url('photocrati-nextgen_pro_film#settings.js'));
        if ($atp != null && $atp->has_method('mark_script')) {
            $atp->mark_script($name);
        }
    }
    /**
     * @return array
     */
    public function _get_field_names()
    {
        return array('thumbnail_override_settings', 'nextgen_pro_film_images_per_page', 'nextgen_pro_film_image_spacing', 'nextgen_pro_film_border_size', 'nextgen_pro_film_frame_size', 'nextgen_pro_film_border_color', 'nextgen_pro_film_frame_color', 'nextgen_pro_film_alttext_display', 'nextgen_pro_film_alttext_font_color', 'nextgen_pro_film_alttext_font_size', 'nextgen_pro_film_description_display', 'nextgen_pro_film_description_font_color', 'nextgen_pro_film_description_font_size', 'display_type_view');
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_pro_film_alttext_display_field($display_type)
    {
        return $this->object->_render_radio_field($display_type, 'alttext_display', __('Display image title', 'nextgen-gallery-pro'), $display_type->settings['alttext_display']);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_pro_film_alttext_font_color_field($display_type)
    {
        return $this->object->_render_color_field($display_type, 'alttext_font_color', __('Title font color', 'nextgen-gallery-pro'), $display_type->settings['alttext_font_color'], __('An empty color setting will use your theme colors', 'nextgen-gallery-pro'), empty($display_type->settings['alttext_display']) ? TRUE : FALSE);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_pro_film_alttext_font_size_field($display_type)
    {
        return $this->object->_render_number_field($display_type, 'alttext_font_size', __('Title font size', 'nextgen-gallery-pro'), $display_type->settings['alttext_font_size'], __('Measured in pixels. An empty or zero setting will use your theme font size', 'nextgen-gallery-pro'), empty($display_type->settings['alttext_display']) ? TRUE : FALSE, __('# of pixels', 'nextgen-gallery-pro'), 0);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_pro_film_description_display_field($display_type)
    {
        return $this->object->_render_radio_field($display_type, 'description_display', __('Display image description', 'nextgen-gallery-pro'), $display_type->settings['description_display']);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_pro_film_description_font_color_field($display_type)
    {
        return $this->object->_render_color_field($display_type, 'description_font_color', __('Description font color', 'nextgen-gallery-pro'), $display_type->settings['description_font_color'], __('An empty color setting will use your theme colors', 'nextgen-gallery-pro'), empty($display_type->settings['description_display']) ? TRUE : FALSE);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_pro_film_description_font_size_field($display_type)
    {
        return $this->object->_render_number_field($display_type, 'description_font_size', __('Description font size', 'nextgen-gallery-pro'), $display_type->settings['description_font_size'], __('Measured in pixels. An empty or zero setting will use your theme font size', 'nextgen-gallery-pro'), empty($display_type->settings['description_display']) ? TRUE : FALSE, __('# of pixels', 'nextgen-gallery-pro'), 0);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_pro_film_images_per_page_field($display_type)
    {
        return $this->object->_render_number_field($display_type, 'images_per_page', __('Images per page', 'nextgen-gallery-pro'), $display_type->settings['images_per_page'], __('"0" will display all images at once', 'nextgen-gallery-pro'), FALSE, __('# of images', 'nextgen-gallery-pro'), 0);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_pro_film_border_size_field($display_type)
    {
        return $this->object->_render_number_field($display_type, 'border_size', __('Border size', 'nextgen-gallery-pro'), $display_type->settings['border_size'], '', FALSE, '', 0);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_pro_film_border_color_field($display_type)
    {
        return $this->object->_render_color_field($display_type, 'border_color', __('Border color', 'nextgen-gallery-pro'), $display_type->settings['border_color']);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_pro_film_frame_size_field($display_type)
    {
        return $this->object->_render_number_field($display_type, 'frame_size', __('Frame size', 'nextgen-gallery-pro'), $display_type->settings['frame_size'], '', FALSE, '', 0);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_pro_film_frame_color_field($display_type)
    {
        return $this->object->_render_color_field($display_type, 'frame_color', __('Frame color', 'nextgen-gallery-pro'), $display_type->settings['frame_color']);
    }
    /**
     * @param C_Display_Type $display_type
     * @return string
     */
    public function _render_nextgen_pro_film_image_spacing_field($display_type)
    {
        return $this->object->_render_number_field($display_type, 'image_spacing', __('Image spacing', 'nextgen-gallery-pro'), $display_type->settings['image_spacing'], '', FALSE, '', 0);
    }
}
/**
 * Class A_NextGen_Pro_Film_Mapper
 * @property C_Display_Type_Mapper $object
 */
class A_NextGen_Pro_Film_Mapper extends Mixin
{
    function set_defaults($entity)
    {
        $this->call_parent('set_defaults', $entity);
        if ($entity->name !== NGG_PRO_FILM) {
            return;
        }
        $settings = C_NextGen_Settings::get_instance();
        $this->object->_set_default_value($entity, 'settings', 'alttext_display', 0);
        $this->object->_set_default_value($entity, 'settings', 'alttext_font_color', '');
        $this->object->_set_default_value($entity, 'settings', 'alttext_font_size', '');
        $this->object->_set_default_value($entity, 'settings', 'border_color', '#CCCCCC');
        $this->object->_set_default_value($entity, 'settings', 'border_size', 1);
        $this->object->_set_default_value($entity, 'settings', 'description_display', 0);
        $this->object->_set_default_value($entity, 'settings', 'description_font_color', '');
        $this->object->_set_default_value($entity, 'settings', 'description_font_size', '');
        $this->object->_set_default_value($entity, 'settings', 'disable_pagination', 0);
        $this->object->_set_default_value($entity, 'settings', 'display_type_view', 'default');
        $this->object->_set_default_value($entity, 'settings', 'frame_color', '#FFFFFF');
        $this->object->_set_default_value($entity, 'settings', 'frame_size', 20);
        $this->object->_set_default_value($entity, 'settings', 'image_spacing', 5);
        $this->object->_set_default_value($entity, 'settings', 'images_per_page', $settings->get('galImages'));
        $this->object->_set_default_value($entity, 'settings', 'ngg_triggers_display', 'always');
        $this->object->_set_default_value($entity, 'settings', 'override_thumbnail_settings', 0);
        $this->object->_set_default_value($entity, 'settings', 'thumbnail_crop', 0);
        $this->object->_set_default_value($entity, 'settings', 'thumbnail_height', $settings->get('thumbheight'));
        $this->object->_set_default_value($entity, 'settings', 'thumbnail_quality', $settings->get('thumbquality'));
        $this->object->_set_default_value($entity, 'settings', 'thumbnail_watermark', 0);
        $this->object->_set_default_value($entity, 'settings', 'thumbnail_width', $settings->get('thumbwidth'));
    }
}