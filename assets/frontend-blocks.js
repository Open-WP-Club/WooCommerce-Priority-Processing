jQuery(document).ready(function($) {
    console.log('WPP: Fixed frontend script loaded');
    
    var isUpdating = false;
    var lastKnownState = null;
    
    // Get current checkbox state
    function getCurrentCheckboxState() {
        var $checkbox = $('.wpp-priority-checkbox:first');
        return $checkbox.length > 0 ? $checkbox.is(':checked') : false;
    }
    
    // Sync all checkboxes to a specific state
    function setAllCheckboxes(state) {
        $('.wpp-priority-checkbox').prop('checked', state);
        console.log('WPP: Set all checkboxes to:', state);
    }
    
    // Block the checkout form
    function blockCheckout() {
        if ($('form.checkout').length) {
            $('form.checkout').block({
                message: 'Updating priority processing...',
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        }
        $('.wpp-priority-checkbox').prop('disabled', true);
        isUpdating = true;
        console.log('WPP: Checkout blocked');
    }
    
    // Unblock the checkout form
    function unblockCheckout() {
        if ($('form.checkout').length) {
            $('form.checkout').unblock();
        }
        $('.wpp-priority-checkbox').prop('disabled', false);
        isUpdating = false;
        console.log('WPP: Checkout unblocked');
    }
    
    // Force checkout update by triggering WooCommerce's update mechanism
    function triggerCheckoutUpdate() {
        console.log('WPP: Triggering checkout update');
        
        // Method 1: Trigger the standard WooCommerce checkout update
        $('body').trigger('update_checkout');
        
        // Method 2: If that doesn't work, trigger input changes
        setTimeout(function() {
            $('form.checkout').find('input, select').first().trigger('change');
        }, 100);
    }
    
    // Handle checkbox state changes
    $(document).on('change', '.wpp-priority-checkbox', function(e) {
        // Prevent multiple simultaneous updates
        if (isUpdating) {
            console.log('WPP: Update in progress, preventing change');
            e.preventDefault();
            return false;
        }
        
        var $clickedCheckbox = $(this);
        var newState = $clickedCheckbox.is(':checked');
        var checkboxId = $clickedCheckbox.attr('id') || 'unknown';
        
        console.log('WPP: Checkbox changed:', checkboxId, 'new state:', newState);
        
        // Store the original state for rollback
        var originalState = lastKnownState;
        lastKnownState = newState;
        
        // Immediately sync all checkboxes
        setAllCheckboxes(newState);
        
        // Block the checkout
        blockCheckout();
        
        // Prepare AJAX data
        var ajaxData = {
            action: 'wpp_update_priority',
            priority: newState ? '1' : '0',
            nonce: wpp_ajax.nonce
        };
        
        console.log('WPP: Sending AJAX request:', ajaxData);
        
        // Send AJAX request
        $.ajax({
            type: 'POST',
            url: wpp_ajax.ajax_url,
            data: ajaxData,
            timeout: 10000,
            success: function(response) {
                console.log('WPP: AJAX Success:', response);
                
                if (response.success) {
                    // Log debug information
                    if (response.data && response.data.debug) {
                        console.log('WPP: Server debug:', response.data.debug);
                    }
                    
                    // Update fragments if provided
                    if (response.data && response.data.fragments) {
                        console.log('WPP: Updating fragments');
                        $.each(response.data.fragments, function(selector, html) {
                            $(selector).replaceWith(html);
                        });
                    }
                    
                    // Force a complete checkout refresh
                    triggerCheckoutUpdate();
                    
                    console.log('WPP: Update successful');
                } else {
                    console.error('WPP: Server error:', response);
                    
                    // Rollback checkbox state
                    lastKnownState = originalState;
                    setAllCheckboxes(originalState);
                    
                    alert('Failed to update priority processing. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                console.error('WPP: AJAX Error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                
                // Rollback checkbox state
                lastKnownState = originalState;
                setAllCheckboxes(originalState);
                
                alert('Connection error. Please check your internet connection and try again.');
            },
            complete: function() {
                console.log('WPP: AJAX Complete');
                
                // Always unblock the checkout
                setTimeout(function() {
                    unblockCheckout();
                }, 500);
            }
        });
    });
    
    // Handle WooCommerce checkout updates
    $(document.body).on('updated_checkout', function() {
        console.log('WPP: Checkout updated event received');
        
        // Wait a bit for DOM to settle, then sync checkboxes
        setTimeout(function() {
            if (!isUpdating) {
                var currentState = getCurrentCheckboxState();
                
                if (lastKnownState !== null && currentState !== lastKnownState) {
                    console.log('WPP: State mismatch detected. Restoring to:', lastKnownState);
                    setAllCheckboxes(lastKnownState);
                } else {
                    console.log('WPP: Checkbox state is consistent:', currentState);
                    lastKnownState = currentState;
                }
            }
        }, 100);
    });
    
    // Initialize state tracking
    setTimeout(function() {
        lastKnownState = getCurrentCheckboxState();
        console.log('WPP: Initial state set to:', lastKnownState);
    }, 500);
    
    // Monitor for checkbox inconsistencies
    setInterval(function() {
        if (!isUpdating) {
            var $checkboxes = $('.wpp-priority-checkbox');
            if ($checkboxes.length > 0) {
                var checkedCount = $checkboxes.filter(':checked').length;
                var totalCount = $checkboxes.length;
                
                // Check for partial selection (inconsistency)
                if (checkedCount > 0 && checkedCount < totalCount) {
                    console.warn('WPP: Checkbox inconsistency detected, syncing to first checkbox');
                    var firstState = $checkboxes.first().is(':checked');
                    setAllCheckboxes(firstState);
                    lastKnownState = firstState;
                }
            }
        }
    }, 2000);
    
    console.log('WPP: Fixed frontend script initialization complete');
});