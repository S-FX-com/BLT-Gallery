<?php

/* { Module: photocrati-nextgen_pro_mosaic } */

define('NGG_PRO_MOSAIC', 'photocrati-nextgen_pro_mosaic');
define('NGG_PRO_MOSAIC_VERSION', '3.0.19');

class M_NextGen_Pro_Mosaic extends C_Base_Module
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
            NGG_PRO_MOSAIC,
            'Mosaic Display Type',
            'Provides the Pro Mosaic display type',
            NGG_PRO_MOSAIC_VERSION,
            'https://www.imagely.com/wordpress-gallery-plugin/nextgen-pro/',
            'Imagely',
            'https://www.imagely.com',
            $context
        );
    }

    function _register_utilities()
    {
        C_Photocrati_Installer::add_handler($this->module_id, 'C_NextGen_Pro_Mosaic_Installer');
    }

    function _register_adapters()
    {
        $this->get_registry()
             ->add_adapter('I_Display_Type_Mapper',
                           'A_Mosaic_Display_Type_Mapper');

        $this->get_registry()
             ->add_adapter('I_Display_Type_Controller',
                           'A_Mosaic_Controller',
                           $this->module_id);

        if (M_Attach_To_Post::is_atp_url() || is_admin())
        {
            $this->get_registry()
                 ->add_adapter('I_Form',
                               'A_Mosaic_Form',
                               $this->module_id);
        }
    }

    function get_type_list()
    {
        return array(
            'A_Mosaic_Controller'              => 'adapter.mosaic_controller.php',
            'A_Mosaic_Display_Type_Mapper'     => 'adapter.mosaic_display_type_mapper.php',
            'A_Mosaic_Form'                    => 'adapter.mosaic_form.php',
        );
    }

    function initialize() {
        parent::initialize();
        C_Form_Manager::get_instance()->add_form(NGG_DISPLAY_SETTINGS_SLUG, NGG_PRO_MOSAIC);
    }
}

class C_NextGen_Pro_Mosaic_Installer extends C_Gallery_Display_Installer
{
    function install($reset = FALSE)
    {
        $this->install_display_type(
            NGG_PRO_MOSAIC, array(
                'title'                 => __('NextGen Pro Mosaic', 'nextgen-gallery-pro'),
                'entity_types'          => array('image'),
                'default_source'        => 'galleries',
                'preview_image_relpath' => NGG_PRO_MOSAIC . '#preview.jpg',
                'hidden_from_ui'        => FALSE,
                'view_order'            => NGG_DISPLAY_PRIORITY_BASE + (NGG_DISPLAY_PRIORITY_STEP * 10) + 55,
                'aliases'               => array(
                    'pro_mosaic',
                    'mosaic',
                    'nextgen_pro_mosaic'
                )
            )
        );
    }

    function uninstall($hard = FALSE)
    {
        $mapper = C_Display_Type_Mapper::get_instance();
        if (($entity = $mapper->find_by_name(NGG_PRO_MOSAIC)))
        {
            if ($hard)
            {
                $mapper->destroy($entity);
            }
            else {
                $entity->hidden_from_ui = TRUE;
                $mapper->save($entity);
            }
        }
    }
}

$M_NextGen_Pro_Mosaic = new M_NextGen_Pro_Mosaic();
