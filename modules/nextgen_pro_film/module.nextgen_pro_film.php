<?php

define('NGG_PRO_FILM', 'photocrati-nextgen_pro_film');

class M_NextGen_Pro_Film extends C_Base_Module
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
            'photocrati-nextgen_pro_film',
            'NextGEN Pro Film',
            'Provides a film-like gallery for NextGEN Gallery',
            '3.9.0',
            'https://www.imagely.com/wordpress-gallery-plugin/nextgen-pro/',
            'Imagely',
            'https://www.imagely.com',
            $context
        );

        C_Photocrati_Installer::add_handler($this->module_id, 'C_NextGen_Pro_Film_Installer');
    }

    function initialize()
    {
        parent::initialize();
        if (M_Attach_To_Post::is_atp_url() || is_admin())
            C_Form_Manager::get_instance()->add_form(NGG_DISPLAY_SETTINGS_SLUG, NGG_PRO_FILM);
    }

    function _register_adapters()
    {
        $registry = $this->get_registry();
        $registry->add_adapter('I_Display_Type_Mapper', 'A_NextGen_Pro_Film_Mapper');
        $registry->add_adapter('I_Display_Type_Controller', 'A_NextGen_Pro_Film_Controller', $this->module_id);

        if (M_Attach_To_Post::is_atp_url() || is_admin())
            $registry->add_adapter('I_Form', 'A_NextGen_Pro_Film_Form', $this->module_id);
    }

    function get_type_list()
    {
        return array(
            'A_Nextgen_Pro_Film_Controller'     => 'adapter.nextgen_pro_film_controller.php',
            'A_Nextgen_Pro_Film_Dynamic_Styles' => 'adapter.nextgen_pro_film_dynamic_styles.php',
            'A_Nextgen_Pro_Film_Form'           => 'adapter.nextgen_pro_film_form.php',
            'A_Nextgen_Pro_Film_Mapper'         => 'adapter.nextgen_pro_film_mapper.php'
        );
    }
}

/**
 * @mixin C_Display_Type_Installer
 */
class C_NextGen_Pro_Film_Installer extends C_Gallery_Display_Installer
{
    function install($reset = FALSE)
    {
        $this->install_display_types();
    }

    function install_display_types()
    {
        $this->install_display_type(
            NGG_PRO_FILM, array(
                'aliases'               => array('pro_film', 'film'),
                'default_source'        => 'galleries',
                'entity_types'          => array('image'),
                'hidden_from_ui'        => FALSE,
                'preview_image_relpath' => 'photocrati-nextgen_pro_film#preview.jpg',
                'title'                 => __('NextGEN Pro Film', 'nextgen-gallery-pro'),
                'view_order'            => NGG_DISPLAY_PRIORITY_BASE + (NGG_DISPLAY_PRIORITY_STEP * 10) + 30
            )
        );
    }

    function uninstall()
    {
        $mapper = C_Display_Type_Mapper::get_instance();
        if (($entity = $mapper->find_by_name(NGG_PRO_FILM)))
        {
            $entity->hidden_from_ui = TRUE;
            $mapper->save($entity);
        }
    }
}

new M_NextGen_Pro_Film;
