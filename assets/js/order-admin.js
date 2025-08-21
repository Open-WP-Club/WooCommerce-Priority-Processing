/**
 * WooCommerce Priority Processing
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
        orderId: orderId
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
      $('#wpp-loading').show();
      $button.prop('disabled', true);
      
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
            // Reload page to show updated order
            console.log('WPP Order Admin: Reloading page');
            window.location.reload();
          } else {
            hideLoadingState($button);
            var errorMessage = response.data || 'Unknown error occurred';
            console.error('WPP Order Admin: Server error', errorMessage);
            alert(wpp_order_admin.error_title + ' ' + errorMessage);
          }
        },
        error: function(xhr, status, error) {
          hideLoadingState($button);
          console.error('WPP Order Admin: AJAX Error', {
            status: status,
            error: error
          });
          alert(wpp_order_admin.connection_error);
        }
      });
    });
    
    function hideLoadingState($button) {
      $('#wpp-loading').hide();
      $button.prop('disabled', false);
    }
    
    console.log('WPP Order Admin: Script initialization complete');
  });