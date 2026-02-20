<?php

/* { Module: photocrati-nextgen_pro_nonce } */

class M_NextGen_Pro_Nonce extends C_Base_Module
{
    function define($id='pope-module', $name='Pope Module', $description='', $version='', $uri='', $author='', $author_uri='', $context=FALSE)
    {
        parent::define(
            'photocrati-nextgen_pro_nonce',
            'Nonce',
            'Provides true NONCE (Numbers Only Once) values',
            '0.1',
            'https://www.imagely.com',
            'Imagely',
            'https://www.imagely.com'
        );
    }

    function _register_hooks()
    {
        add_action( 'wp_simple_nonce_cleanup', array($this, 'cleanup' ));
        add_action( 'wp', array($this, 'schedule_garbage_collection' ));
    }

    function cleanup()
    {
        if (!function_exists('wp_snonce_cleanup')) WPSimpleNonce::clearNonces();
    }

    function schedule_garbage_collection() {
        if (!function_exists('wp_simple_nonce_register_garbage_collection')) {
            if ( ! wp_next_scheduled( 'wp_simple_nonce_cleanup' ) ) {
                wp_schedule_event( time(), 'daily', 'wp_simple_nonce_cleanup' );
            }
        }
    }

    public static function include_library()
    {
        $file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'class.wpsimplenonce.php';
        if (!class_exists('WPSimpleNonce') && is_file($file))
        {
            require_once ($file);
        }
    }

    function get_type_list()
    {
      return array('WPSimpleNonce' => 'class.wpsimplenonce.php');
    }
}

new M_NextGen_Pro_Nonce();
M_NextGen_Pro_Nonce::include_library();
