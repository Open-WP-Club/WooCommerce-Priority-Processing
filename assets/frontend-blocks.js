jQuery(document).ready(function($) {
    var isUpdating = false;
    var lastKnownState = null;
    var updateAttempts = 0;
    var maxRetries = 3;
    
    // Get current checkbox state
    function getCurrentCheckboxState() {
        var $checkbox = $('.wpp-priority-checkbox:first');
        return $checkbox.length > 0 ? $checkbox.is(':checked') : false;
    }
    
    // Sync all checkboxes to a specific state
    function setAllCheckboxes(state) {
        $('.wpp-priority-checkbox').prop('checked', state);
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
    }
    
    // Unblock the checkout form
    function unblockCheckout() {
        if ($('form.checkout').length) {
            $('form.checkout').unblock();
        }
        $('.wpp-priority-checkbox').prop('disabled', false);
        isUpdating = false;
    }
    
    // Force checkout update by triggering WooCommerce's update mechanism
    function triggerCheckoutUpdate() {
        // Method 1: Trigger the standard WooCommerce checkout update
        $('body').trigger('update_checkout');
        
        // Method 2: If that doesn't work, trigger input changes
        setTimeout(function() {
            $('form.checkout').find('input, select').first().trigger('change');
        }, 100);
    }
    
    // Show user-friendly error message
    function showErrorMessage(message, isTemporary) {
        var $errorDiv = $('#wpp-error-message');
        if ($errorDiv.length === 0) {
            $errorDiv = $('<div id="wpp-error-message" style="background: #dc3545; color: white; padding: 10px; margin: 10px 0; border-radius: 4px; display: none;"></div>');
            $('.wpp-priority-row').after($errorDiv);
        }
        
        $errorDiv.html('<strong>Priority Processing Error:</strong> ' + message).fadeIn();
        
        if (isTemporary) {
            setTimeout(function() {
                $errorDiv.fadeOut();
            }, 5000);
        }
    }
    
    // Hide error message
    function hideErrorMessage() {
        $('#wpp-error-message').fadeOut();
    }
    
    // Handle rollback with error recovery
    function rollbackState(originalState, errorMessage) {
        lastKnownState = originalState;
        setAllCheckboxes(originalState);
        showErrorMessage(errorMessage || 'Unable to update priority processing. Please try again.', true);
    }
    
    // Handle checkbox state changes with enhanced error recovery
    $(document).on('change', '.wpp-priority-checkbox', function(e) {
        // Prevent multiple simultaneous updates
        if (isUpdating) {
            e.preventDefault();
            return false;
        }
        
        var $clickedCheckbox = $(this);
        var newState = $clickedCheckbox.is(':checked');
        
        // Store the original state for rollback
        var originalState = lastKnownState;
        lastKnownState = newState;
        
        // Reset attempt counter for new interactions
        updateAttempts = 0;
        
        // Hide any previous error messages
        hideErrorMessage();
        
        // Immediately sync all checkboxes
        setAllCheckboxes(newState);
        
        // Block the checkout
        blockCheckout();
        
        // Attempt the update
        attemptUpdate(newState, originalState);
    });
    
    // Attempt update with retry logic
    function attemptUpdate(newState, originalState) {
        updateAttempts++;
        
        // Prepare AJAX data
        var ajaxData = {
            action: 'wpp_update_priority',
            priority: newState ? '1' : '0',
            nonce: wpp_ajax.nonce
        };
        
        // Send AJAX request
        $.ajax({
            type: 'POST',
            url: wpp_ajax.ajax_url,
            data: ajaxData,
            timeout: 15000, // Increased timeout
            success: function(response) {
                if (response.success) {
                    // Update fragments if provided
                    if (response.data && response.data.fragments) {
                        $.each(response.data.fragments, function(selector, html) {
                            $(selector).replaceWith(html);
                        });
                    }
                    
                    // Force a complete checkout refresh
                    triggerCheckoutUpdate();
                    
                    // Reset attempts counter
                    updateAttempts = 0;
                    hideErrorMessage();
                    
                } else {
                    handleUpdateError(response.data || 'Server returned an error', newState, originalState);
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = 'Connection error';
                
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please check your connection.';
                } else if (xhr.status === 0) {
                    errorMessage = 'No connection. Please check your internet.';
                } else if (xhr.status >= 500) {
                    errorMessage = 'Server error. Please try again later.';
                } else {
                    errorMessage = 'Connection error: ' + error;
                }
                
                handleUpdateError(errorMessage, newState, originalState);
            },
            complete: function() {
                // Always unblock the checkout after a delay
                setTimeout(function() {
                    unblockCheckout();
                }, 500);
            }
        });
    }
    
    // Handle update errors with retry logic
    function handleUpdateError(errorMessage, newState, originalState) {
        if (updateAttempts < maxRetries) {
            // Retry after a short delay
            setTimeout(function() {
                showErrorMessage('Retrying... (Attempt ' + updateAttempts + ' of ' + maxRetries + ')', true);
                attemptUpdate(newState, originalState);
            }, 1000 * updateAttempts); // Exponential backoff
        } else {
            // Max retries reached, rollback
            rollbackState(originalState, errorMessage + ' (Max retries reached)');
        }
    }
    
    // Handle WooCommerce checkout updates
    $(document.body).on('updated_checkout', function() {
        // Wait a bit for DOM to settle, then sync checkboxes
        setTimeout(function() {
            if (!isUpdating) {
                var currentState = getCurrentCheckboxState();
                
                if (lastKnownState !== null && currentState !== lastKnownState) {
                    // State mismatch detected, restore to last known state
                    setAllCheckboxes(lastKnownState);
                } else {
                    // Update last known state to current state
                    lastKnownState = currentState;
                }
            }
        }, 100);
    });
    
    // Initialize state tracking with validation
    setTimeout(function() {
        lastKnownState = getCurrentCheckboxState();
        
        // Validate that all checkboxes are in sync initially
        var $checkboxes = $('.wpp-priority-checkbox');
        if ($checkboxes.length > 1) {
            var checkedCount = $checkboxes.filter(':checked').length;
            if (checkedCount > 0 && checkedCount < $checkboxes.length) {
                // Inconsistent state on load, sync to first checkbox
                var firstState = $checkboxes.first().is(':checked');
                setAllCheckboxes(firstState);
                lastKnownState = firstState;
            }
        }
    }, 500);
    
    // Monitor for checkbox inconsistencies with error correction
    setInterval(function() {
        if (!isUpdating) {
            var $checkboxes = $('.wpp-priority-checkbox');
            if ($checkboxes.length > 0) {
                var checkedCount = $checkboxes.filter(':checked').length;
                var totalCount = $checkboxes.length;
                
                // Check for partial selection (inconsistency)
                if (checkedCount > 0 && checkedCount < totalCount) {
                    // Inconsistency detected, sync to majority state
                    var shouldCheck = checkedCount > (totalCount / 2);
                    setAllCheckboxes(shouldCheck);
                    lastKnownState = shouldCheck;
                }
            }
        }
    }, 3000);
    
    // Handle page visibility changes to re-sync state
    $(document).on('visibilitychange', function() {
        if (!document.hidden && !isUpdating) {
            setTimeout(function() {
                var currentState = getCurrentCheckboxState();
                if (lastKnownState !== null && currentState !== lastKnownState) {
                    setAllCheckboxes(lastKnownState);
                }
            }, 500);
        }
    });
    
    // Emergency state reset function (can be called from console if needed)
    window.wppResetState = function() {
        isUpdating = false;
        lastKnownState = null;
        $('.wpp-priority-checkbox').prop('disabled', false);
        unblockCheckout();
        hideErrorMessage();
        
        // Re-initialize
        setTimeout(function() {
            lastKnownState = getCurrentCheckboxState();
        }, 100);
        
        return 'WPP state reset complete';
    };
});