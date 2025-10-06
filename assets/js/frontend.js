jQuery(document).ready(function($) {
    console.log('WPP: Classic checkout script loaded');
    
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
        
        // Block checkout form
        if ($('form.checkout').length) {
            $('form.checkout').block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
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
                    // Session updated - trigger WooCommerce's native update
                    console.log('WPP: Session saved as:', response.data.saved_value);
                    console.log('WPP: Triggering checkout update...');
                    $(document.body).trigger('update_checkout');
                } else {
                    console.error('WPP: Server error:', response);
                    
                    // Revert checkbox
                    $('.wpp-priority-checkbox').prop('checked', !isChecked);
                    
                    // Unblock and re-enable
                    if ($('form.checkout').length) {
                        $('form.checkout').unblock();
                    }
                    $('.wpp-priority-checkbox').prop('disabled', false);
                    updateInProgress = false;
                }
            },
            error: function(xhr, status, error) {
                console.error('WPP: AJAX Error:', status, error);
                
                // Revert checkbox
                $('.wpp-priority-checkbox').prop('checked', !isChecked);
                
                // Unblock and re-enable
                if ($('form.checkout').length) {
                    $('form.checkout').unblock();
                }
                $('.wpp-priority-checkbox').prop('disabled', false);
                updateInProgress = false;
            }
        });
    }
    
    /**
     * Handle checkbox change with debouncing
     */
    $(document).on('change', '.wpp-priority-checkbox', function(e) {
        // Clear any pending update
        if (updateTimeout) {
            clearTimeout(updateTimeout);
        }
        
        var $checkbox = $(this);
        var isChecked = $checkbox.is(':checked');
        
        console.log('WPP: Checkbox changed to:', isChecked);
        
        // Sync all checkboxes immediately
        $('.wpp-priority-checkbox').prop('checked', isChecked);
        
        // Debounce the update by 300ms
        updateTimeout = setTimeout(function() {
            updatePriorityProcessing(isChecked);
        }, 300);
    });
    
    /**
     * Handle WooCommerce checkout update completion
     */
    $(document.body).on('updated_checkout', function() {
        console.log('WPP: Checkout updated');
        
        // Check current checkbox state after update
        var $checkbox = $('.wpp-priority-checkbox');
        var isChecked = $checkbox.is(':checked');
        console.log('WPP: After update - Checkbox state:', isChecked);
        
        // Unblock and re-enable
        if ($('form.checkout').length) {
            $('form.checkout').unblock();
        }
        $('.wpp-priority-checkbox').prop('disabled', false);
        updateInProgress = false;
    });
    
    console.log('WPP: Classic checkout script ready');
});