<?php
/**
 * Class A_NextGen_Pro_Lightbox_Ajax
 * @mixin C_Ajax_Controller
 * @adapts I_Ajax_Controller
 */
class A_NextGen_Pro_Lightbox_Ajax extends Mixin
{
    /**
     * Provides a Galleria-formatted JSON array of get_included_entities() results
     */
    function pro_lightbox_load_images_action()
    {
        $retval = array();
        if ($id = $this->param('id')) {
            $displayed_gallery_mapper = C_Displayed_Gallery_Mapper::get_instance();
            if ($this->param('lang', NULL, FALSE)) {
                if (class_exists('SitePress')) {
                    global $sitepress;
                    $sitepress->switch_lang($this->param('lang'));
                }
            }
            // Fetch ATP galleries or build our displayed gallery by parameters
            if (is_numeric($id)) {
                $displayed_gallery = $displayed_gallery_mapper->find($id, TRUE);
            } else {
                $factory = C_Component_Factory::get_instance();
                $displayed_gallery = $factory->create('displayed_gallery', $this->param('gallery'), $displayed_gallery_mapper);
            }
            if ($displayed_gallery) {
                $settings = C_NextGen_Settings::get_instance()->get('ngg_pro_lightbox');
                // we already have the first 'page' worth localized in the page HTML
                $page = $this->object->param('page', NULL, 1);
                if ($displayed_gallery->is_album_gallery && $page == 0) {
                    $page = 1;
                }
                $offset = $settings['localize_limit'] * ($page === 1 ? $page : $page - 1);
                $retval = M_Galleria::format_entities($displayed_gallery->get_entities(
                    $settings['localize_limit'],
                    // limit
                    $offset
                ));
            }
        }
        return apply_filters('ngg_pro_lightbox_images_queue', $retval);
    }
    function pro_lightbox_get_sidebar_data_action()
    {
        return apply_filters('ngg_pro_lightbox_sidebar_data_request', array());
    }
}
/**
 * Class A_NextGen_Pro_Lightbox_Album_Form
 * @mixin C_Form
 * @adapts I_Form using all display type contexts
 */
class A_NextGen_Pro_Lightbox_Album_Form extends Mixin
{
    function _get_field_names()
    {
        $ret = $this->call_parent('_get_field_names');
        if (($lightbox = C_Lightbox_Library_Manager::get_instance()->get_selected()) && $lightbox->name == NGG_PRO_LIGHTBOX) {
            $ret[] = 'nextgen_pro_lightbox_open_gallery_in_lightbox';
        }
        return $ret;
    }
    function enqueue_static_resources()
    {
        $this->call_parent('enqueue_static_resources');
        wp_enqueue_script('nextgen_pro_lightbox_display_type_settings_js', $this->object->get_static_url('photocrati-nextgen_pro_lightbox#display_type_settings.js'), array(), NGG_PRO_LIGHTBOX_VERSION, TRUE);
    }
    function _render_nextgen_pro_lightbox_open_gallery_in_lightbox_field($display_type)
    {
        return $this->_render_radio_field($display_type, 'open_gallery_in_lightbox', __('Open gallery in Pro Lightbox', 'nextgen-gallery-pro'), $display_type->settings['open_gallery_in_lightbox'], __('Feature only available when the Pro Lightbox has been selected as the lightbox effect in Other Options', 'nextgen-gallery-pro'));
    }
}
/**
 * Class A_NextGen_Pro_Lightbox_Effect_Code
 * @mixin C_Display_Type_Controller
 * @adapts I_Display_Type_Controller
 */
class A_NextGen_Pro_Lightbox_Effect_Code extends Mixin
{
    static $galleries_displayed = array();
    function get_effect_code($displayed_gallery)
    {
        // Swap the gallery placeholder
        $retval = $this->call_parent('get_effect_code', $displayed_gallery);
        $retval = str_replace('%PRO_LIGHTBOX_GALLERY_ID%', $displayed_gallery->id(), $retval);
        $lightbox = C_Lightbox_Library_Manager::get_instance()->get(NGG_PRO_LIGHTBOX);
        if ($lightbox && $lightbox->values['nplModalSettings']['enable_comments'] && $lightbox->values['nplModalSettings']['display_comments']) {
            $retval .= ' data-nplmodal-show-comments="1"';
        }
        return $retval;
    }
    function enqueue_frontend_resources($displayed_gallery)
    {
        $this->call_parent('enqueue_frontend_resources', $displayed_gallery);
        if (!in_array($displayed_gallery->id(), self::$galleries_displayed) && C_NextGen_Settings::get_instance()->thumbEffect == NGG_PRO_LIGHTBOX) {
            // prevent calling get_included_entities() more than once
            self::$galleries_displayed[] = $displayed_gallery->id();
            foreach (M_NextGen_Pro_Lightbox::get_components() as $name => $handler) {
                $handler = new $handler();
                $handler->name = $name;
                $handler->displayed_gallery = $displayed_gallery;
                $handler->enqueue_static_resources();
            }
            $settings = C_NextGen_Settings::get_instance()->get('ngg_pro_lightbox');
            $limit = $settings['localize_limit'];
            if (isset($displayed_gallery->display_settings['localize_limit'])) {
                $limit = $displayed_gallery->display_settings['localize_limit'];
            }
            M_Galleria::enqueue_entities($displayed_gallery, $limit);
        }
    }
}
/**
 * @mixin C_Form
 * @property C_Form|Mixin_Display_Type_Form|A_NextGen_Pro_Lightbox_Form $object
 */
class A_NextGen_Pro_Lightbox_Form extends Mixin
{
    function get_model()
    {
        $model = C_Lightbox_Library_Manager::get_instance()->get(NGG_PRO_LIGHTBOX);
        return $model;
    }
    function enqueue_static_resources()
    {
        wp_enqueue_script('photocrati-nextgen_pro_lightbox_settings-js', $this->get_static_url('photocrati-nextgen_pro_lightbox#settings.js'), array('jquery.nextgen_radio_toggle'));
        wp_enqueue_style('photocrati-nextgen_pro_lightbox_settings-css', $this->get_static_url('photocrati-nextgen_pro_lightbox#settings.css'));
    }
    /**
     * Returns a list of fields to render on the settings page
     */
    function _get_field_names()
    {
        return array(
            'nextgen_pro_lightbox_color_options_header',
            'nextgen_pro_lightbox_style',
            'nextgen_pro_lightbox_background_color',
            'nextgen_pro_lightbox_sidebar_background_color',
            'nextgen_pro_lightbox_sidebar_button_color',
            'nextgen_pro_lightbox_sidebar_button_background',
            'nextgen_pro_lightbox_carousel_background_color',
            'nextgen_pro_lightbox_carousel_text_color',
            'nextgen_pro_lightbox_overlay_icon_color',
            'nextgen_pro_lightbox_icon_color',
            'nextgen_pro_lightbox_icon_background_enabled',
            'nextgen_pro_lightbox_icon_background_rounded',
            'nextgen_pro_lightbox_icon_background',
            'nextgen_pro_lightbox_lightbox_and_image_sizing_header',
            'nextgen_pro_lightbox_padding',
            'nextgen_pro_lightbox_image_crop',
            'nextgen_pro_lightbox_image_pan',
            'nextgen_pro_lightbox_social_header',
            'nextgen_pro_lightbox_enable_comments',
            'nextgen_pro_lightbox_display_comments',
            'nextgen_pro_lightbox_enable_sharing',
            'nextgen_pro_lightbox_facebook_app_id',
            'nextgen_pro_lightbox_enable_twitter_cards',
            'nextgen_pro_lightbox_twitter_username',
            'nextgen_pro_lightbox_thumbnail_carousel_header',
            'nextgen_pro_lightbox_display_carousel',
            'nextgen_pro_lightbox_display_captions',
            'nextgen_pro_lightbox_thumbnail_carousel_enabled',
            // TODO: Add header "Ecommerce"
            'nextgen_pro_lightbox_transition_effects_header',
            'nextgen_pro_lightbox_transition_effect',
            'nextgen_pro_lightbox_transition_speed',
            'nextgen_pro_lightbox_slideshow_speed',
            'nextgen_pro_lightbox_interaction_pause',
            'nextgen_pro_lightbox_other_settings_header',
            'nextgen_pro_lightbox_enable_routing',
            'nextgen_pro_lightbox_router_slug',
            'nextgen_pro_lightbox_localize_limit',
        );
    }
    function _render_nextgen_pro_lightbox_color_options_header_field($lightbox)
    {
        return $this->object->_render_header_field($lightbox, 'color_options', __('Color options', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_lightbox_and_image_sizing_header_field($lightbox)
    {
        return $this->object->_render_header_field($lightbox, 'lightbox_and_image_sizing', __('Lightbox and image sizing', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_social_header_field($lightbox)
    {
        return $this->object->_render_header_field($lightbox, 'social', __('Social', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_thumbnail_carousel_header_field($lightbox)
    {
        return $this->object->_render_header_field($lightbox, 'thumbnail_carousel', __('Thumbnail carousel', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_transition_effects_header_field($lightbox)
    {
        return $this->object->_render_header_field($lightbox, 'transition_effects', __('Transition effects', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_other_settings_header_field($lightbox)
    {
        return $this->object->_render_header_field($lightbox, 'other', __('Other settings', 'nextgen-gallery-pro'));
    }
    /**
     * Renders the 'slug' setting field
     *
     * @param $lightbox
     * @return mixed
     */
    function _render_nextgen_pro_lightbox_router_slug_field($lightbox)
    {
        return $this->object->_render_text_field($lightbox, 'router_slug', __('Router slug', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['router_slug'], __('Used to route JS actions to the URL', 'nextgen-gallery-pro'));
    }
    /**
     * Renders padding unit & width fields; a custom template is used over the NGG form
     * methods so that both fields can be inside the same tr/td pair.
     *
     * @param object $lightbox Lightbox class
     * @return string $html
     */
    function _render_nextgen_pro_lightbox_padding_field($lightbox)
    {
        return $this->object->render_partial('photocrati-nextgen_pro_lightbox#admin_field_padding_and_unit', array('id' => $lightbox->name, 'padding_name' => 'padding', 'padding_label' => __('Lightbox padding', 'nextgen-gallery-pro'), 'padding_value' => $lightbox->values['nplModalSettings']['padding'], 'padding_text' => __('Padding is limited to no more than 25% of the browser screen size at startup', 'nextgen-gallery-pro'), 'padding_unit_name' => 'padding_unit', 'padding_unit_value' => $lightbox->values['nplModalSettings']['padding_unit'], 'padding_unit_options' => array('px' => 'px', '%' => '%')), TRUE);
    }
    /**
     * Renders the lightbox 'icon color' setting field
     *
     * @param $lightbox
     * @return mixed
     */
    function _render_nextgen_pro_lightbox_icon_color_field($lightbox)
    {
        return $this->object->_render_color_field($lightbox, 'icon_color', __('Icon color', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['icon_color'], __('An empty setting here will use your style defaults', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_icon_background_field($lightbox)
    {
        return $this->object->_render_color_field($lightbox, 'icon_background', __('Icon background', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['icon_background'], __('An empty setting here will use your style defaults', 'nextgen-gallery-pro'), empty($lightbox->values['nplModalSettings']['icon_background_enabled']) ? TRUE : FALSE);
    }
    function _render_nextgen_pro_lightbox_icon_background_enabled_field($lightbox)
    {
        return $this->object->_render_radio_field($lightbox, 'icon_background_enabled', __('Display background on carousel icons', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['icon_background_enabled']);
    }
    function _render_nextgen_pro_lightbox_icon_background_rounded_field($lightbox)
    {
        return $this->object->_render_radio_field($lightbox, 'icon_background_rounded', __('Display rounded background on carousel icons', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['icon_background_rounded'], '', empty($lightbox->values['nplModalSettings']['icon_background_enabled']) ? TRUE : FALSE);
    }
    function _render_nextgen_pro_lightbox_overlay_icon_color_field($lightbox)
    {
        return $this->object->_render_color_field($lightbox, 'overlay_icon_color', __('Floating elements color', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['overlay_icon_color'], __('An empty setting here will use your style defaults', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_sidebar_button_color_field($lightbox)
    {
        return $this->object->_render_color_field($lightbox, 'sidebar_button_color', __('Sidebar button text color', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['sidebar_button_color'], __('An empty setting here will use your style defaults', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_sidebar_button_background_field($lightbox)
    {
        return $this->object->_render_color_field($lightbox, 'sidebar_button_background', __('Sidebar button background', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['sidebar_button_background'], __('An empty setting here will use your style defaults', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_carousel_text_color_field($lightbox)
    {
        return $this->object->_render_color_field($lightbox, 'carousel_text_color', __('Carousel text color', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['carousel_text_color'], __('An empty setting here will use your style defaults', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_background_color_field($lightbox)
    {
        return $this->object->_render_color_field($lightbox, 'background_color', __('Background color', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['background_color'], __('An empty setting here will use your style defaults', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_carousel_background_color_field($lightbox)
    {
        return $this->object->_render_color_field($lightbox, 'carousel_background_color', __('Carousel background color', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['carousel_background_color'], __('An empty setting here will use your style defaults', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_sidebar_background_color_field($lightbox)
    {
        return $this->object->_render_color_field($lightbox, 'sidebar_background_color', __('Sidebar background color', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['sidebar_background_color'], __('An empty setting here will use your style defaults', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_image_pan_field($lightbox)
    {
        return $this->object->_render_radio_field($lightbox, 'image_pan', __('Pan cropped images', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['image_pan'], __('When enabled images can be panned with the mouse', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_interaction_pause_field($lightbox)
    {
        return $this->object->_render_radio_field($lightbox, 'interaction_pause', __('Pause on interaction', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['interaction_pause'], __('When enabled image display will be paused if the user presses a thumbnail or any navigational link', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_enable_routing_field($lightbox)
    {
        return $this->object->_render_radio_field($lightbox, 'enable_routing', __('Enable browser routing', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['enable_routing'], __('Necessary for commenting to be enabled', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_facebook_app_id_field($lightbox)
    {
        return $this->object->_render_text_field($lightbox, 'facebook_app_id', __('Facebook App ID', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['facebook_app_id'], __('When available this enables a more advanced sharing backend', 'nextgen-gallery-pro'), empty($lightbox->values['nplModalSettings']['enable_sharing']) ? TRUE : FALSE);
    }
    function _render_nextgen_pro_lightbox_enable_sharing_field($lightbox)
    {
        return $this->object->_render_radio_field($lightbox, 'enable_sharing', __('Enable sharing', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['enable_sharing'], __('When enabled social-media sharing icons will be displayed', 'nextgen-gallery-pro'), empty($lightbox->values['nplModalSettings']['enable_routing']) ? TRUE : FALSE);
    }
    function _render_nextgen_pro_lightbox_enable_comments_field($lightbox)
    {
        return $this->object->_render_radio_field($lightbox, 'enable_comments', __('Enable comments', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['enable_comments'], '', empty($lightbox->values['nplModalSettings']['enable_routing']) ? TRUE : FALSE);
    }
    function _render_nextgen_pro_lightbox_display_comments_field($lightbox)
    {
        return $this->object->_render_radio_field($lightbox, 'display_comments', __('Display comments initially', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['display_comments'], __('When on the commenting sidebar will be opened at startup', 'nextgen-gallery-pro'), empty($lightbox->values['nplModalSettings']['enable_comments']) ? TRUE : FALSE);
    }
    function _render_nextgen_pro_lightbox_display_captions_field($lightbox)
    {
        return $this->object->_render_radio_field($lightbox, 'display_captions', __('Display captions initially', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['display_captions'], __('When on the captions toolbar will be opened at startup', 'nextgen-gallery-pro'));
    }
    public function _render_nextgen_pro_lightbox_thumbnail_carousel_enabled_field($lightbox)
    {
        return $this->object->_render_select_field($lightbox, 'enable_carousel', __('Display carousel thumbnails', 'nextgen-gallery-pro'), array('always' => 'Always display thumbnails', 'never' => 'Never display thumbnails', 'nomobile' => 'Only on desktop browsers'), $lightbox->values['nplModalSettings']['enable_carousel'], __('Very large galleries can impact Pro Lightbox performance on mobile devices; disable carousel thumbnails to reduce that impact', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_display_carousel_field($lightbox)
    {
        return $this->object->_render_radio_field($lightbox, 'display_carousel', __('Display carousel initially', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['display_carousel'], __('When disabled the navigation carousel will be docked and hidden offscreen at startup', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_enable_twitter_cards_field($lightbox)
    {
        return $this->object->_render_radio_field($lightbox, 'enable_twitter_cards', __('Enable Twitter Cards', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['enable_twitter_cards'], empty($lightbox->values['nplModalSettings']['enable_sharing']) ? TRUE : FALSE);
    }
    function _render_nextgen_pro_lightbox_twitter_username_field($lightbox)
    {
        return $this->object->_render_text_field($lightbox, 'twitter_username', __('Twitter username', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['twitter_username'], __('Required by twitter for Twitter Card analytics', 'nextgen-gallery-pro'), empty($lightbox->values['nplModalSettings']['enable_twitter_cards']) ? TRUE : FALSE);
    }
    function _render_nextgen_pro_lightbox_localize_limit_field($lightbox)
    {
        return $this->object->_render_number_field($lightbox, 'localize_limit', __('Localize limit', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['localize_limit'], __('For performance gallery images are localized as javascript. Galleries with more images this limit will make an AJAX call to load the rest at startup. Set to 0 to include every image in displayed galleries.', 'nextgen-gallery-pro'), FALSE, '#', 0);
    }
    function _render_nextgen_pro_lightbox_transition_speed_field($lightbox)
    {
        return $this->object->_render_number_field($lightbox, 'transition_speed', __('Transition speed', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['transition_speed'], __('Measured in seconds', 'nextgen-gallery-pro'), FALSE, __('seconds', 'nextgen-gallery-pro'), 0);
    }
    function _render_nextgen_pro_lightbox_slideshow_speed_field($lightbox)
    {
        return $this->object->_render_number_field($lightbox, 'slideshow_speed', __('Slideshow speed', 'nextgen-gallery-pro'), $lightbox->values['nplModalSettings']['slideshow_speed'], __('Measured in seconds', 'nextgen-gallery-pro'), FALSE, __('seconds', 'nextgen-gallery-pro'), 0);
    }
    function _render_nextgen_pro_lightbox_style_field($lightbox)
    {
        $available_styles = array('dark' => __('Default: a dark theme', 'nextgen-gallery-pro'), 'black' => __('All black: Removes borders from the comments panel', 'nextgen-gallery-pro'), 'white' => __('All white: A white based theme', 'nextgen-gallery-pro'));
        $lightbox->values['nplModalSettings']['style'] = str_replace('.css', '', $lightbox->values['nplModalSettings']['style']);
        return $this->object->_render_select_field($lightbox, 'style', __('Color theme', 'nextgen-gallery-pro'), $available_styles, $lightbox->values['nplModalSettings']['style'], __('Preset styles to customize the display. Selecting an option may reset some color fields.', 'nextgen-gallery-pro'));
    }
    function get_effect_options()
    {
        return array('fade' => __('Crossfade betweens images', 'nextgen-gallery-pro'), 'flash' => __('Fades into background color between images', 'nextgen-gallery-pro'), 'pulse' => __('Quickly removes the image into background color, then fades the next image', 'nextgen-gallery-pro'), 'slide' => __('Slides the images depending on image position', 'nextgen-gallery-pro'), 'fadeslide' => __('Fade between images and slide slightly at the same time', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_transition_effect_field($lightbox)
    {
        return $this->object->_render_select_field($lightbox, 'transition_effect', __('Transition effect', 'nextgen-gallery-pro'), $this->get_effect_options(), $lightbox->values['nplModalSettings']['transition_effect']);
    }
    function _render_nextgen_pro_lightbox_touch_transition_effect_field($lightbox)
    {
        return $this->object->_render_select_field($lightbox, 'touch_transition_effect', __('Touch transition effect', 'nextgen-gallery-pro'), $this->get_effect_options(), $lightbox->values['nplModalSettings']['touch_transition_effect'], __('The transition to use on touch devices if the default transition is too intense', 'nextgen-gallery-pro'));
    }
    function _render_nextgen_pro_lightbox_image_crop_field($lightbox)
    {
        return $this->object->_render_select_field($lightbox, 'image_crop', __('Crop image display', 'nextgen-gallery-pro'), array('true' => __('Images will be scaled to fill the display, centered and cropped', 'nextgen-gallery-pro'), 'false' => __('Images will be scaled down until the entire image fits', 'nextgen-gallery-pro'), 'height' => __('Images will scale to fill the height of the display', 'nextgen-gallery-pro'), 'width' => __('Images will scale to fill the width of the display', 'nextgen-gallery-pro'), 'landscape' => __('Landscape images will fill the display, but scale portraits to fit', 'nextgen-gallery-pro'), 'portrait' => __('Portrait images will fill the display, but scale landscapes to fit', 'nextgen-gallery-pro')), $lightbox->values['nplModalSettings']['image_crop']);
    }
    function save_action()
    {
        // TODO: Add validation
        if ($updates = $this->object->param(NGG_PRO_LIGHTBOX)) {
            $settings = C_NextGen_Settings::get_instance();
            $settings->set('ngg_pro_lightbox', $updates);
            $settings->save();
            // There's some sort of caching going on that requires all lightboxes
            // to be flushed after we make changes to the Pro Lightbox
            C_Lightbox_Library_Manager::get_instance()->deregister_all();
        }
    }
    function _render_header_field($display_type, $name, $label, $hidden = FALSE)
    {
        return $this->object->render_partial(NGG_PRO_LIGHTBOX . '#admin_field_header', array('display_type_name' => $display_type->name, 'name' => $name, 'label' => $label, 'hidden' => $hidden), True);
    }
}
/**
 * Class A_NextGen_Pro_Lightbox_Triggers_Form
 * @mixin C_Form
 * @adapts I_Form for all display type contexts
 */
class A_NextGen_Pro_Lightbox_Triggers_Form extends Mixin
{
    function _get_field_names()
    {
        $ret = $this->call_parent('_get_field_names');
        $ret[] = 'nextgen_pro_lightbox_triggers_display';
        return $ret;
    }
    function _render_nextgen_pro_lightbox_triggers_display_field($display_type)
    {
        return $this->_render_select_field($display_type, 'ngg_triggers_display', __('Display Triggers', 'nextgen-gallery-pro'), array('always' => __('Always', 'nextgen-gallery-pro'), 'exclude_mobile' => __('Exclude Small Screens', 'nextgen-gallery-pro'), 'never' => __('Never', 'nextgen-gallery-pro')), isset($display_type->settings['ngg_triggers_display']) ? $display_type->settings['ngg_triggers_display'] : 'always');
    }
    function _render_nextgen_pro_lightbox_triggers_style_field($display_type)
    {
        return $this->_render_select_field($display_type, 'ngg_triggers_style', __('Triggers Style', 'nextgen-gallery-pro'), array('plain' => __('Plain', 'nextgen-gallery-pro'), 'fancy' => __('Fancy', 'nextgen-gallery-pro')), isset($display_type->settings['ngg_triggers_style']) ? $display_type->settings['ngg_triggers_style'] : 'plain');
    }
}
class C_NextGen_Pro_Lightbox_Trigger extends C_Displayed_Gallery_Trigger
{
    static $_pro_lightbox_enabled = NULL;
    function get_css_class()
    {
        $classes = 'fa ngg-trigger nextgen_pro_lightbox';
        if ($this->name == NGG_PRO_LIGHTBOX_TRIGGER) {
            return $classes . ' fa-share-square';
        } else {
            return $classes . ' fa-comment';
        }
    }
    static function is_renderable($name, $displayed_gallery)
    {
        $retval = FALSE;
        // Both of these triggers require the Pro Lightbox to be configured as the lightbox effect
        if (self::are_triggers_enabled($displayed_gallery) && self::is_pro_lightbox_enabled() && self::does_source_return_images($displayed_gallery)) {
            // If comments are enabled, display the trigger button to open the comments sidebar
            if ($name == NGG_PRO_LIGHTBOX_COMMENT_TRIGGER) {
                $library = self::get_pro_lightbox();
                if (isset($library->values['nplModalSettings']['enable_comments']) && $library->values['nplModalSettings']['enable_comments']) {
                    $retval = TRUE;
                }
            } else {
                $retval = TRUE;
            }
        }
        return $retval;
    }
    static function does_source_return_images($displayed_gallery)
    {
        $retval = FALSE;
        if (($source = $displayed_gallery->get_source()) && in_array('image', $source->returns)) {
            $retval = TRUE;
        }
        return $retval;
    }
    static function is_pro_lightbox_enabled()
    {
        if (is_null(self::$_pro_lightbox_enabled)) {
            $settings = C_NextGen_Settings::get_instance();
            if ($settings->thumbEffect == NGG_PRO_LIGHTBOX) {
                self::$_pro_lightbox_enabled = TRUE;
            } else {
                self::$_pro_lightbox_enabled = FALSE;
            }
        }
        return self::$_pro_lightbox_enabled;
    }
    static function are_triggers_enabled($displayed_gallery)
    {
        return isset($displayed_gallery->display_settings['ngg_triggers_display']) && $displayed_gallery->display_settings['ngg_triggers_display'] != 'never';
    }
    static function get_pro_lightbox()
    {
        return C_Lightbox_Library_Manager::get_instance()->get(NGG_PRO_LIGHTBOX);
    }
    function get_attributes()
    {
        $retval = array('class' => $this->get_css_class(), 'data-nplmodal-gallery-id' => $this->displayed_gallery->id());
        // If we're adding the trigger to an image, then we need
        // to add an attribute for the Pro Lightbox to know which image to display
        if ($this->view->get_id() == 'nextgen_gallery.image') {
            $image = $this->view->get_object();
            $retval['data-image-id'] = $image->{$image->id_field};
        }
        // If we're adding the commenting trigger, then we need to tell the
        // Pro Lightbox to open the sidebar when clicked
        if ($this->name == NGG_PRO_LIGHTBOX_COMMENT_TRIGGER) {
            $retval['data-nplmodal-show-comments'] = 1;
        }
        return $retval;
    }
    function enqueue_resources($displayed_gallery)
    {
        $router = C_Router::get_instance();
        $enqueue = FALSE;
        if (!wp_style_is('fontawesome', 'registered')) {
            $enqueue = C_Display_Type_Controller::get_instance()->enqueue_displayed_gallery_trigger_buttons_resources($displayed_gallery);
        }
        if ($enqueue !== FALSE) {
            if (!wp_style_is('ngg-trigger-buttons', 'registered')) {
                wp_register_style('ngg-trigger-buttons', $router->get_static_url('photocrati-nextgen_pro_lightbox#trigger_buttons.css'), array(), NGG_PRO_LIGHTBOX_VERSION);
                wp_enqueue_style('ngg-trigger-buttons');
            }
        }
    }
}
class C_OpenGraph_Controller extends C_MVC_Controller
{
    static $_instances = array();
    /**
     * Returns an instance of the controller in a particular context
     * @param string|bool $context
     * @return C_OpenGraph_Controller
     */
    static function get_instance($context = FALSE)
    {
        if (!isset(self::$_instances[$context])) {
            $klass = get_class();
            self::$_instances[$context] = new $klass($context);
        }
        return self::$_instances[$context];
    }
    // /nextgen-share/{url}/{slug}
    function index_action()
    {
        wp_dequeue_script('photocrati_ajax');
        wp_dequeue_script('frame_event_publisher');
        wp_dequeue_script('jquery');
        wp_dequeue_style('nextgen_gallery_related_images');
        $img_mapper = C_Image_Mapper::get_instance();
        $image_id = $this->param('image_id');
        if ($image = $img_mapper->find($image_id)) {
            $displayed_gallery_id = $this->param('displayed_gallery_id');
            // Template parameters
            $params = array('img' => $image);
            // Get the url & dimensions
            $named_size = $this->param('named_size');
            $storage = C_Gallery_Storage::get_instance();
            $dimensions = $storage->get_image_dimensions($image, $named_size);
            $image->url = $storage->get_image_url($image, $named_size, TRUE);
            $image->width = $dimensions['width'];
            $image->height = $dimensions['height'];
            // Generate the lightbox url
            $router = $this->get_router();
            $lightboxes = C_Lightbox_Library_Manager::get_instance();
            $lightbox = $lightboxes->get(NGG_PRO_LIGHTBOX);
            $uri = urldecode($this->param('uri'));
            $lightbox_slug = $lightbox->values['nplModalSettings']['router_slug'];
            $qs = $this->get_querystring();
            if ($qs) {
                $lightbox_url = $router->get_url('/', FALSE, 'root');
                $lightbox_url .= "?" . $qs;
            } else {
                $lightbox_url = $router->get_url($uri, FALSE, 'root');
                $lightbox_url .= '/';
            }
            $params['lightbox_url'] = "{$lightbox_url}#{$lightbox_slug}/{$displayed_gallery_id}/{$image_id}";
            // Add the blog name
            $params['blog_name'] = get_bloginfo('name');
            if ($lightbox->values['nplModalSettings']['enable_twitter_cards']) {
                $params['enable_twitter_cards'] = $lightbox->values['nplModalSettings']['enable_twitter_cards'];
                $params['twitter_username'] = $lightbox->values['nplModalSettings']['twitter_username'];
            }
            // Add routed url
            $protocol = $router->is_https() ? 'https://' : 'http://';
            $params['routed_url'] = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            // Render the opengraph metadata
            $this->expires('+1 day');
            $this->render_partial("photocrati-nextgen_pro_lightbox#opengraph", $params);
            exit;
            // Prevent themes from conflicting and displaying a second set of OpenGraph tags
        } else {
            header(__('Status: 404 Image not found', 'nextgen-gallery-pro'));
            echo __('Image not found', 'nextgen-gallery-pro');
        }
    }
    /**
     * The querystring contains the URI segment to return to, but possibly other querystring data that should be included
     * in the lightbox url. This function returns the querystring without the return data
     */
    function get_querystring()
    {
        // The URI parameter is handled independently of this method
        $mutation_one = preg_replace("/uri=[^&]+&?/", '', $this->get_router()->get_querystring());
        // fbclid is provided by facebook and clutters our otherwise pretty url
        $mutation_two = preg_replace("/fbclid=[^&]+&?/", '', $mutation_one);
        return $mutation_two;
    }
}