/**
 * Priority Processing - Frontend Block Checkout Handler
 * Handles real-time AJAX updates for priority processing checkbox
 */
(function($) {
  'use strict';

  // Wait for DOM to be ready
  $(document).ready(function() {
    initPriorityProcessing();
  });

  /**
   * Initialize priority processing functionality
   */
  function initPriorityProcessing() {
    // Handle checkbox change
    $(document).on('change', '#priority_processing, input[name="priority_processing"]', function() {
      handlePriorityChange($(this).is(':checked'));
    });

    // Listen for WooCommerce checkout updates
    $(document.body).on('updated_checkout', function() {
      // Re-bind events after checkout updates
      bindCheckboxEvents();
    });
  }

  /**
   * Bind checkbox events
   */
  function bindCheckboxEvents() {
    $('input[name="priority_processing"]').off('change').on('change', function() {
      handlePriorityChange($(this).is(':checked'));
    });
  }

  /**
   * Handle priority processing change
   */
  function handlePriorityChange(isChecked) {
    // Show loading indicator
    showLoadingIndicator();

    // Send AJAX request
    $.ajax({
      url: wppData.ajax_url,
      type: 'POST',
      data: {
        action: 'wpp_update_priority',
        nonce: wppData.nonce,
        priority_enabled: isChecked
      },
      success: function(response) {
        if (response.success) {
          // Update fragments if available (for classic checkout)
          if (response.data.fragments) {
            $.each(response.data.fragments, function(key, value) {
              $(key).replaceWith(value);
            });
          }

          // Trigger checkout update AFTER a small delay to prevent race conditions
          // This allows the session to be fully updated before shipping recalculates
          setTimeout(function() {
            $(document.body).trigger('update_checkout');
          }, 100);
        } else {
          console.error('Priority update failed:', response.data.message);
          showErrorMessage(response.data.message);
        }
      },
      error: function(xhr, status, error) {
        console.error('AJAX error:', error);
        showErrorMessage('An error occurred. Please try again.');
      },
      complete: function() {
        // Hide loading indicator after a brief delay to ensure update completes
        setTimeout(function() {
          hideLoadingIndicator();
        }, 150);
      }
    });
  }

  /**
   * Show loading indicator
   */
  function showLoadingIndicator() {
    // Block the checkout form
    $('.woocommerce-checkout').block({
      message: null,
      overlayCSS: {
        background: '#fff',
        opacity: 0.6
      }
    });
  }

  /**
   * Hide loading indicator
   */
  function hideLoadingIndicator() {
    $('.woocommerce-checkout').unblock();
  }

  /**
   * Show error message
   */
  function showErrorMessage(message) {
    // Remove any existing error messages
    $('.wpp-error-message').remove();

    // Add error message
    var errorHtml = '<div class="woocommerce-error wpp-error-message">' + message + '</div>';
    $('.woocommerce-checkout').prepend(errorHtml);

    // Scroll to error
    $('html, body').animate({
      scrollTop: $('.wpp-error-message').offset().top - 100
    }, 500);

    // Auto-remove after 5 seconds
    setTimeout(function() {
      $('.wpp-error-message').fadeOut(400, function() {
        $(this).remove();
      });
    }, 5000);
  }

})(jQuery);