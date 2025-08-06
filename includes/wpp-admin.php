<?php

class WPP_Admin
{
  public function __construct()
  {
    add_action('admin_menu', [$this, 'add_admin_menu']);
    add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
  }

  public function add_admin_menu()
  {
    add_submenu_page(
      'woocommerce',
      __('Priority Processing', 'woo-priority'),
      __('Priority Processing', 'woo-priority'),
      'manage_woocommerce',
      'woo-priority-processing',
      [$this, 'admin_page']
    );
  }

  public function admin_page()
  {
?>
    <div class="wrap">
      <h1><?php _e('Priority Processing Settings', 'woo-priority'); ?></h1>

      <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
          <p><?php _e('Settings saved successfully!', 'woo-priority'); ?></p>
        </div>
      <?php endif; ?>

      <form method="post" action="options.php">
        <?php settings_fields('wpp_settings'); ?>

        <table class="form-table">
          <tr>
            <th scope="row">
              <label for="wpp_enabled"><?php _e('Enable Priority Processing', 'woo-priority'); ?></label>
            </th>
            <td>
              <input type="checkbox" id="wpp_enabled" name="wpp_enabled" value="1" <?php checked(get_option('wpp_enabled'), '1'); ?> />
              <p class="description"><?php _e('Enable or disable the priority processing option at checkout', 'woo-priority'); ?></p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="wpp_fee_amount"><?php _e('Fee Amount', 'woo-priority'); ?></label>
            </th>
            <td>
              <input type="number" step="0.01" min="0" id="wpp_fee_amount" name="wpp_fee_amount"
                value="<?php echo esc_attr(get_option('wpp_fee_amount', '5.00')); ?>" />
              <span><?php echo get_woocommerce_currency_symbol(); ?></span>
              <p class="description"><?php _e('The additional fee for priority processing', 'woo-priority'); ?></p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="wpp_checkbox_label"><?php _e('Checkbox Label', 'woo-priority'); ?></label>
            </th>
            <td>
              <input type="text" id="wpp_checkbox_label" name="wpp_checkbox_label"
                value="<?php echo esc_attr(get_option('wpp_checkbox_label')); ?>"
                class="regular-text" />
              <p class="description"><?php _e('The label shown next to the checkbox', 'woo-priority'); ?></p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="wpp_description"><?php _e('Description Text', 'woo-priority'); ?></label>
            </th>
            <td>
              <textarea id="wpp_description" name="wpp_description" rows="3" cols="50" class="large-text"><?php
                                                                                                          echo esc_textarea(get_option('wpp_description'));
                                                                                                          ?></textarea>
              <p class="description"><?php _e('Additional description shown below the checkbox', 'woo-priority'); ?></p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="wpp_fee_label"><?php _e('Fee Label in Cart', 'woo-priority'); ?></label>
            </th>
            <td>
              <input type="text" id="wpp_fee_label" name="wpp_fee_label"
                value="<?php echo esc_attr(get_option('wpp_fee_label')); ?>"
                class="regular-text" />
              <p class="description"><?php _e('The label shown in cart and checkout totals', 'woo-priority'); ?></p>
            </td>
          </tr>
        </table>

        <?php submit_button(); ?>
      </form>
    </div>
<?php
  }

  public function admin_scripts($hook)
  {
    if ($hook === 'woocommerce_page_woo-priority-processing') {
      wp_enqueue_style('wpp-admin', WPP_PLUGIN_URL . 'assets/admin.css', [], WPP_VERSION);
    }
  }
}
