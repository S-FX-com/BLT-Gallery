<?php
/*** { Module: photocrati-nextgen_pro_tile,
       Depends: { photocrati-nextgen_gallery_display } } ***/

define('NGG_PRO_TILE', 'photocrati-nextgen_pro_tile');

class M_NextGen_Pro_Tile extends C_Base_Module
{

    // Used in both the controller and individual-image.php template
    public static $default_size_params = array(
        'width'  => 800,
        'height' => 600
    );

    function define($id          = 'pope-module',
                    $name        = 'Pope Module',
                    $description = '',
                    $version     = '',
                    $uri         = '',
                    $author      = '',
                    $author_uri  = '',
                    $context     = FALSE)
    {
        parent::define(
            'photocrati-nextgen_pro_tile',
            'NextGEN Pro Tile',
            'Provides the NextGEN Pro Tile Display Type',
            '3.3.0',
            'https://www.imagely.com/wordpress-gallery-plugin/nextgen-gallery/',
            'Imagely',
            'https://www.imagely.com'
        );

        C_Photocrati_Installer::add_handler($this->module_id, 'C_NextGen_Pro_Tile_Installer');
    }

    function initialize()
    {
        parent::initialize();
        if (is_admin())
        {
            $forms = C_Form_Manager::get_instance();
            $forms->add_form(
                NGG_DISPLAY_SETTINGS_SLUG, NGG_PRO_TILE
            );
        }
    }

    /**
     * Register adapters required for the NextGen Pro Tile
     */
    function _register_adapters()
    {
        $this->get_registry()->add_adapter('I_Display_Type_Mapper','A_NextGen_Pro_Tile_Mapper');
        $this->get_registry()->add_adapter('I_Display_Type',       'A_NextGen_Pro_Tile');

        if (M_Attach_To_Post::is_atp_url() || is_admin())
            $this->get_registry()->add_adapter('I_Form','A_NextGen_Pro_Tile_Form', $this->module_id);

        $this->get_registry()->add_adapter('I_Display_Type_Controller', 'A_NextGen_Pro_Tile_Controller', $this->module_id);
    }

    function _register_hooks()
    {
    }

    function get_type_list()
    {
        return array(
            'A_Nextgen_Pro_Tile'            => 'adapter.nextgen_pro_tile.php',
            'A_Nextgen_Pro_Tile_Controller' => 'adapter.nextgen_pro_tile_controller.php',
            'A_Nextgen_Pro_Tile_Form'       => 'adapter.nextgen_pro_tile_form.php',
            'A_Nextgen_Pro_Tile_Mapper'     => 'adapter.nextgen_pro_tile_mapper.php',
            'C_Nextgen_Pro_Tile_Installer'  => 'class.nextgen_pro_tile_installer.php'
        );
    }
}

class C_NextGen_Pro_Tile_Installer extends C_Gallery_Display_Installer
{
    function install($reset = FALSE)
    {
        $this->install_display_type(
            NGG_PRO_TILE, array(
                'title'                 => __('NextGEN Pro Tile', 'nextgen-gallery-pro'),
                'entity_types'          => array('image'),
                'preview_image_relpath' => 'photocrati-nextgen_pro_tile#preview.jpg',
                'default_source'        => 'galleries',
                'view_order'            => NGG_DISPLAY_PRIORITY_BASE + (NGG_DISPLAY_PRIORITY_STEP * 10) - 10,
                'aliases'               => array(
                    'pro_tile',
                    'tile',
                    'nextgen_pro_tile'
                )
            )
        );
    }
}

new M_NextGen_Pro_Tile();
