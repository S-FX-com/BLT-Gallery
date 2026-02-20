<?php

/***
    {
        Product: photocrati-nextgen-plus,
        Depends: { photocrati-nextgen }
    }
***/

class P_Photocrati_NextGen_Plus extends C_Base_Product
{
    static $modules_provided = array(
        'imagely-licensing'                           => 'backend',
        'photocrati-auto_update'                      => 'backend',
        'photocrati-auto_update-admin'                => 'backend',
        'photocrati-nextgen_pro_i18n'                 => 'always',
        'photocrati-nextgen_picturefill'              => 'always',
        'photocrati-image_protection'                 => 'always',
        'photocrati-galleria'                         => 'always',
        'photocrati-comments'                         => 'always',
        'photocrati-nextgen_pro_tile'                 => 'always',
        'photocrati-nextgen_pro_slideshow'            => 'always',
        'photocrati-nextgen_pro_horizontal_filmstrip' => 'always',
        'photocrati-nextgen_pro_thumbnail_grid'       => 'always',
        'photocrati-nextgen_pro_blog_gallery'         => 'always',
        'photocrati-nextgen_pro_film'                 => 'always',
        'photocrati-nextgen_pro_masonry'              => 'always',
        'photocrati-nextgen_pro_albums'               => 'always',
        'photocrati-nextgen_pro_mosaic'               => 'always',
        'photocrati-nextgen_pro_sidescroll'           => 'always',
        'photocrati-nextgen_pro_imagebrowser'         => 'always',
		'imagely-pro-search'					      => 'always',
        'photocrati-nextgen_pro_lightbox'             => 'always',
        'photocrati-nextgen_pro_captions'             => 'always',
        'photocrati-nextgen_pro_marketing'            => 'backend'
    );

    function get_modules_provided()
    {
        return array_keys(self::$modules_provided);
    }

    function get_modules_to_load()
    {
        $retval = array();

        foreach (self::$modules_provided as $module_name => $condition) {
            switch ($condition) {
                case 'always':
                    $retval[] = $module_name;
                    break;
                case 'backend':
                    if (is_admin())
                        $retval[] = $module_name;
                    break;
                case 'frontend':
                    if (!is_admin())
                        $retval[] = $module_name;
                    break;
            }
        }

        $retval = apply_filters('ngg_plus_get_modules_to_load', $retval, self::$modules_provided);

        return $retval;
    }

    function define($id = 'pope-product',
                    $name = 'Pope Product',
                    $description = '',
                    $version = '',
                    $uri = '',
                    $author = '',
                    $author_uri = '',
                    $context = FALSE)
    {
        parent::define(
            'photocrati-nextgen-plus',
            'Photocrati NextGEN Plus',
            'Photocrati NextGEN Plus',
            NGG_PLUS_PLUGIN_VERSION,
            'http://www.nextgen-gallery.com',
            'Imagely',
            'http://www.imagely.com'
        );

        $this->get_registry()->set_product_module_path($this->module_id, dirname(__FILE__));

        include_once('class.nextgen_plus_installer.php');
        C_Photocrati_Installer::add_handler($this->module_id, 'C_NextGen_Plus_Installer');
    }

    function load()
    {
        $registry = $this->get_registry();
        foreach ($this->get_modules_to_load() as $module_name) {
            $registry->load_module($module_name);
        }
        parent::load();
    }
}

new P_Photocrati_NextGen_Plus();
