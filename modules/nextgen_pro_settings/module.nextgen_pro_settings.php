<?php
/* { Module: photocrati-nextgen_pro_settings,
	 Depends: { photocrati-nextgen_other_options }} */

class M_NextGen_Pro_Settings extends C_Base_Module
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
            'photocrati-nextgen_pro_settings',
            'Pro Options',
            'Provides additional settings management features',
            '3.0.19',
            'https://www.imagely.com/wordpress-gallery-plugin/nextgen-pro/',
            'Imagely',
            'https://www.imagely.com'
        );
    }

    function _register_adapters()
    {
        if (is_admin())
            $this->get_registry()->add_adapter('I_Form', 'A_NextGen_Pro_Settings_Reset_Form', 'reset');
    }

    function get_type_list()
    {
        return array('A_NextGen_Pro_Settings_Reset_Form' => 'adapter.nextgen_pro_settings_reset_form.php');
    }
}

new M_NextGen_Pro_Settings;
