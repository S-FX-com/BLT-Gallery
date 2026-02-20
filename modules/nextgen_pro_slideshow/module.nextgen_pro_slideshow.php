<?php

/*
 {
	Module:		photocrati-nextgen_pro_slideshow,
	Depends:	{ photocrati-galleria }
 }
 */

define('NGG_PRO_SLIDESHOW', 'photocrati-nextgen_pro_slideshow');

class M_NextGen_Pro_Slideshow extends C_Base_Module
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
			'photocrati-nextgen_pro_slideshow',
			'NextGen Pro Slideshow',
			"Provides Photocrati's Slideshow Gallery Type",
            '3.3.0',
            'https://www.imagely.com/wordpress-gallery-plugin/nextgen-pro/',
            'Imagely',
            'https://www.imagely.com',
			$context
		);

		C_Photocrati_Installer::add_handler($this->module_id, 'C_NextGen_Pro_Slideshow_Installer');
	}

	function get_type_list()
	{
		return array(
			'A_Nextgen_Pro_Slideshow_Controller' => 'adapter.nextgen_pro_slideshow_controller.php',
			'A_Nextgen_Pro_Slideshow_Form' => 'adapter.nextgen_pro_slideshow_form.php',
			'A_Nextgen_Pro_Slideshow_Mapper' => 'adapter.nextgen_pro_slideshow_mapper.php'
		);
	}


	function _register_adapters()
	{
		// Provides default values for the display type
		$this->get_registry()->add_adapter('I_Display_Type_Mapper', 'A_NextGen_Pro_Slideshow_Mapper');
        $this->get_registry()->add_adapter('I_Display_Type_Controller', 'A_NextGen_Pro_Slideshow_Controller', $this->module_id);

        if (M_Attach_To_Post::is_atp_url() || is_admin())
            $this->get_registry()->add_adapter('I_Form', 'A_NextGen_Pro_Slideshow_Form', $this->module_id);
	}

    function initialize()
    {
        if (M_Attach_To_Post::is_atp_url() || is_admin())
            C_Form_Manager::get_instance()->add_form(NGG_DISPLAY_SETTINGS_SLUG, NGG_PRO_SLIDESHOW);
    }
}

class C_NextGen_Pro_Slideshow_Installer extends C_Gallery_Display_Installer
{
    function install($reset = FALSE)
    {
        $this->install_display_types();
    }

    function install_display_types()
    {
        $this->install_display_type(
            NGG_PRO_SLIDESHOW, array(
                'title'							=>	__('NextGEN Pro Slideshow', 'nextgen-gallery-pro'),
                'entity_types'					=>	array('image'),
                'default_source'				=>	'galleries',
                'preview_image_relpath'			=>	'photocrati-nextgen_pro_slideshow#preview.jpg',
                'hidden_from_ui'                =>  FALSE,
                'view_order'                    => NGG_DISPLAY_PRIORITY_BASE + (NGG_DISPLAY_PRIORITY_STEP * 10) + 10,
                'aliases'                       => array(
                    'slideshow',
                    'pro_slideshow',
                    'nextgen_pro_slideshow'
                )
            )
        );
    }

    function uninstall($hard = FALSE)
    {
        $mapper = C_Display_Type_Mapper::get_instance();
        if (($entity = $mapper->find_by_name(NGG_PRO_SLIDESHOW)))
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

new M_NextGen_Pro_Slideshow;
