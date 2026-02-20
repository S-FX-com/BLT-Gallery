<?php

define('M_Imagely_Search_ID', 'imagely-pro-search');

class M_Search extends C_Base_Module
{
    function define($id = 'pope-module',
                    $name = 'Pope Module',
                    $description = '',
                    $version = '',
                    $uri = '',
                    $author = '',
                    $author_uri = '',
                    $context = FALSE)
    {
        parent::define(
            M_Imagely_Search_ID,
            'NextGEN Frontend Search',
            'Allows users to search for images on the site frontend',
            '3.11.0',
            'https://www.imagely.com/wordpress-gallery-plugin/nextgen-gallery/',
            'Imagely',
            'https://www.imagely.com'
        );

        C_Photocrati_Installer::add_handler($this->module_id, 'C_Search_Installer');
    }

    function initialize()
    {
        parent::initialize();

        if (M_Attach_To_Post::is_atp_url() || is_admin())
            C_Form_Manager::get_instance()->add_form(NGG_DISPLAY_SETTINGS_SLUG, $this->module_id);
    }

    function _register_adapters()
    {
        $registry = $this->get_registry();
        $registry->add_adapter('I_Display_Type_Controller', 'A_Search_Controller', $this->module_id);
        $registry->add_adapter('I_Display_Type_Mapper',     'A_Search_Mapper');
        $registry->add_adapter('I_Routing_App',             'A_Search_URLs');

        if (M_Attach_To_Post::is_atp_url() || is_admin())
            $registry->add_adapter('I_Form', 'A_Search_Form', $this->module_id);
    }

    function _register_hooks()
    {
        add_action('ngg_routes', array($this, 'define_routes'));

        // Flush the cache when any of these actions are triggered
        add_action('ngg_added_new_image',    array($this, 'flush_cache'));
        add_action('ngg_album_updated',      array($this, 'flush_cache'));
        add_action('ngg_delete_album',       array($this, 'flush_cache'));
        add_action('ngg_delete_gallery',     array($this, 'flush_cache'));
        add_action('ngg_delete_image',       array($this, 'flush_cache'));
        add_action('ngg_delete_picture',     array($this, 'flush_cache'));
        add_action('ngg_image_updated',      array($this, 'flush_cache'));
        add_action('ngg_manage_tags',        array($this, 'flush_cache'));
        add_action('ngg_recovered_image',    array($this, 'flush_cache'));
        add_action('ngg_updated_image_meta', array($this, 'flush_cache'));

        add_action('widget_text', array($this, 'widget_text'));
    }

    /**
     * Prevent this display type from being used in a widget.
     *
     * Due to its use of URL as a key component of how frontend search functions we currently just do not
     * support this display type as a widget. If there's no page/post we simply don't do anything.
     * @param string $text
     * @return string
     */
    public function widget_text($text)
    {
        preg_match_all('/' . get_shortcode_regex() . '/', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $shortcode) {
            if ($shortcode[2] !== 'ngg')
                continue;
            $found = preg_match("/display=['\"]imagely-search[\"']/i", $shortcode[3]);
            if ($found)
                $text = str_replace($shortcode[0], '', $text);
        }

        return $text;
    }

    public function flush_cache()
    {
        $transient_manager = C_Photocrati_Transient_Manager::get_instance();
        $transient_manager->clear('frontend_image_search', FALSE);
    }

    public function define_routes($router)
    {
        $slug = '/'.C_NextGen_Settings::get_instance()->router_param_slug;
        $router->rewrite("{*}{$slug}{*}/search/{*}/tagfilter/{*}", "{1}{$slug}{2}/nggsearch--{3}/tagfilter--{4}");
        $router->rewrite("{*}{$slug}{*}/search/{*}",               "{1}{$slug}{2}/nggsearch--{3}");
    }

    function get_type_list()
    {
        return array(
            'A_Search_Controller' => 'adapter.controller.php',
            'A_Search_Form'       => 'adapter.form.php',
            'A_Search_Mapper'     => 'adapter.mapper.php',
            'A_Search_URLs'       => 'adapter.search_urls.php'
        );
    }

}

class C_Search_Installer extends C_Gallery_Display_Installer
{
    function install($reset = FALSE)
    {
        global $wpdb;

        // Source the dbDelta() method
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // DO NOT remove the definition of description/alttext or the indexes wont be added by dbDelta()
        dbDelta("CREATE TABLE {$wpdb->prefix}ngg_pictures (
            description mediumtext NULL,
            alttext mediumtext NULL,
            FULLTEXT KEY `alttext_search` (`alttext`),
            FULLTEXT KEY `description_search` (`description`),
            FULLTEXT KEY `combined_search` (`alttext`, `description`)
        );");

        $this->install_display_type(
            M_Imagely_Search_ID, array(
                'title'                 => __('NextGen Frontend Image Search', 'nextgen-gallery-pro'),
                'entity_types'          => array('gallery', 'album', 'image'),
                'default_source'        => 'galleries',
                'preview_image_relpath' => M_Imagely_Search_ID . '#preview.png',
                'hidden_from_ui'        => FALSE,
                'view_order'            => NGG_DISPLAY_PRIORITY_BASE + (NGG_DISPLAY_PRIORITY_STEP * 10) + 60,
                'aliases'               => array()
            )
        );
    }
}

new M_Search();
