<?php

class M_NextGEN_Pro_Marketing extends C_Base_Module
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
            'photocrati-nextgen_pro_marketing',
            'NextGEN Pro Marketing',
            'Adds marketing to encourage Starter and Plus users to upgrade to Pro',
            '3.6.0',
            'https://www.imagely.com/wordpress-gallery-plugin/nextgen-gallery/',
            'Imagely',
            'https://www.imagely.com'
        );
    }

    function _register_adapters()
    {
        $registry = C_Component_Registry::get_instance();

        // Remove the existing marketing page provided by NGG; we'll reuse it
        $registry->del_adapter('I_Page_Manager', 'A_NextGen_Pro_Upgrade_Page');
        $registry->del_adapter('I_Page_Manager', 'A_NextGen_Pro_Plus_Upgrade_Page');

        if (is_admin() && (defined('NGG_PLUS_PLUGIN_VERSION') || defined('NGG_STARTER_PLUGIN_VERSION')))
        {
            // Ensure that the upgrade page is at the end of the menu
            add_filter('custom_menu_order', function($menu) {
                global $submenu;
                $ngg = &$submenu['nextgen-gallery'];

                // It's possible for users with limited access to not have the NGG submenu added
                if (empty($ngg) || !is_array($ngg))
                    return $menu;

                foreach ($ngg as $ndx => $page) {
                    if ($page[2] === "ngg_starter_plus_upgrade") {
                        $backup = $ngg[$ndx];
                        unset ($ngg[$ndx]);
                        $ngg[] = $backup;
                    }
                }
                return $menu;
            });

            $registry->add_adapter('I_Page_Manager', 'A_NextGEN_StarterPlus_Upgrade_Page_Setup');
            $registry->add_adapter(
                'I_NextGen_Admin_Page',
                'A_NextGEN_StarterPlus_Upgrade_Page_Controller',
                'ngg_starter_plus_upgrade'
            );
        }
    }

    function get_type_list()
    {
        return array(
            'A_NextGEN_StarterPlus_Upgrade_Page_Setup'      => 'adapter.upgrade_page_setup.php',
            'A_NextGEN_StarterPlus_Upgrade_Page_Controller' => 'adapter.upgrade_page_controller.php'
        );
    }
}

new M_NextGen_Pro_Marketing();
