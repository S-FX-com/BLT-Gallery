<?php

if (!class_exists('AC_NextGen_Pro_Settings_Installer')) {
    abstract class AC_NextGen_Pro_Settings_Installer
    {
        protected $defaults = array();
        protected $current  = array();
        protected $groups   = array();

        public function set_defaults($defaults = array())
        {
            $this->defaults = $defaults;
        }

        public function load_current_settings()
        {
            $settings = C_NextGen_Settings::get_instance();
            foreach ($this->defaults as $key => $unused) {
                $this->current[$key] = $settings->get($key);
            }
        }

        public function set_current_settings()
        {
            $settings = C_NextGen_Settings::get_instance();
            foreach ($this->current as $key => $val) {
                $settings->set($key, $val);
            }
        }

        public function get_groups()
        {
            return $this->groups;
        }

        public function set_groups($groups = array())
        {
            $this->groups = $groups;
        }

        function reset()
        {
            $this->uninstall(TRUE);
            $settings = C_NextGen_Settings::get_instance();
            foreach ($this->defaults as $key => $val) {
                $settings->set($key, $val);
            }
            $settings->save();
        }

        public function install()
        {
            $settings = C_NextGen_Settings::get_instance();
            foreach ($this->defaults as $key => $val) {
                $settings->set_default_value($key, $val);
            }
        }

        public function uninstall($hard = FALSE)
        {
            if ($hard)
            {
                $settings = C_NextGen_Settings::get_instance();
                foreach ($this->defaults as $key => $val) {
                    $settings->delete($key);
                }
                $settings->save();
            }
        }
    }
}