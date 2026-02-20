<?php
/*
 {
    Module: photocrati-nextgen_pro_captions
 }
 */
class M_NextGen_Pro_Captions extends C_Base_Module
{
    public static $display_types = array(
        'photocrati-nextgen_pro_masonry',
        'photocrati-nextgen_pro_thumbnail_grid',
        'photocrati-nextgen_pro_blog_gallery',
        'photocrati-nextgen_pro_film',
        'photocrati-nextgen_pro_mosaic',
		'photocrati-nextgen_pro_sidescroll',
		'photocrati-nextgen_pro_tile'
    );

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
            'photocrati-nextgen_pro_captions',
            'NextGEN Pro Captions',
            "Provides image caption effects",
            '3.3.0',
            'https://www.imagely.com/wordpress-gallery-plugin/nextgen-pro/',
            'Imagely',
            'https://www.imagely.com',
            $context
        );
    }

    function _register_hooks()
    {
        add_action('wp_enqueue_scripts', array($this, 'register_captions'), -250);
    }

    function _register_adapters()
    {
        $registry = $this->get_registry();
        if (M_Attach_To_Post::is_atp_url() || is_admin())
        {
            foreach (self::$display_types as $display_type) {
                $registry->add_adapter('I_Form', 'A_NextGen_Pro_Captions_Form', $display_type);
            }
        }

        $registry->add_adapter('I_Display_Type_Controller', 'A_NextGen_Pro_Captions_Resources');
        $registry->add_adapter('I_Display_Type_Mapper', 'A_NextGen_Pro_Captions_Display_Type_Mapper');
    }

    function register_captions()
    {
        $router = C_Router::get_instance();
        wp_register_script(
            'nextgen_pro_captions_imagesloaded',
            $router->get_static_url('photocrati-nextgen_pro_captions#imagesloaded.min.js'),
            array('jquery')
        );
        wp_register_script(
            'nextgen_pro_captions-js',
            $router->get_static_url('photocrati-nextgen_pro_captions#captions.js'),
            ['underscore', 'shave.js', 'nextgen_pro_captions_imagesloaded'],
            FALSE,
            TRUE
        );
        wp_register_style(
            'nextgen_pro_captions-css',
            $router->get_static_url('photocrati-nextgen_pro_captions#captions.css')
        );
    }

    function get_type_list()
    {
        return array(
            'A_NextGen_Pro_Captions_Form'                => 'adapter.nextgen_pro_captions_form.php',
            'A_NextGen_Pro_Captions_Resources'           => 'adapter.nextgen_pro_captions_resources.php',
            'A_NextGen_Pro_Captions_Display_Type_Mapper' => 'adapter.nextgen_pro_captions_display_type_mapper.php'
        );
    }
}

new M_NextGen_Pro_Captions;
