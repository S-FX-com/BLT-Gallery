<?php

/**
 * To maintain compatibility with the legacy Photocrati theme this module must not yet be refactored into a generic
 * licensing module now that the autoupdate_admin module has been removed; if this module exists but the below methods
 * are not implemented the theme will generate fatal errors executing this module's code.
 */
class M_AutoUpdate extends C_Base_Module
{
    public $_api_url = NULL;

    private static $_license_check_cache = NULL;

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
            'photocrati-auto_update',
            'Photocrati Auto Update',
            "Provides automatic updates",
            '3.10.0',
            'https://www.imagely.com/wordpress-gallery-plugin/nextgen-pro/',
            'Imagely',
            'https://www.imagely.com'
        );
    }

    /**
     * @return M_Licensing
     */
    public function get_licensing_module()
    {
        // $module is not immediately returned just so I can doctype it and make PHPStorm not classify it as an error.

        /** @var M_Licensing $module */
        $module = C_Component_Registry::get_instance()->get_module('imagely-licensing');
        return $module;
    }

    function _get_api_url()
    {
        return $this->get_licensing_module()->_get_api_url();
    }

    function get_license($product = NULL)
    {
        return $this->get_licensing_module()->get_license($product);
    }

    function set_license($license, $product = NULL)
    {
        return $this->get_licensing_module()->set_license($license, $product);
    }

    function get_product_list()
    {
        return $this->get_licensing_module()->get_product_list();
    }

    function get_module_list()
    {
        return $this->get_licensing_module()->get_module_list();
    }

    function api_request($url, $action, $parameter_list = NULL)
    {
        return $this->get_licensing_module()->api_request($url, $action, $parameter_list);
    }

    static function is_valid_license($return_status = FALSE)
    {
        return M_Licensing::is_valid_license($return_status);
    }

    function get_type_list()
    {
        return [];
    }

}

new M_AutoUpdate();
