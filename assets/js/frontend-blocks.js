jQuery(document).ready(function($) {
    console.log('WPP: Simplified frontend script loaded');
    
    var updateInProgress = false;
    var updateTimeout = null;
    
    /**
     * Update priority processing via AJAX
     * FIXED: Simplified to only update session and trigger WooCommerce refresh
     */
    function updatePriorityProcessing(isChecked) {
        if (updateInProgress) {
            console.log('WPP: Update already in progress, skipping');
            return;
        }
        
        updateInProgress = true;
        console.log('WPP: Updating priority to:', isChecked);
        
        // Show loading state
        $('.wpp-priority-checkbox').prop('disabled', true);
        
        // Block checkout form with clean loading message
        if ($('form.checkout').length) {
            $('form.checkout').block({
                message: '⚡ Updating priority processing...',
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.7
                },
                css: {
                    padding: '20px',
                    textAlign: 'center',
                    color: '#333',
                    border: 'none',
                    backgroundColor: '#f8f9fa',
                    cursor: 'wait'
                }
            });
        }
        
        // Send AJAX request - only updates session
        $.ajax({
            type: 'POST',
            url: wpp_ajax.ajax_url,
            data: {
                action: 'wpp_update_priority',
                priority: isChecked ? '1' : '0',
                nonce: wpp_ajax.nonce
            },
            timeout: 10000,
            success: function(response) {
                console.log('WPP: AJAX Success:', response);
                
                if (response.success) {
                    // Session updated successfully
                    console.log('WPP: Session saved as:', response.data.saved_value);
                    
                    // Now trigger WooCommerce's native checkout update
                    // This will recalculate fees and refresh the entire checkout
                    console.log('WPP: Triggering WooCommerce checkout update...');
                    $('body').trigger('update_checkout');
                } else {
                    console.error('WPP: Server error:', response);
                    alert('Failed to update priority processing. Please try again.');
                    
                    // Revert checkbox state
                    $('.wpp-priority-checkbox').prop('checked', !isChecked);
                }
            },
            error: function(xhr, status, error) {
                console.error('WPP: AJAX Error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                
                alert('Connection error. Please try again.');
                
                // Revert checkbox state
                $('.wpp-priority-checkbox').prop('checked', !isChecked);
            },
            complete: function() {
                // Reset update flag after a short delay
                // This allows WooCommerce's update_checkout to complete
                setTimeout(function() {
                    updateInProgress = false;
                    $('.wpp-priority-checkbox').prop('disabled', false);
                    
                    // Unblock will happen automatically when WooCommerce finishes updating
                }, 300);
            }
        });
    }
    
    /**
     * Handle checkbox change with debouncing
     * FIXED: Debounced to prevent rapid-fire updates
     */
    $(document).on('change', '.wpp-priority-checkbox', function(e) {
        // Clear any pending update
        if (updateTimeout) {
            clearTimeout(updateTimeout);
        }
        
        var $checkbox = $(this);
        var isChecked = $checkbox.is(':checked');
        
        console.log('WPP: Checkbox changed to:', isChecked);
        
        // Sync all checkboxes immediately for visual consistency
        $('.wpp-priority-checkbox').prop('checked', isChecked);
        
        // Debounce the actual update by 300ms
        updateTimeout = setTimeout(function() {
            updatePriorityProcessing(isChecked);
        }, 300);
    });
    
    /**
     * Handle WooCommerce checkout update completion
     * FIXED: Verify and log checkbox state after update
     */
    $(document.body).on('updated_checkout', function() {
        console.log('WPP: Checkout updated event received');
        
        // Check current checkbox state after WooCommerce refresh
        var $checkbox = $('.wpp-priority-checkbox');
        var isChecked = $checkbox.is(':checked');
        console.log('WPP: After update - Checkbox state:', isChecked, 'Checkbox count:', $checkbox.length);
        
        // Unblock the form
        if ($('form.checkout').length) {
            $('form.checkout').unblock();
        }
        
        // Re-enable checkboxes if somehow still disabled
        $('.wpp-priority-checkbox').prop('disabled', false);
    });
    
    console.log('WPP: Simplified script initialization complete');
});