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
          // Update fragments - this smoothly updates totals/shipping without page reload
          if (response.data && response.data.fragments) {
            $.each(response.data.fragments, function(key, value) {
              var $target = $(key);
              if ($target.length) {
                $target.replaceWith(value);
              }
            });
          }

          // Notify WooCommerce and other scripts that checkout was updated
          // This is a notification event, NOT a trigger for full refresh
          $(document.body).trigger('updated_checkout', [response.data]);
        } else {
          var errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
          showErrorMessage(errorMsg);
        }
      },
      error: function(jqXHR, textStatus, errorThrown) {
        showErrorMessage('An error occurred. Please try again.');
      }
    });
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