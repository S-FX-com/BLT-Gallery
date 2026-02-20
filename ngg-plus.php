<?php
/*
 * Plugin Name: NextGEN Plus
 * Description: A premium add-on for NextGEN Gallery with beautiful new gallery displays and a fullscreen, responsive Pro Lightbox with social sharing and commenting.
 * Version: 1.13.0
 * Plugin URI: http://www.nextgen-gallery.com
 * Author: Imagely
 * Author URI: https://www.imagely.com
 * License: GPLv3
 */

if (preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF']))
    die('You are not allowed to call this page directly.');


include_once('class.nextgen_pro_settings_installer.php');

class NextGen_Plus
{
    static $minimum_ngg_version = '3.3.20.1';
    static $product_loaded = FALSE;

    // Initialize the plugin
    function __construct()
    {
        // We only load the plugin if we're outside of the activation request, loaded in an iframe by WordPress. Reason
        // being, if WP_DEBUG is enabled, and another Pope-based plugin (such as the photocrati theme or NextGEN
        // Pro/Plus), then PHP will output strict warnings
        if (!$this->is_activating())
        {
            define('NGG_PLUS_PLUGIN_BASENAME', plugin_basename(__FILE__));
            define('NGG_PLUS_MODULE_URL', plugins_url(path_join(basename(dirname(__FILE__)), 'modules')));
            define('NGG_PLUS_PLUGIN_VERSION', '1.13.0');

            $ngg_activated            = class_exists('C_NextGEN_Bootstrap');
            $ngg_modules_initialized  = did_action('load_nextgen_gallery_modules');
            if ((!$ngg_activated && !$ngg_modules_initialized))
                add_action('load_nextgen_gallery_modules', array($this, 'load_product'));
            else
                $this->load_product(NULL, $ngg_activated, $ngg_modules_initialized);
        }

        $this->_register_hooks();
    }

    /**
     * Loads the product providing NextGEN Gallery Pro functionality
     *
     * @param C_Component_Registry $registry
     * @param bool $ngg_activated
     * @param bool $ngg_modules_loaded
     * @return bool
     */
    function load_product($registry = NULL, $ngg_activated = TRUE, $ngg_modules_loaded = FALSE)
    {
        $retval = FALSE;

        if (!self::$product_loaded)
        {
            // version mismatch: do not load
            if (!defined('NGG_PLUGIN_VERSION') || version_compare(NGG_PLUGIN_VERSION, self::$minimum_ngg_version) == -1)
                return;

            // Don't load Plus if Pro or Starter or already active
            if (defined('NGG_PRO_PLUGIN_VERSION') || defined('NGG_STARTER_PLUGIN_VERSION'))
                return;

            // Get the component registry if one wasn't provided
            if (!$registry)
                $registry = C_Component_Registry::get_instance();

            // If NGG is activated and we've missed the action to load our modules, then we need to clean up the state
            if ($ngg_activated && $ngg_modules_loaded)
                C_Component_Registry::$_instance = NULL;

            $dir = dirname(__FILE__);
            $registry->add_module_path($dir, 2, FALSE);
            $registry->load_product('photocrati-nextgen-plus');
            $registry->initialize_all_modules();
        }
        else {
            $retval = self::$product_loaded;
        }

        return $retval;
    }

    function is_activating()
    {
        $retval =  strpos($_SERVER['REQUEST_URI'], 'plugins.php') !== FALSE && isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('activate', 'activate-selected'));
        if (!$retval && strpos($_SERVER['REQUEST_URI'], 'update.php') !== FALSE && isset($_REQUEST['action']) && $_REQUEST['action'] == 'install-plugin' && isset($_REQUEST['plugin']) && strpos($_REQUEST['plugin'], 'nextgen-gallery-plus') === 0)
            $retval = TRUE;
        if (!$retval && strpos($_SERVER['REQUEST_URI'], 'update.php') !== FALSE && isset($_REQUEST['action']) && $_REQUEST['action'] == 'activate-plugin' && isset($_REQUEST['plugin']) && strpos($_REQUEST['plugin'], 'nextgen-gallery-plus') === 0)
            $retval = TRUE;
        if (!$retval && strpos($_SERVER['REQUEST_URI'], 'plugins.php') !== FALSE && isset($_REQUEST['action']) && $_REQUEST['action'] == 'activate-selected' && isset($_REQUEST['checked']) && is_array($_REQUEST['checked']) && in_array('nextgen-gallery-pro/nggallery-plus.php', $_REQUEST['checked']))
            $retval = TRUE;

        return $retval;
    }

    function _register_hooks()
    {
        add_action('activate_' . plugin_basename(__FILE__), array($this, 'activate'));
        add_action('deactivate_' . plugin_basename(__FILE__), array($this, 'deactivate'));
        add_action('plugins_loaded', array($this, 'remove_ngg_third_party_compat_hook'));

	// Show compatibility notices if NGG is missing or too old
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    /**
     * Allow CSS/JS to be minified with NGG 2.1.15
     */
    function remove_ngg_third_party_compat_hook()
    {
        global $wp_filter;
        if (class_exists('M_Third_Party_Compat') && !empty($wp_filter['ngg_non_minified_modules'][10]))
        {
            foreach ($wp_filter['ngg_non_minified_modules'][10] as $id => $filter) {
                if (!strpos($id, 'dont_minify_nextgen_pro_cssjs'))
                    continue;
                $object = $filter['function'][0];
                if (!is_object($object) || get_class($object) != 'M_Third_Party_Compat')
                    continue;
                remove_filter('ngg_non_minified_modules', array($object, 'dont_minify_nextgen_pro_cssjs'), 10);
            }
        }
    }

    static function activate()
    {
        // admin_notices will check for this later
        update_option('photocrati_plus_recently_activated', 'true');
    }

    static function deactivate()
    {
        if (class_exists('C_Photocrati_Installer'))
            C_Photocrati_Installer::uninstall('photocrati-nextgen-plus');
    }

    function admin_notices()
    {
        $nextgen_found = FALSE;
        if (defined('NGG_PLUGIN_VERSION'))
            $nextgen_found = 'NGG_PLUGIN_VERSION';
        if (defined('NEXTGEN_GALLERY_PLUGIN_VERSION'))
            $nextgen_found = 'NEXTGEN_GALLERY_PLUGIN_VERSION';
        $nextgen_version = @constant($nextgen_found);

        if (FALSE == $nextgen_found)
        {
            $message = __('Please install &amp; activate <a href="http://wordpress.org/plugins/nextgen-gallery/" target="_blank">NextGEN Gallery</a> to allow NextGEN Plus to work.', 'nextgen-gallery-pro');
            echo '<div class="updated"><p>' . $message . '</p></div>';
        }
        else if (version_compare($nextgen_version, self::$minimum_ngg_version) == -1) {
            $ngg_pro_version = NGG_PLUS_PLUGIN_VERSION;
            $upgrade_url     = admin_url('/plugin-install.php?tab=plugin-information&plugin=nextgen-gallery&section=changelog&TB_iframe=true&width=640&height=250');
            $message = sprintf(
                __("NextGEN Gallery %s is incompatible with NextGEN Plus %s. Please update <a class='thickbox' href='%s'>NextGEN Gallery</a> to version %s or higher. NextGEN Plus has been deactivated.", 'nextgen-gallery-pro'),
                $nextgen_version,
                $ngg_pro_version,
                $upgrade_url,
                self::$minimum_ngg_version
            );
            echo '<div class="updated"><p>' . $message . '</p></div>';
            deactivate_plugins(NGG_PLUS_PLUGIN_BASENAME);
        }
        elseif (defined('NGG_STARTER_PLUGIN_VERSION')) {
            $message = __('NextGEN Starter is already active and cannot be used at the same time as NextGEN Plus. NextGEN Plus has been deactivated.', 'nextgen-gallery-pro');
            echo '<div class="updated"><p>' . $message . '</p></div>';
            deactivate_plugins(NGG_PLUS_PLUGIN_BASENAME);
        }
        elseif (defined('NGG_PRO_PLUGIN_VERSION')) {
            $message = __('NextGEN Pro is already active and cannot be used at the same time as NextGEN Plus. NextGEN Plus has been deactivated.', 'nextgen-gallery-pro');
            echo '<div class="updated"><p>' . $message . '</p></div>';
            deactivate_plugins(NGG_PLUS_PLUGIN_BASENAME);
        }
        elseif (delete_option('photocrati_plus_recently_activated')) {
            $message = __('To activate the NextGEN Pro Lightbox please go to Gallery > Other Options > Lightbox Effects.', 'nextgen-gallery-pro');
            echo '<div class="updated"><p>' . $message . '</p></div>';
        }
    }
}

new NextGen_Plus();
