<?php

/**
 * Responsible for Pro, Plus, and Starter updates and licensing.
 */
class M_Licensing extends C_Base_Module
{
    public $_api_url = 'https://members.photocrati.com/api/';

    private static $_license_check_cache = NULL;
    private static $update_information   = NULL;

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
            'imagely-licensing',
            'Imagely Updates and Licenses',
            "Provides automatic updates and license verification",
            '3.10.0',
            'https://www.imagely.com/wordpress-gallery-plugin/nextgen-pro/',
            'Imagely',
            'https://www.imagely.com'
        );

        C_Photocrati_Installer::add_handler($this->module_id, 'C_Imagely_Licensing_Settings_Installer');
    }

    public function _register_hooks()
    {
        add_action('admin_init', [$this, 'register_forms']);

        // Inform the autoupdater where this product's license.php is
        add_filter('photocrati_license_path_list', [$this, 'get_product_license_path'], 10, 3);

        // Allow automatic updating of Pro from members.photocrati.com using WordPress' upgrader
        add_filter('plugins_api', [$this, 'get_upgrader_plugin_info'], 20, 3);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'set_upgrade_info_transient']);
        add_action('upgrader_process_complete', [$this, 'upgrader_process_complete'], 10, 2);
    }

    public function register_forms()
    {
        $display = apply_filters('ngg_pro_display_license_form', TRUE);
        if ($display && (!is_multisite() || is_super_admin()))
        {
            C_Component_Registry::get_instance()->add_adapter('I_Form', 'A_Licensing_API_Key_Form', 'imagely_license_key');
            C_Form_Manager::get_instance()->add_form(NGG_OTHER_OPTIONS_SLUG, 'imagely_license_key');
        }
    }

    /**
     * @return string|void
     */
    public function get_current_product()
    {
        if (defined('NGG_PRO_PLUGIN_VERSION'))
           return 'photocrati-nextgen-pro';
        elseif (defined('NGG_PLUS_PLUGIN_VERSION'))
            return 'photocrati-nextgen-plus';
        elseif (defined('NGG_STARTER_PLUGIN_VERSION'))
            return 'photocrati-nextgen-starter';
    }

    /**
     * @return string|void
     */
    public function get_current_plugin_filename()
    {
        if (defined('NGG_PRO_PLUGIN_VERSION'))
            return 'nextgen-gallery-pro/nggallery-pro.php';
        elseif (defined('NGG_PLUS_PLUGIN_VERSION'))
            return 'nextgen-gallery-plus/ngg-plus.php';
        elseif (defined('NGG_STARTER_PLUGIN_VERSION'))
            return 'nextgen-gallery-starter/ngg-starter.php';
    }

    /**
     * @return string|void
     */
    public function get_current_plugin_version()
    {
        if (defined('NGG_PRO_PLUGIN_VERSION'))
            return NGG_PRO_PLUGIN_VERSION;
        elseif (defined('NGG_PLUS_PLUGIN_VERSION'))
            return NGG_PLUS_PLUGIN_VERSION;
        elseif (defined('NGG_STARTER_PLUGIN_VERSION'))
            return NGG_STARTER_PLUGIN_VERSION;
    }

    /**
     * Gets the API url
     *
     * @return string
     */
    public function _get_api_url()
    {
        return $this->_api_url;
    }

    /**
     * Returns license key, retrieval from multiple sources.
     *
     * @param string $product
     */
    function get_license($product = NULL)
    {
        if ($product)
            $license = get_option('photocrati_license_product_' . $product, NULL);
        else
            $license = get_option('photocrati_license_default', NULL);

        // A cached or defined license has been found, return now.
        if (!empty($license))
            return $license;

        // The constant should take priority over the contents of license.php found in distributable zips.
        if (defined('PHOTOCRATI_LICENSE_DEFAULT'))
        {
            $license = PHOTOCRATI_LICENSE_DEFAULT;
        }
        else {
            // Continue searching by looking for a license.php in each POPE product root.
            $license_abspath = $this->get_license_file_abspath($product);
            if (file_exists($license_abspath))
            {
                // The PHP license file should contain the statement like the following:
                // $license = 'license-key';
                include($license_abspath);
            }
        }

        // There just is no license
        if (empty($license) || $license == NULL)
            return '';

        $this->set_license($license, $product);

        return $license;
    }

    /**
     * @param string $product
     * @return string|null
     */
    public function get_license_file_abspath($product)
    {
        $product_list = $this->get_registry()->get_product_list();
        $path_list = array();

        if ($product != NULL)
        {
            if (array_search($product, $product_list) !== FALSE)
                $path_list[] = $this->get_registry()->get_module_dir($product);
        }
        else {
            foreach ($product_list as $product) {
                $path_list[] = $this->get_registry()->get_module_dir($product);
            }
        }

        $path_list = apply_filters('photocrati_license_path_list', $path_list, $product_list, $product);

        foreach ($path_list as $path) {
            $path = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $path);
            $path = rtrim($path, DIRECTORY_SEPARATOR);
            $path .= '/license.php';

            if (!file_exists($path))
                $path = realpath($path);

            if (!file_exists($path))
                continue;
            else
                return $path;
        }

        return NULL;
    }

    function set_license($license, $product = NULL)
    {
        if ($product != NULL)
            return update_option('photocrati_license_product_' . $product, $license, FALSE);
        else
            return update_option('photocrati_license_default', $license, FALSE);
    }

    /**
     * Returns a product_id -> product_version associative array of all the loaded products
     */
    function get_product_list()
    {
        $product_list = $this->get_registry()->get_product_list();
        $version_list = array();

        foreach ($product_list as $product_id) {
            $product = $this->get_registry()->get_product($product_id);
            $version_list[$product_id] = $product->module_version;
        }

        return $version_list;
    }

    /**
     * Returns a module_id -> module_version associative array of all the loaded modules
     */
    function get_module_list()
    {
        $module_list = $this->get_registry()->get_module_list();
        $version_list = array();

        foreach ($module_list as $module_id)
        {
            $module = $this->get_registry()->get_module($module_id);

            $version_list[$module_id] = $module->module_version;
        }

        return $version_list;
    }

    function api_request($url, $action, $parameter_list = NULL)
    {
        $url = $url . '?post_back=1&api_act=' . $action;
        $http_args = [];

        if (!isset($parameter_list['product-list']))
        {
            $product_list = $this->get_product_list();
            $parameter_list['product-list'] = $product_list;
        }

        if (!isset($parameter_list['module-list']))
        {
            $module_list = $this->get_module_list();
            $parameter_list['module-list'] = $module_list;
        }

        if (!isset($parameter_list['license-key']))
        {
            $license_key = array();
            $default_key = $this->get_license();
            $product_list = $parameter_list['product-list'];

            foreach ($product_list as $product_id => $product_version)
            {
                $product_key = $this->get_license($product_id);

                if ($product_key != null && $product_key != $default_key)
                {
                    $license_key[$product_id] = $product_key;
                }
            }

            $license_key['default'] = $default_key;
            $parameter_list['license-key'] = $license_key;
        }

        if (!isset($parameter_list['authority-site']))
        {
            $authority_site = admin_url();
            $parameter_list['authority-site'] = $authority_site;
        }

        if (isset($parameter_list['http-timeout']))
        {
            $http_args['timeout'] = $parameter_list['http-timeout'];

            unset($parameter_list['http-timeout']);
        }

        if (isset($parameter_list['http-stream']))
        {
            $http_args['stream'] = $parameter_list['http-stream'];

            unset($parameter_list['http-stream']);
        }

        if (isset($parameter_list['http-filename']))
        {
            $http_args['filename'] = $parameter_list['http-filename'];

            unset($parameter_list['http-filename']);
        }

        $http_args['body']      = $parameter_list;
        $http_args['sslverify'] = FALSE;
        $return = wp_remote_post($url, $http_args);

        if ($return != NULL && !is_wp_error($return))
        {
            if (isset($http_args['filename']))
            {
                return TRUE; // TODO: REMOVE THIS. Also: determine why this TODO was originally added.
            }
            else {
                $return = wp_remote_retrieve_body($return);
                $return = json_decode($return, TRUE);

                return $return;
            }
        }

        return FALSE;
    }

    /**
     * @param FALSE $return_status
     * @return bool|mixed|string
     */
    static function is_valid_license($return_status = FALSE)
    {
        $transient_key = 'nextgen_gallery_pro_license_check';
        $cache         = defined('NGG_PRO_CACHE_LICENSE_CHECKS') ? NGG_PRO_CACHE_LICENSE_CHECKS : TRUE;
        $ttl           = defined('NGG_PRO_CACHE_LICENSE_TTL')    ? NGG_PRO_CACHE_LICENSE_TTL    : 86400; // 24 hours.

        if (self::$_license_check_cache)
            $status = self::$_license_check_cache;
        else
            $status = $cache ? get_transient($transient_key) : FALSE;

        if (!$status)
        {
            try {
                $registry = C_Component_Registry::get_instance();

                /** @var M_Licensing $auto_update */
                $auto_update = $registry->get_module('imagely-licensing');

                $product = $auto_update->get_current_product();

                $status = $auto_update->api_request(
                    $auto_update->_get_api_url(),
                    'cklic',
                    array(
                        'product-list'  => array(
                            $product => $registry->get_product($product)->module_version
                        ),
                    )
                );
            }
            catch (Exception $ex) {
                $status = 'ERROR';
            }

            // Prevent multiple transient reads, or if caching is disabled, from making potentially hundreds
            // of license checks for a single page request
            self::$_license_check_cache = $status;

            if ($cache)
                set_transient($transient_key, $status, $ttl);
        }

        // NOTE: because above we make sure we only request a license check for the photocrati-nextgen-pro product, a response of
        // PARTIAL makes no sense and really just indicates that the license is completely invalid, i.e. probably a Nil license
        // so in this specific case it is safe to consider PARTIAL the same as INVALID
        if ($return_status)
            return $status;
        else
            return !in_array(
                $status,
                [
                    'ERROR',
                    'EXPIRED',
                    'INVALID',
                    'PARTIAL'
                ]
            );
    }

    /**
     * Fetches the info.json from imagely.com to determine if updates are available and display information about what is available
     *
     * @return stdClass|null
     */
    public function get_update_info()
    {
        // We already know the license isn't valid, don't bother.
        if (!M_Licensing::is_valid_license())
            return self::$update_information;

        // Use the already decoded from JSON object.
        if (NULL !== self::$update_information)
            return self::$update_information;

        // Then check if the transient exists.
        if (FALSE !== $transient = get_transient('ngg_pro_update_check_results'))
        {
            self::$update_information = $this->get_update_info_object($transient);
            return self::$update_information;
        }

        $action     = 'nextgen_gallery_pro_product_update_check';
        $product_id = $this->get_current_product();
        $license    = C_Component_Registry::get_instance()->get_module('imagely-licensing')->get_license($product_id);

        // Transient not found, request data from the server.
        $remote = wp_remote_get(
            'https://members.photocrati.com/wp-admin/admin-ajax.php?action=' . $action . '&product_id=' . $product_id . '&license-key=' . $license,
            [
                'timeout' => 10,
                'headers' => ['Accept' => 'application/json']
            ]
        );

        // Store the result in a transient, and the resulting object as an attribute to this instance.
        if (!is_wp_error($remote) && isset($remote['response']['code']) && $remote['response']['code'] == 200 && !empty($remote['body']))
        {
            set_transient('ngg_pro_update_check_results', $remote['body'], 86400); // 24 hours.
            self::$update_information = $this->get_update_info_object($remote['body']);
            return self::$update_information;
        }

        return NULL;
    }

    /**
     * @param string $rawinfo
     * @return stdClass
     */
    public function get_update_info_object($rawinfo = '')
    {
        // Decoding failed; abort
        if (FALSE === $info = json_decode($rawinfo))
            return NULL;

        $retval = new stdClass();
        $retval->author   = $info->author;
        $retval->banners  = (array)$info->banners;
        $retval->name     = $info->name;
        $retval->package  = $info->download_url;
        $retval->plugin   = $this->get_current_plugin_filename();
        $retval->requires = $info->requires;
        $retval->sections = (array)$info->sections;
        $retval->slug     = 'nggallery-pro';
        $retval->tested   = $info->tested;
        $retval->trunk    = $info->download_url;
        $retval->version  = $info->version;

        $retval->author_profile = $info->author_profile;
        $retval->download_link  = $info->download_url;
        $retval->last_updated   = $info->last_updated;
        $retval->new_version    = $info->version;
        $retval->requires_php   = $info->requires_php;

        return $retval;
    }

    /**
     * Provides update information to WordPress for users examining the coming changes.
     *
     * @param $res
     * @param string $action
     * @param stdClass $args
     * @return false|stdClass
     */
    public function get_upgrader_plugin_info($res, $action, $args)
    {
        if ('plugin_information' !== $action)
            return FALSE;

        if (!in_array($args->slug, ['nggallery-pro', 'ngg-plus', 'ngg-starter']))
            return FALSE;

        return $this->get_update_info() ?: FALSE;
    }

    /**
     * Adds NextGEN Pro to WordPress' list of plugins with available updates, when applicable.
     *
     * @param stdClass $transient
     * @return stdClass
     */
    public function set_upgrade_info_transient($transient)
    {
        if (empty($transient) || !is_object($transient))
            return $transient;

        $info = $this->get_update_info();

        if ($info && version_compare($this->get_current_plugin_version(), $info->version, '<' ))
        {
            if (empty($transient->response))
                $transient->response = [];
            $key = $this->get_current_plugin_filename();
            $transient->response[$key] = $info;
        }

        return $transient;
    }

    /**
     * Removes the information on the upgrade that was just performed.
     *
     * @param $upgrader
     * @param array $options
     */
    public function upgrader_process_complete($upgrader, $options)
    {
        if ($options['action'] === 'update' && $options['type'] === 'plugin')
            delete_transient('ngg_pro_update_check_results');
    }

    function get_product_license_path($path_list, $product_list, $product)
    {
        if ($product == $this->get_current_product())
        {
            $plugin_root = plugin_dir_path(__FILE__);
            if (in_array($plugin_root, $path_list) === FALSE)
                $path_list[] = $plugin_root;
        }

        return $path_list;
    }

    function get_type_list()
    {
        return [
            'A_Licensing_API_Key_Form' => 'adapter.apikey_form.php'
        ];
    }

}

class C_Imagely_Licensing_Settings_Installer extends AC_NextGen_Pro_Settings_Installer
{
    function __construct()
    {
        $this->set_defaults(['imagely_license_key' => '']);
        $this->set_groups(['']);
    }
}

new M_Licensing();
