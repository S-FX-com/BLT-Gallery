<?php

/**
 * @property C_Form $object
 * @mixin C_Form
 */
class A_Licensing_API_Key_Form extends Mixin
{
    function get_model()
    {
        return C_Settings_Model::get_instance();
    }

    function get_title()
    {
        /** @var M_Licensing $licensing */
        $licensing = C_Component_Registry::get_instance()->get_module('imagely-licensing');
        $current = $licensing->get_current_product();

        switch ($current) {
            case 'photocrati-nextgen-pro':
                return __('Pro license key', 'nextgen-gallery-pro');
            case 'photocrati-nextgen-plus':
                return __('Plus license key', 'nextgen-gallery-pro');
            case 'photocrati-nextgen-starter':
                return __('Starter license key', 'nextgen-gallery-pro');
        }
    }

    function _get_field_names()
    {
        return array(
            'nextgen_pro_license_key',
        );
    }

    function enqueue_static_resources()
    {
        wp_enqueue_style(
            'nextgen_pro_api_key_form',
            $this->object->get_static_url('imagely-licensing#admin_form.css')
        );
    }

    function _render_nextgen_pro_license_key_field($settings)
    {
        /** @var M_Licensing $licensing */
        $licensing = C_Component_Registry::get_instance()->get_module('imagely-licensing');
        $model = new stdClass;
        $model->name = 'imagely_license_key';
        $field = $this->object->_render_text_field(
            $model,
            'imagely_license_key',
            __('Licensing key', 'nextgen-gallery-pro'),
            $licensing->get_license($licensing->get_current_product()),
            __('Used to validate your permissions to download future updates and ecommerce functions.', 'nextgen-gallery-pro')
        );

        return $field;
    }

    function save_action($options)
    {
        if (!empty($options))
        {
            $settings = C_NextGen_Settings::get_instance();
            $registry = C_Component_Registry::get_instance();

            /** @var M_Licensing $licensing */
            $licensing = $registry->get_module('imagely-licensing');

            $current_product = $licensing->get_current_product();
            $license = $licensing->get_license($current_product);

            // The actual setting is kept in a separate WP option; the entry in $settings is just used to determine if
            // we need to call set_license() and clear the update check transient.
            if ($options['imagely_license_key'] !== $license)
            {
                // Set the license as both for this product and as the 'default' license
                $licensing->set_license($options['imagely_license_key'], NULL);
                $licensing->set_license($options['imagely_license_key'], $current_product);
                delete_transient('nextgen_gallery_pro_license_check');
                M_Licensing::is_valid_license();

                $settings->imagely_license_key = $options['imagely_license_key'];
                $settings->save();
            }
        }
    }
}