<?php

/**
 * Admin Dashboard Handler
 * Manages the main admin page display and statistics dashboard
 */
class Admin_Dashboard
{
  private $statistics;
  private $settings_handler;

  public function __construct($statistics_instance)
  {
    $this->statistics = $statistics_instance;
    $this->settings_handler = new Admin_Settings();
  }

  /**
   * Display the main admin page
   */
  public function display_page()
  {
    // Get current settings and statistics
    $settings = $this->settings_handler->get_settings();
    $stats = $this->statistics->get_statistics();
    $cache_info = $this->statistics->get_cache_info();

    // Display the complete admin page
    $this->render_page_header();
    $this->render_statistics_section($stats, $cache_info);
    $this->render_settings_section($settings);
    $this->render_page_scripts();
  }

  /**
   * Render page header
   */
  private function render_page_header()
  {
?>
    <div class="wrap wpp-admin-container">
      <h1><?php _e('Priority Processing Settings', 'woo-priority'); ?></h1>

      <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
          <p><strong><?php _e('Settings saved successfully!', 'woo-priority'); ?></strong> <?php _e('Your priority processing options are now active.', 'woo-priority'); ?></p>
        </div>
      <?php endif; ?>
    <?php
  }

  /**
   * Render statistics section
   */
  private function render_statistics_section($stats, $cache_info)
  {
    ?>
      <!-- Statistics Section -->
      <div class="wpp-statistics-section">
        <div class="wpp-statistics-header">
          <h2><?php _e('Priority Processing Statistics', 'woo-priority'); ?></h2>
          <div class="wpp-stats-controls">
            <span class="wpp-cache-info">
              <?php if ($cache_info['is_cached']): ?>
                <small><?php printf(__('Cached for %d hours', 'woo-priority'), $cache_info['cache_duration_hours']); ?></small>
              <?php else: ?>
                <small><?php _e('Live data', 'woo-priority'); ?></small>
              <?php endif; ?>
            </span>
            <button type="button" id="wpp-refresh-stats" class="button button-secondary">
              <span class="dashicons dashicons-update"></span>
              <?php _e('Refresh Stats', 'woo-priority'); ?>
            </button>
          </div>
        </div>

        <div class="wpp-statistics-grid" id="wpp-statistics-container">
          <?php $this->render_statistics_cards($stats); ?>
        </div>

        <div class="wpp-statistics-note">
          <p><strong><?php _e('Note:', 'woo-priority'); ?></strong> <?php _e('Statistics are cached for 24 hours to improve performance. Click "Refresh Stats" to get the latest data.', 'woo-priority'); ?></p>
        </div>
      </div>
    <?php
  }

  /**
   * Render individual statistics cards
   */
  private function render_statistics_cards($stats)
  {
    $cards_data = [
      [
        'icon' => '⚡',
        'value' => number_format($stats['total_priority_orders']),
        'label' => __('Total Priority Orders', 'woo-priority'),
        'id' => 'stat-total-orders'
      ],
      [
        'icon' => '💰',
        'value' => wc_price($stats['total_priority_revenue']),
        'label' => __('Total Priority Revenue', 'woo-priority'),
        'id' => 'stat-total-revenue'
      ],
      [
        'icon' => '📈',
        'value' => $stats['priority_percentage'] . '%',
        'label' => __('Priority Rate', 'woo-priority'),
        'id' => 'stat-percentage'
      ],
      [
        'icon' => '💵',
        'value' => wc_price($stats['average_priority_fee']),
        'label' => __('Average Fee', 'woo-priority'),
        'id' => 'stat-avg-fee'
      ],
      [
        'icon' => '📅',
        'value' => number_format($stats['today_priority_orders']),
        'label' => __('Today', 'woo-priority'),
        'id' => 'stat-today'
      ],
      [
        'icon' => '📊',
        'value' => number_format($stats['this_week_priority_orders']),
        'label' => __('This Week', 'woo-priority'),
        'id' => 'stat-this-week'
      ],
      [
        'icon' => '📆',
        'value' => number_format($stats['this_month_priority_orders']),
        'label' => __('This Month', 'woo-priority'),
        'id' => 'stat-this-month'
      ],
      [
        'icon' => '🔄',
        'value' => esc_html(gmdate('H:i', strtotime($stats['last_updated']))),
        'label' => __('Last Updated', 'woo-priority'),
        'id' => 'stat-last-updated'
      ]
    ];

    foreach ($cards_data as $card) {
      echo '<div class="wpp-stat-card">';
      echo '<div class="wpp-stat-icon">' . $card['icon'] . '</div>';
      echo '<div class="wpp-stat-content">';
      echo '<div class="wpp-stat-value" id="' . $card['id'] . '">' . $card['value'] . '</div>';
      echo '<div class="wpp-stat-label">' . $card['label'] . '</div>';
      echo '</div>';
      echo '</div>';
    }
  }

  /**
   * Render settings section
   */
  private function render_settings_section($settings)
  {
    ?>
      <div class="wpp-settings-grid">
        <!-- Main Settings Panel -->
        <div class="wpp-settings-card">
          <h2><?php _e('Configuration', 'woo-priority'); ?></h2>

          <form method="post" action="options.php" id="wpp-settings-form">
            <?php settings_fields('wpp_settings'); ?>

            <?php
            // Render settings sections
            $this->settings_handler->render_basic_settings($settings);
            $this->settings_handler->render_permissions_settings($settings);
            $this->settings_handler->render_display_settings($settings);
            ?>

            <?php submit_button(__('Save Changes', 'woo-priority'), 'primary', 'submit', false); ?>
          </form>
        </div>

        <!-- Preview Panel -->
        <?php $this->settings_handler->render_preview_panel($settings); ?>
      </div>
    </div>
  <?php
  }

  /**
   * Render JavaScript for the admin page
   */
  private function render_page_scripts()
  {
  ?>
    <script>
      jQuery(document).ready(function($) {
        // Live preview updates
        function updatePreview() {
          var sectionTitle = $('#wpp_section_title').val() || 'Express Options';
          var checkboxLabel = $('#wpp_checkbox_label').val() || 'Priority processing + Express shipping';
          var description = $('#wpp_description').val() || '';
          var feeAmount = $('#wpp_fee_amount').val() || '0.00';
          var enabled = $('#wpp_enabled').is(':checked');

          $('#preview-section-title').text(sectionTitle);
          $('#preview-checkbox-label').text(checkboxLabel);
          $('#preview-fee-amount').text(feeAmount);

          if (description) {
            $('#preview-description').text(description).show();
          } else {
            $('#preview-description').hide();
          }

          // Update preview styling based on enabled state
          var $preview = $('#checkout-preview');
          var $checkbox = $preview.find('input[type="checkbox"]');

          if (enabled) {
            $preview.css('opacity', '1');
            $checkbox.css('opacity', '1');
          } else {
            $preview.css('opacity', '0.6');
            $checkbox.css('opacity', '0.5');
          }

          // Update status indicator
          var $status = $('.wpp-status');
          if (enabled) {
            $status.removeClass('wpp-status-disabled').addClass('wpp-status-enabled').text('Active');
          } else {
            $status.removeClass('wpp-status-enabled').addClass('wpp-status-disabled').text('Inactive');
          }

          // Update stats - only show numbers
          $('.wpp-stat-value').first().text(feeAmount);
          $('.wpp-stat-value').last().text(enabled ? '✅' : '❌');
        }

        // Permission settings handlers
        function updatePermissionSummary() {
          var selectedRoles = [];
          var allowGuests = $('#wpp_allow_guests').is(':checked');

          // Always include shop managers
          selectedRoles.push('Shop Managers');

          // Get selected user roles
          $('input[name="wpp_allowed_user_roles[]"]:checked').each(function() {
            var roleLabel = $(this).parent().find('strong').text();
            selectedRoles.push(roleLabel);
          });

          // Add guests if allowed
          if (allowGuests) {
            selectedRoles.push('Guest Users');
          }

          // Update summary display
          var $summary = $('#wpp-permission-summary');
          if (selectedRoles.length > 1) { // More than just shop managers
            var summaryHtml = '<div style="background: #e7f3ff; border: 1px solid #b3d9ff; padding: 10px; border-radius: 4px;">' +
              '<strong>Priority processing is available to:</strong>' +
              '<ul style="margin: 5px 0 0 20px;">';

            selectedRoles.forEach(function(role) {
              summaryHtml += '<li>' + role + '</li>';
            });

            summaryHtml += '</ul></div>';
            $summary.html(summaryHtml);
          } else {
            $summary.html('<div style="background: #fff2e5; border: 1px solid #ffcc99; padding: 10px; border-radius: 4px;">' +
              '<strong>Only Shop Managers currently have access to priority processing</strong></div>');
          }

          // Update guest status text
          $('.wpp-guest-status').text(allowGuests ? 'Allowed' : 'Denied');

          // Update preview permission summary
          $('#preview-permission-summary').html(
            selectedRoles.length > 1 ?
            '<strong>Available to:</strong> ' + selectedRoles.join(', ') :
            '<strong>No access granted</strong>'
          );
        }

        // Bind permission change events
        $('input[name="wpp_allowed_user_roles[]"], #wpp_allow_guests').on('change', function() {
          updatePermissionSummary();
          updatePreview();
        });

        // Bind events for live preview
        $('#wpp_section_title, #wpp_checkbox_label, #wpp_description, #wpp_fee_amount').on('input', updatePreview);
        $('#wpp_enabled').on('change', updatePreview);

        // Initialize on page load
        setTimeout(function() {
          updatePreview();
          updatePermissionSummary();
        }, 100);

        // Statistics refresh handler
        $('#wpp-refresh-stats').on('click', function(e) {
          e.preventDefault();

          var $button = $(this);
          var originalText = $button.html();

          // Show loading state
          $button.prop('disabled', true);
          $button.html('<span class="dashicons dashicons-update spin"></span> ' + wpp_admin_ajax.refreshing_text);

          // Add loading class to statistics container
          $('#wpp-statistics-container').addClass('wpp-loading');

          $.ajax({
            url: wpp_admin_ajax.ajax_url,
            type: 'POST',
            data: {
              action: 'wpp_refresh_stats',
              nonce: wpp_admin_ajax.nonce
            },
            success: function(response) {
              if (response.success && response.data.formatted) {
                // Update all statistics with formatted values
                $('#stat-total-orders').text(response.data.formatted.total_priority_orders);
                $('#stat-total-revenue').html(response.data.formatted.total_priority_revenue);
                $('#stat-percentage').text(response.data.formatted.priority_percentage);
                $('#stat-avg-fee').html(response.data.formatted.average_priority_fee);
                $('#stat-today').text(response.data.formatted.today_priority_orders);
                $('#stat-this-week').text(response.data.formatted.this_week_priority_orders);
                $('#stat-this-month').text(response.data.formatted.this_month_priority_orders);

                // Update last updated time
                var now = new Date();
                var timeString = now.getHours().toString().padStart(2, '0') + ':' +
                  now.getMinutes().toString().padStart(2, '0');
                $('#stat-last-updated').text(timeString);

                // Show success feedback
                $('#wpp-statistics-container').addClass('wpp-updated');
                setTimeout(function() {
                  $('#wpp-statistics-container').removeClass('wpp-updated');
                }, 2000);

                if (response.data.message) {
                  console.log('WPP: ' + response.data.message);
                }
              } else {
                alert('Failed to refresh statistics. Please try again.');
              }
            },
            error: function(xhr, status, error) {
              console.error('Statistics refresh error:', error);
              alert('Error refreshing statistics: ' + error);
            },
            complete: function() {
              // Reset button state
              $button.prop('disabled', false);
              $button.html(originalText);
              $('#wpp-statistics-container').removeClass('wpp-loading');
            }
          });
        });
      });
    </script>
<?php
  }
}
