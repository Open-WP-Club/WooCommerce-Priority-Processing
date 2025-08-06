<?php

// Only define the class if WC_Settings_Page exists
if (!class_exists('WPP_WooCommerce_Settings') && class_exists('WC_Settings_Page')) {

  class WPP_WooCommerce_Settings extends WC_Settings_Page
  {
    public function __construct()
    {
      $this->id = 'wpp_priority';
      $this->label = __('Priority Processing', 'woo-priority');

      parent::__construct();
    }

    public function get_settings($current_section = '')
    {
      $settings = array();

      if ('' == $current_section) {
        $settings = array(
          array(
            'title' => __('Priority Processing Settings', 'woo-priority'),
            'type'  => 'title',
            'desc'  => __('Configure priority processing and express shipping options for checkout.', 'woo-priority'),
            'id'    => 'wpp_settings'
          ),

          array(
            'title'   => __('Enable Priority Processing', 'woo-priority'),
            'desc'    => __('Enable or disable the priority processing option at checkout', 'woo-priority'),
            'id'      => 'wpp_enabled',
            'default' => 'yes',
            'type'    => 'checkbox'
          ),

          array(
            'title'             => __('Fee Amount', 'woo-priority'),
            'desc'              => __('The additional fee for priority processing', 'woo-priority'),
            'id'                => 'wpp_fee_amount',
            'type'              => 'number',
            'default'           => '5.00',
            'custom_attributes' => array(
              'step' => '0.01',
              'min'  => '0'
            )
          ),

          array(
            'title'   => __('Section Title', 'woo-priority'),
            'desc'    => __('The title shown above the priority processing option', 'woo-priority'),
            'id'      => 'wpp_section_title',
            'type'    => 'text',
            'default' => __('Express Options', 'woo-priority')
          ),

          array(
            'title'   => __('Checkbox Label', 'woo-priority'),
            'desc'    => __('The label shown next to the checkbox', 'woo-priority'),
            'id'      => 'wpp_checkbox_label',
            'type'    => 'text',
            'default' => __('Priority processing + Express shipping', 'woo-priority')
          ),

          array(
            'title'   => __('Description Text', 'woo-priority'),
            'desc'    => __('Additional description shown below the checkbox', 'woo-priority'),
            'id'      => 'wpp_description',
            'type'    => 'textarea',
            'default' => __('Your order will be processed with priority and shipped via express delivery', 'woo-priority'),
            'css'     => 'min-width:300px;'
          ),

          array(
            'title'   => __('Fee Label in Cart', 'woo-priority'),
            'desc'    => __('The label shown in cart and checkout totals', 'woo-priority'),
            'id'      => 'wpp_fee_label',
            'type'    => 'text',
            'default' => __('Priority Processing & Express Shipping', 'woo-priority')
          ),

          array(
            'type' => 'sectionend',
            'id'   => 'wpp_settings'
          )
        );
      }

      return apply_filters('woocommerce_get_settings_' . $this->id, $settings, $current_section);
    }

    public function save()
    {
      $settings = $this->get_settings();
      WC_Admin_Settings::save_fields($settings);
    }
  }
}
