<?php

define('NGG_PRO_IMAGEBROWSER','photocrati-nextgen_pro_imagebrowser');

class M_NextGen_Pro_ImageBrowser extends C_Base_Module
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
            NGG_PRO_IMAGEBROWSER,
            'NextGEN Pro ImageBrowser',
            'Provides the NextGEN Pro ImageBrowser Display Type',
            '3.3.0',
            'https://www.imagely.com/wordpress-gallery-plugin/nextgen-gallery/',
            'Imagely',
            'https://www.imagely.com'
        );

        C_Photocrati_Installer::add_handler($this->module_id, 'C_NextGen_Pro_ImageBrowser_Installer');
    }

    function initialize()
    {
        parent::initialize();
        if (M_Attach_To_Post::is_atp_url() || is_admin())
            C_Form_Manager::get_instance()->add_form(NGG_DISPLAY_SETTINGS_SLUG, NGG_PRO_IMAGEBROWSER);
    }

    /**
     * Register adapters required for the NextGen Pro ImageBrowser
     */
    function _register_adapters()
    {
        $registry = $this->get_registry();

        $registry->add_adapter('I_Display_Type_Mapper', 'A_NextGen_Pro_ImageBrowser_Mapper');
        $registry->add_adapter('I_Display_Type',        'A_NextGen_Pro_ImageBrowser');
        $registry->add_adapter('I_Routing_App',         'A_NextGen_Pro_ImageBrowser_Urls');

        if (M_Attach_To_Post::is_atp_url() || is_admin())
            $registry->add_adapter('I_Form', 'A_NextGen_Pro_ImageBrowser_Form', $this->module_id);

        $registry->add_adapter('I_Display_Type_Controller', 'A_NextGen_Pro_ImageBrowser_Controller', $this->module_id);
    }

    function _register_hooks()
    {
        if (!defined('NGG_DISABLE_LEGACY_SHORTCODES') || !NGG_DISABLE_LEGACY_SHORTCODES)
        {
            C_NextGen_Shortcode_Manager::add('imagebrowser',    array($this, 'render_shortcode'));
            C_NextGen_Shortcode_Manager::add('nggimagebrowser', array($this, 'render_shortcode'));
        }

        add_action('ngg_routes', array($this, 'define_routes'));
    }

    function define_routes($router)
    {
        $slug = '/'.C_NextGen_Settings::get_instance()->router_param_slug;
        $router->rewrite("{*}{$slug}{*}/image/{\\w}", "{1}{$slug}{2}/pid--{3}");
    }

    /**
     * Gets a value from the parameter array, and if not available, uses the default value
     *
     * @param string $name
     * @param mixed $default
     * @param array $params
     * @return mixed
     */
    function _get_param($name, $default, $params)
    {
        return (isset($params[$name])) ? $params[$name] : $default;
    }

    function render_shortcode($params, $inner_content=NULL)
    {
        $params['gallery_ids']  = $this->_get_param('id', NULL, $params);
        $params['source']       = $this->_get_param('source', 'galleries', $params);
        $params['display_type'] = $this->_get_param('display_type', NGG_PRO_IMAGEBROWSER, $params);

        unset($params['id']);

        $renderer = C_Displayed_Gallery_Renderer::get_instance();
        return $renderer->display_images($params, $inner_content);
    }

    function get_type_list()
    {
        return array(
            'A_Nextgen_Pro_Imagebrowser'            => 'adapter.nextgen_pro_imagebrowser.php',
            'A_Nextgen_Pro_Imagebrowser_Controller' => 'adapter.nextgen_pro_imagebrowser_controller.php',
            'A_Nextgen_Pro_Imagebrowser_Form'       => 'adapter.nextgen_pro_imagebrowser_form.php',
            'A_Nextgen_Pro_Imagebrowser_Mapper'     => 'adapter.nextgen_pro_imagebrowser_mapper.php',
            'A_Nextgen_Pro_Imagebrowser_Urls'       => 'adapter.nextgen_pro_imagebrowser_urls.php',
            'C_Nextgen_Pro_Imagebrowser_Installer'  => 'class.nextgen_pro_imagebrowser_installer.php'
        );
    }
}

/**
 * @param $galleryID
 * @param string $template
 * @return string
 */
function nggShowProImageBrowser($galleryID, $template = '')
{
    $renderer = C_Displayed_Gallery_Renderer::get_instance();
    $retval = $renderer->display_images(array(
        'gallery_ids'   =>  array($galleryID),
        'display_type'  =>  NGG_PRO_IMAGEBROWSER,
        'template'      =>  $template
    ));

    return apply_filters('ngg_show_imagebrowser_content', $retval, $galleryID);
}

/**
 * @param $picturelist
 * @param string $template
 * @return string
 */
function nggCreateProImageBrowser($picturelist, $template = '')
{
    $renderer = C_Displayed_Gallery_Renderer::get_instance();
    $image_ids = array();
    foreach ($picturelist as $image) $image_ids[] = $image->pid;
    return $renderer->display_images(array(
        'image_ids'     =>  $image_ids,
        'display_type'  =>  NGG_PRO_IMAGEBROWSER,
        'template'      =>  $template
    ));
}

/**
 * @mixin C_Display_Type_Installer
 */
class C_NextGen_Pro_ImageBrowser_Installer extends C_Gallery_Display_Installer
{
    function install($reset = FALSE)
    {
        $this->install_display_type(
            NGG_PRO_IMAGEBROWSER, array(
                'title'                 => __('NextGEN Pro ImageBrowser', 'nextgen-gallery-pro'),
                'entity_types'          => array('image'),
                'preview_image_relpath' => NGG_PRO_IMAGEBROWSER . '#preview.jpg',
                'hidden_from_ui'        => FALSE,
                'default_source'        => 'galleries',
                'view_order'            => NGG_DISPLAY_PRIORITY_BASE + (NGG_DISPLAY_PRIORITY_STEP * 10) + 15,
                'aliases'               => array(
                    'pro_imagebrowser',
                    'nextgen_pro_imagebrowser'
                )
            )
        );
    }

    function uninstall($hard = FALSE)
    {
        $mapper = C_Display_Type_Mapper::get_instance();
        if (($entity = $mapper->find_by_name(NGG_PRO_IMAGEBROWSER)))
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

new M_NextGen_Pro_ImageBrowser();
