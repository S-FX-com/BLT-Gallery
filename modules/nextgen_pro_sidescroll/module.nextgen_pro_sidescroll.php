<?php
/*
{
	Module: photocrati-nextgen_pro_sidescroll
}
 */
define('NGG_PRO_SIDESCROLL', 'photocrati-nextgen_pro_sidescroll');
class M_NextGen_Pro_Sidescroll extends C_Base_Module
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
			'photocrati-nextgen_pro_sidescroll',
			'NextGEN Pro Sidescroll',
			'Provides a horizontal scrolling gallery for NextGEN Gallery',
            '3.3.0',
            'https://www.imagely.com/wordpress-gallery-plugin/nextgen-pro/',
            'Imagely',
            'https://www.imagely.com',
			$context
		);

		C_Photocrati_Installer::add_handler($this->module_id, 'C_NextGen_Pro_Sidescroll_Installer');
	}

	function get_type_list()
	{
		return array(
			'A_Nextgen_Pro_Sidescroll_Controller' => 'adapter.nextgen_pro_sidescroll_controller.php',
			'A_Nextgen_Pro_Sidescroll_Form' => 'adapter.nextgen_pro_sidescroll_form.php',
			'A_Nextgen_Pro_Sidescroll_Mapper' => 'adapter.nextgen_pro_sidescroll_mapper.php',
		);
	}

	function _register_adapters()
	{
		$this->get_registry()->add_adapter('I_Display_Type_Mapper', 'A_NextGen_Pro_Sidescroll_Mapper');
        $this->get_registry()->add_adapter('I_Display_Type_Controller', 'A_NextGen_Pro_Sidescroll_Controller', $this->module_id);

        if (M_Attach_To_Post::is_atp_url() || is_admin())
            $this->get_registry()->add_adapter('I_Form', 'A_NextGen_Pro_Sidescroll_Form', $this->module_id);
	}

    function initialize()
    {
        parent::initialize();
        if (M_Attach_To_Post::is_atp_url() || is_admin())
            C_Form_Manager::get_instance()->add_form(NGG_DISPLAY_SETTINGS_SLUG, NGG_PRO_SIDESCROLL);
    }
}

class C_NextGen_Pro_Sidescroll_Installer extends C_Gallery_Display_Installer
{
    function install($reset = FALSE)
    {
        $this->install_display_types();
    }

    function install_display_types()
    {
        $this->install_display_type(
            NGG_PRO_SIDESCROLL, array(
                'title'						=>	__('NextGEN Pro Sidescroll', 'nextgen-gallery-pro'),
                'entity_types'				=>	array('image'),
                'default_source'			=>	'galleries',
                'preview_image_relpath'		=>	'photocrati-nextgen_pro_sidescroll#preview.jpg',
                'hidden_from_ui'            =>  FALSE,
                'view_order' => NGG_DISPLAY_PRIORITY_BASE + (NGG_DISPLAY_PRIORITY_STEP * 10) + 30,
                'aliases'                   =>  array(
                    'pro_sidescroll',
                    'sidescroll'
                )
            )
        );
    }

    function uninstall($hard = FALSE)
    {
        $mapper = C_Display_Type_Mapper::get_instance();
        if (($entity = $mapper->find_by_name(NGG_PRO_SIDESCROLL)))
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

new M_NextGen_Pro_Sidescroll;
