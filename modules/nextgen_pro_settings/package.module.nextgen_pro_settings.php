<?php
/**
 * Class A_Reset_Form
 * @mixin C_Form
 * @adapts I_Form using "reset" context
 */
class A_NextGen_Pro_Settings_Reset_Form extends Mixin
{
    public function render()
    {
        $retval = $this->call_parent('render');
        // Inject a paragraph, warning the user that this includes all Pro and e-commerce settings
        $warning = esc_html__('Resets all NextGEN and NextGEN Pro settings to default values, including ecommerce.', 'nextgen-gallery-pro');
        if (($index = strrpos($retval, '</td>')) !== FALSE) {
            $beginning = substr($retval, 0, $index);
            $end = substr($retval, $index);
            $retval = "{$beginning}<p><em>{$warning}</em></p>{$end}";
        }
        $retval .= $this->object->render_partial('photocrati-nextgen_pro_settings#reset_form', array('reset_ecommerce_tooltip' => __('Replace NextGen Pro ecommerce options with their default setting', 'nextgen-gallery-pro'), 'reset_ecommerce_label' => __('Reset only ecommerce settings', 'nextgen-gallery-pro'), 'reset_ecommerce_confirmation' => __("Reset all ecommerce options to default settings?\n\nChoose [Cancel] to Stop, [OK] to proceed.", 'nextgen-gallery-pro'), 'reset_ecommerce_value' => __('Reset ecommerce options', 'nextgen-gallery-pro'), 'reset_ecommerce_warning' => __('Resets only ecommerce settings to default values.', 'nextgen-gallery-pro'), 'reset_non_ecommerce_tooltip' => __('Replace all existing options (except ecommerce) with their default setting', 'nextgen-gallery-pro'), 'reset_non_ecommerce_label' => __('Reset non ecommerce settings', 'nextgen-gallery-pro'), 'reset_non_ecommerce_confirmation' => __("Reset all non ecommerce options to default settings?\n\nChoose [Cancel] to Stop, [OK] to proceed.", 'nextgen-gallery-pro'), 'reset_non_ecommerce_value' => __('Reset non-ecommerce options', 'nextgen-gallery-pro'), 'reset_non_ecommerce_warning' => __('Resets all settings except ecommerce to default values.', 'nextgen-gallery-pro')), TRUE);
        return $retval;
    }
    public function redirect()
    {
        header('Location: ' . $_SERVER['REQUEST_URI']);
        throw new E_Clean_Exit();
    }
    public function reset_ecommerce_settings_action()
    {
        C_Photocrati_Transient_Manager::flush();
        $installers = apply_filters('ngg_pro_settings_reset_installers', array());
        foreach ($installers as $installer_classname) {
            $installer = new $installer_classname();
            $actions = $installer->get_groups();
            if (in_array('ecommerce', $actions)) {
                $installer->reset();
            }
        }
        $this->redirect();
    }
    public function reset_non_ecommerce_settings_action()
    {
        C_Photocrati_Transient_Manager::flush();
        $installers = apply_filters('ngg_pro_settings_reset_installers', array());
        $ecomm_installers = array();
        foreach ($installers as $installer_classname) {
            $installer = new $installer_classname();
            $actions = $installer->get_groups();
            if (in_array('ecommerce', $actions)) {
                $ecomm_installers[] = $installer;
            }
        }
        foreach ($ecomm_installers as $installer) {
            $installer->load_current_settings();
        }
        // wipe out all settings
        C_Photocrati_Installer::uninstall('photocrati-nextgen-pro');
        C_Photocrati_Installer::uninstall('photocrati-nextgen');
        $settings = C_NextGen_Settings::get_instance();
        $settings->reset();
        $settings->destroy();
        wp_remote_get(admin_url('plugins.php'), array('timeout' => 180, 'blocking' => true, 'sslverify' => false));
        $settings = C_NextGen_Settings::get_instance();
        $settings->load();
        foreach ($ecomm_installers as $installer) {
            $installer->set_current_settings();
        }
        $settings->save();
        $this->redirect();
    }
    // TODO: this was beyond the initial project scope and never completed
    /* public function reset_all_non_pro_settings_action()
       {
       } */
    // While this is functionally complete it is beyond the scope of NGGPro-270 and so has no button
    // to activate this method in the reset_form.php template
    /*
        public function reset_all_pro_settings_action()
        {
            C_Photocrati_Transient_Manager::flush();
    
            C_Photocrati_Installer::uninstall('photocrati-nextgen-pro', TRUE);
            $installer = new C_NextGen_Pro_Installer;
            $installer->install_display_types();
    
            $this->redirect();
        } */
}