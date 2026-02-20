<?php
/***
{
    Module: photocrati-nextgen_pro_masonry,
    Depends: { photocrati-nextgen_gallery_display }
}
***/

define('NGG_PRO_MASONRY', 'photocrati-nextgen_pro_masonry');

class M_NextGen_Pro_Masonry extends C_Base_Module
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
            'photocrati-nextgen_pro_masonry',
            'NextGEN Pro Masonry',
            'Provides the NextGEN Pro Masonry Display Type',
            '3.3.0',
            'https://www.imagely.com/wordpress-gallery-plugin/nextgen-pro/',
            'Imagely',
            'https://www.imagely.com'
        );

		C_Photocrati_Installer::add_handler($this->module_id, 'C_NextGen_Pro_Masonry_Installer');
    }

    /**
     * Register adapters
     */
    function _register_adapters()
    {
        $this->get_registry()->add_adapter('I_Display_Type_Mapper', 'A_NextGen_Pro_Masonry_Mapper');
        $this->get_registry()->add_adapter('I_Display_Type_Controller', 'A_NextGen_Pro_Masonry_Controller', $this->module_id);

        if (M_Attach_To_Post::is_atp_url() || is_admin())
            $this->get_registry()->add_adapter('I_Form', 'A_NextGen_Pro_Masonry_Form', $this->module_id);
    }

    function get_type_list()
    {
        return array(
            'A_Nextgen_Pro_Masonry_Controller' => 'adapter.nextgen_pro_masonry_controller.php',
            'A_Nextgen_Pro_Masonry_Form' => 'adapter.nextgen_pro_masonry_form.php',
            'A_Nextgen_Pro_Masonry_Mapper' => 'adapter.nextgen_pro_masonry_mapper.php'
        );
    }

    function initialize()
    {
        parent::initialize();
        if (M_Attach_To_Post::is_atp_url() || is_admin())
            C_Form_Manager::get_instance()->add_form(NGG_DISPLAY_SETTINGS_SLUG, NGG_PRO_MASONRY);
    }
}

class C_NextGen_Pro_Masonry_Installer extends C_Gallery_Display_Installer
{
    function install($reset = FALSE)
    {
        $this->install_display_types();
    }

    function install_display_types()
    {
        $this->install_display_type(
            NGG_PRO_MASONRY, array(
                'title'                 => __('NextGEN Pro Masonry', 'nextgen-gallery-pro'),
                'entity_types'          => array('image'),
                'preview_image_relpath' => 'photocrati-nextgen_pro_masonry#preview.jpg',
                'default_source'        => 'galleries',
                'hidden_from_ui'        => FALSE,
                'view_order'            => NGG_DISPLAY_PRIORITY_BASE + (NGG_DISPLAY_PRIORITY_STEP * 10) + 50,
                'aliases'               => array(
                    'masonry',
                    'pro_masonry',
                    'nextgen_pro_masonry',
                    'basic_masonry'
                )
            )
        );
    }

    function uninstall($hard = FALSE)
    {
        $mapper = C_Display_Type_Mapper::get_instance();
        if (($entity = $mapper->find_by_name(NGG_PRO_MASONRY)))
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

new M_NextGen_Pro_Masonry();
