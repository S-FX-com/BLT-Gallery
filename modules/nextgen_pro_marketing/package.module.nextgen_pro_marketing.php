<?php
/**
 * @property C_NextGen_Admin_Page_Controller|A_NextGEN_StarterPlus_Upgrade_Page_Controller $object
 */
class A_NextGEN_StarterPlus_Upgrade_Page_Controller extends Mixin
{
    function enqueue_backend_resources()
    {
        $this->call_parent('enqueue_backend_resources');
        // This won't be registered if Plus is detected by NGG 3.4
        wp_register_style('ngg_marketing_blocks_style', C_Router::get_instance()->get_static_url('photocrati-marketing#blocks.css'), ['wp-block-library'], NGG_SCRIPT_VERSION);
        wp_enqueue_style('nextgen_starterplus_upgrade_page', $this->get_static_url('photocrati-nextgen_pro_upgrade#style.css'), ['ngg_marketing_blocks_style'], NGG_SCRIPT_VERSION);
    }
    function get_page_title()
    {
        return __('Extensions', 'nggallery');
    }
    function get_required_permission()
    {
        return 'NextGEN Change options';
    }
    function get_i18n_strings()
    {
        $i18n = new stdClass();
        $i18n->page_title = $this->object->get_page_title();
        return $i18n;
    }
    /**
     * @return C_Marketing_Block_Card[]
     */
    public function get_marketing_blocks()
    {
        $controller = C_NextGen_Admin_Page_Controller::get_instance('ngg_pro_upgrade');
        $retval = $controller->get_marketing_blocks();
        $plus_list = ['Ecommerce', 'Proofing', 'Print Fulfillment', 'Auto Tax Calculations', 'Pricelists', 'Coupons', 'Lightroom Plugin'];
        $starter_list = array_merge($plus_list, ['Tiled Gallery', 'Masonry Gallery', 'Film Gallery', 'Blog Style Gallery', 'Sidescroll Gallery', 'Pro Lightbox', 'Social Sharing', 'Image Commenting', 'Image Protection', 'Deep Linking', 'Frontend Search', 'Hover Captions', 'Digital Downloads']);
        $list = [];
        if (defined('NGG_PLUS_PLUGIN_VERSION')) {
            $list = $plus_list;
        } elseif (defined('NGG_STARTER_PLUGIN_VERSION')) {
            $list = $starter_list;
        }
        foreach ($retval as $ndx => $block) {
            if (!in_array($block->title, $list)) {
                unset($retval[$ndx]);
            }
        }
        return $retval;
    }
    function index_action()
    {
        $this->object->enqueue_backend_resources();
        $router = C_Router::get_instance();
        $template = 'photocrati-nextgen_pro_upgrade#upgrade';
        print $this->object->render_view($template, ['i18n' => $this->get_i18n_strings(), 'header_image_url' => $router->get_static_url('photocrati-nextgen_admin#imagely_icon.png'), 'marketing_blocks' => $this->object->get_marketing_blocks()], TRUE);
    }
}
class A_NextGEN_StarterPlus_Upgrade_Page_Setup extends Mixin
{
    function setup()
    {
        // This is apparently The WordPress Way(tm)
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        $this->object->add('ngg_starter_plus_upgrade', array('adapter' => 'A_NextGEN_StarterPlus_Upgrade_Page_Controller', 'parent' => NGGFOLDER));
        return $this->call_parent('setup');
    }
}