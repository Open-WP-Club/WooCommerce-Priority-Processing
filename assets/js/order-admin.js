/**
 * WooCommerce Priority Processing
 */
jQuery(document).ready(function($) {
  // Handle add/remove priority buttons
  $('#wpp-add-priority, #wpp-remove-priority').on('click', function(e) {
    e.preventDefault();
    
    var $button = $(this);
    var orderId = $button.data('order-id');
    var isAdding = $button.attr('id') === 'wpp-add-priority';
    var action = isAdding ? 'add' : 'remove';
    
    // Show loading state immediately (no confirmation)
    $('#wpp-loading').show();
    $button.prop('disabled', true);
    
    // Prepare AJAX data
    var ajaxData = {
      action: 'wpp_toggle_order_priority',
      order_id: orderId,
      priority_action: action,
      nonce: $('#wpp_order_priority_nonce').val()
    };
    
    // Send AJAX request
    $.ajax({
      url: wpp_order_admin.ajax_url,
      type: 'POST',
      data: ajaxData,
      timeout: 30000,
      success: function(response) {
        if (response.success) {
          // Reload page to show updated order
          window.location.reload();
        } else {
          hideLoadingState($button);
          var errorMessage = response.data || 'Unknown error occurred';
          alert(wpp_order_admin.error_title + ' ' + errorMessage);
        }
      },
      error: function(xhr, status, error) {
        hideLoadingState($button);
        alert(wpp_order_admin.connection_error);
      }
    });
  });
  
  function hideLoadingState($button) {
    $('#wpp-loading').hide();
    $button.prop('disabled', false);
  }
});