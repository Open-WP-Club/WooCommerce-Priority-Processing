/**
 * WooCommerce Priority Processing - Order Admin JavaScript
 * Handles admin functionality for individual orders
 */
jQuery(document).ready(function($) {
  console.log('WPP Order Admin: Script loaded');
  
  // Handle add/remove priority buttons
  $('#wpp-add-priority, #wpp-remove-priority').on('click', function(e) {
      e.preventDefault();
      
      var $button = $(this);
      var orderId = $button.data('order-id');
      var isAdding = $button.attr('id') === 'wpp-add-priority';
      var action = isAdding ? 'add' : 'remove';
      
      console.log('WPP Order Admin: Button clicked', {
          action: action,
          orderId: orderId,
          button: $button.attr('id')
      });
      
      // Get confirmation message
      var confirmMsg = isAdding ? 
          wpp_order_admin.confirm_add :
          wpp_order_admin.confirm_remove;
          
      // Confirm action
      if (!confirm(confirmMsg)) {
          console.log('WPP Order Admin: Action cancelled by user');
          return;
      }
      
      // Show loading state
      showLoadingState();
      
      // Prepare AJAX data
      var ajaxData = {
          action: 'wpp_toggle_order_priority',
          order_id: orderId,
          priority_action: action,
          nonce: $('#wpp_order_priority_nonce').val()
      };
      
      console.log('WPP Order Admin: Sending AJAX request', ajaxData);
      
      // Send AJAX request
      $.ajax({
          url: wpp_order_admin.ajax_url,
          type: 'POST',
          data: ajaxData,
          timeout: 30000,
          success: function(response) {
              console.log('WPP Order Admin: AJAX Success', response);
              
              if (response.success) {
                  // Show success message
                  showSuccessMessage(response.data.message);
                  
                  // Reload page after delay to show updated order
                  setTimeout(function() {
                      console.log('WPP Order Admin: Reloading page');
                      window.location.reload();
                  }, wpp_order_admin.success_reload_delay);
                  
              } else {
                  hideLoadingState();
                  var errorMessage = response.data || 'Unknown error occurred';
                  console.error('WPP Order Admin: Server error', errorMessage);
                  alert(wpp_order_admin.error_title + ' ' + errorMessage);
              }
          },
          error: function(xhr, status, error) {
              hideLoadingState();
              console.error('WPP Order Admin: AJAX Error', {
                  status: status,
                  error: error,
                  response: xhr.responseText
              });
              alert(wpp_order_admin.connection_error);
          }
      });
  });
  
  /**
   * Show loading state
   */
  function showLoadingState() {
      console.log('WPP Order Admin: Showing loading state');
      $('#wpp-loading-overlay').fadeIn(200);
      
      // Disable all buttons
      $('#wpp-add-priority, #wpp-remove-priority').prop('disabled', true);
  }
  
  /**
   * Hide loading state
   */
  function hideLoadingState() {
      console.log('WPP Order Admin: Hiding loading state');
      $('#wpp-loading-overlay').fadeOut(200);
      
      // Re-enable buttons
      $('#wpp-add-priority, #wpp-remove-priority').prop('disabled', false);
  }
  
  /**
   * Show success message
   */
  function showSuccessMessage(message) {
      console.log('WPP Order Admin: Showing success message', message);
      
      // Create success message element
      var $successMessage = $('<div class="wpp-success-message">' + 
          '<strong>✅ ' + message + '</strong>' +
          '</div>');
      
      // Insert at top of container
      $('#wpp-order-priority-container').prepend($successMessage);
      
      // Hide loading overlay
      $('#wpp-loading-overlay').fadeOut(200);
      
      // Animate success message
      $successMessage.hide().slideDown(300);
  }
  
  /**
   * Add pulse animation to add button for better visibility
   */
  function addPulseAnimation() {
      var $addButton = $('#wpp-add-priority');
      if ($addButton.length) {
          // Add pulse class after a delay
          setTimeout(function() {
              $addButton.addClass('wpp-pulse');
          }, 1000);
          
          // Remove pulse on hover
          $addButton.on('mouseenter', function() {
              $(this).removeClass('wpp-pulse');
          });
      }
  }
  
  /**
   * Handle keyboard navigation
   */
  function initializeKeyboardNavigation() {
      $('#wpp-add-priority, #wpp-remove-priority').on('keydown', function(e) {
          // Enter or Space key
          if (e.keyCode === 13 || e.keyCode === 32) {
              e.preventDefault();
              $(this).click();
          }
      });
  }
  
  /**
   * Monitor for meta box repositioning
   */
  function monitorMetaBoxPosition() {
      var $metaBox = $('#wpp_order_priority');
      if ($metaBox.length) {
          console.log('WPP Order Admin: Meta box found at position', $metaBox.index());
          
          // Ensure meta box is properly positioned after order notes
          var $orderNotes = $('#woocommerce-order-notes');
          if ($orderNotes.length && $metaBox.index() <= $orderNotes.index()) {
              console.log('WPP Order Admin: Repositioning meta box after order notes');
              $metaBox.insertAfter($orderNotes);
          }
      }
  }
  
  /**
   * Initialize accessibility features
   */
  function initializeAccessibility() {
      // Add ARIA labels
      $('#wpp-add-priority').attr('aria-label', 'Add priority processing to this order');
      $('#wpp-remove-priority').attr('aria-label', 'Remove priority processing from this order');
      
      // Add role attributes
      $('.wpp-status-card').attr('role', 'status');
      $('.wpp-loading-overlay').attr('role', 'dialog').attr('aria-live', 'polite');
      
      // Add loading text for screen readers
      $('#wpp-loading-overlay').append('<span class="screen-reader-text">Processing priority change, please wait</span>');
  }
  
  /**
   * Initialize responsive behavior
   */
  function initializeResponsiveBehavior() {
      // Check if mobile view
      function isMobileView() {
          return $(window).width() <= 768;
      }
      
      // Adjust layout for mobile
      function adjustForMobile() {
          if (isMobileView()) {
              $('.wpp-actions-grid').addClass('wpp-mobile-layout');
          } else {
              $('.wpp-actions-grid').removeClass('wpp-mobile-layout');
          }
      }
      
      // Initial check
      adjustForMobile();
      
      // Check on resize
      $(window).on('resize', debounce(adjustForMobile, 250));
  }
  
  /**
   * Debounce function for performance
   */
  function debounce(func, wait) {
      var timeout;
      return function() {
          var context = this, args = arguments;
          var later = function() {
              timeout = null;
              func.apply(context, args);
          };
          clearTimeout(timeout);
          timeout = setTimeout(later, wait);
      };
  }
  
  /**
   * Initialize error handling
   */
  function initializeErrorHandling() {
      // Global AJAX error handler for our requests
      $(document).ajaxError(function(event, xhr, settings, error) {
          if (settings.data && settings.data.indexOf('wpp_toggle_order_priority') !== -1) {
              console.error('WPP Order Admin: Global AJAX error caught', {
                  event: event,
                  xhr: xhr,
                  settings: settings,
                  error: error
              });
              
              hideLoadingState();
          }
      });
  }
  
  // Initialize all functionality
  console.log('WPP Order Admin: Initializing features');
  addPulseAnimation();
  initializeTooltips();
  initializeKeyboardNavigation();
  monitorMetaBoxPosition();
  initializeAccessibility();
  initializeResponsiveBehavior();
  initializeErrorHandling();
  
  console.log('WPP Order Admin: Initialization complete');
});