<?php

/**
 * To allow compatibility with the legacy Photocrati theme that still has this module of a same name this class has been
 * gutted to only have the same function signatures and returns.
 *
 * The static and templates directories remain for the same reason.
 */
class M_AutoUpdate_Admin extends C_Base_Module
{
    public $_updater      = NULL;
    public $_update_list  = NULL;
    public $_controller   = NULL;
    public $_ajax_handler = NULL;

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
            'photocrati-auto_update-admin',
            'Photocrati Auto Update Admin',
            "Provides an AJAX admin interface to sequentially and progressively download and install updates",
            '3.10.0',
            'https://www.imagely.com/wordpress-gallery-plugin/nextgen-pro/',
            'Imagely',
            'https://www.imagely.com'
        );
    }

    function _register_adapters() {}

    function _register_hooks() {}

    public function _get_cached_update_list()
    {
        return [];
    }

    /**
     * We don't actually alter $value here
     *
     * @param mixed $value
     * @return array|mixed
     */
    function _get_update_list($value = NULL)
    {
    	return NULL;
    }

    function _get_text_list()
    {
        return [];
    }

    function get_update_page_url()
    {
        return '';
    }

    function admin_init() {}

    function admin_menu() {}

    function dashboard_setup() {}

    function dashboard_widget() {}

    function get_type_list()
    {
        return [];
    }
}

new M_AutoUpdate_Admin();
