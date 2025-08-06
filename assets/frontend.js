jQuery(document).ready(function($) {
    console.log('WPP: Frontend script loaded');
    
    // Initialize checkbox state on page load
    function initializeCheckboxState() {
        var $checkboxes = $('.wpp-priority-checkbox');
        if ($checkboxes.length > 0) {
            console.log('WPP: Initializing checkbox state');
            // Ensure all checkboxes have the same state
            var firstCheckboxState = $checkboxes.first().is(':checked');
            $checkboxes.prop('checked', firstCheckboxState);
            console.log('WPP: Checkbox initialized to:', firstCheckboxState);
        }
    }
    
    // Initialize on page load
    initializeCheckboxState();
    
    // Re-initialize after checkout updates
    $(document.body).on('updated_checkout', function() {
        console.log('WPP: Checkout updated, reinitializing');
        setTimeout(initializeCheckboxState, 100); // Small delay to ensure DOM is updated
    });
    
    // Handle priority checkbox change
    $(document).on('change', '.wpp-priority-checkbox', function() {
        console.log('WPP: Checkbox changed');
        
        var $checkbox = $(this);
        var isChecked = $checkbox.is(':checked') ? '1' : '0';
        console.log('WPP: Checkbox state:', isChecked);
        
        // Sync all checkboxes
        $('.wpp-priority-checkbox').prop('checked', $checkbox.is(':checked'));
        
        // Disable checkbox during update
        $('.wpp-priority-checkbox').prop('disabled', true);
        
        // Block checkout UI
        if ($('form.checkout').length) {
            $('form.checkout').block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        }
        
        console.log('WPP: Sending AJAX request...');
        
        $.ajax({
            type: 'POST',
            url: wpp_ajax.ajax_url,
            data: {
                action: 'wpp_update_priority',
                priority: isChecked,
                nonce: wpp_ajax.nonce
            },
            success: function(response) {
                console.log('WPP: AJAX success:', response);
                
                if (response.success) {
                    if (response.data.fragments) {
                        // Update fragments manually
                        $.each(response.data.fragments, function(key, value) {
                            if ($(key).length) {
                                $(key).replaceWith(value);
                            }
                        });
                        console.log('WPP: Fragments updated');
                    }
                    
                    // Trigger body update for other plugins
                    $(document.body).trigger('updated_checkout');
                } else {
                    console.error('WPP: AJAX request failed:', response.data);
                    alert('Failed to update priority processing: ' + (response.data || 'Unknown error'));
                    // Revert checkbox state
                    $('.wpp-priority-checkbox').prop('checked', !$checkbox.is(':checked'));
                }
                
                // Re-enable checkboxes
                $('.wpp-priority-checkbox').prop('disabled', false);
            },
            error: function(xhr, status, error) {
                console.error('WPP: AJAX error:', status, error);
                console.error('WPP: Response:', xhr.responseText);
                
                // Revert checkbox state
                $('.wpp-priority-checkbox').prop('checked', !$checkbox.is(':checked'));
                $('.wpp-priority-checkbox').prop('disabled', false);
                
                alert('Failed to update priority processing. Please try again.');
            },
            complete: function() {
                // Always unblock
                if ($('form.checkout').length) {
                    $('form.checkout').unblock();
                }
            }
        });
    });
    
    // Ensure checkboxes are enabled after checkout updates
    $(document.body).on('updated_checkout', function() {
        console.log('WPP: Checkout updated');
        if ($('form.checkout').length) {
            $('form.checkout').unblock();
        }
        $('.wpp-priority-checkbox').prop('disabled', false);
    });
    
    // Clear priority on page unload (when navigating away)
    $(window).on('beforeunload', function() {
        if (window.location.href.indexOf('order-received') > -1) {
            // Don't clear if going to thank you page
            return;
        }
        
        // Clear priority session when leaving checkout
        $.ajax({
            type: 'POST',
            url: wpp_ajax.ajax_url,
            data: {
                action: 'wpp_update_priority',
                priority: '0',
                nonce: wpp_ajax.nonce
            },
            async: false
        });
    });
});