jQuery(document).ready(function($) {
    console.log('WPP: Frontend script loaded');
    
    // Simple checkbox state sync
    function syncCheckboxes() {
        var $checkboxes = $('.wpp-priority-checkbox');
        console.log('WPP: Found', $checkboxes.length, 'priority checkboxes');
        
        if ($checkboxes.length > 1) {
            // If multiple checkboxes exist, sync them
            var firstChecked = $checkboxes.first().is(':checked');
            $checkboxes.prop('checked', firstChecked);
            console.log('WPP: Synced multiple checkboxes to:', firstChecked);
        } else if ($checkboxes.length === 1) {
            console.log('WPP: Single checkbox found, state:', $checkboxes.first().is(':checked'));
        }
    }
    
    // Sync on page load
    syncCheckboxes();
    
    // Re-sync after checkout updates
    $(document.body).on('updated_checkout', function() {
        console.log('WPP: Checkout updated, re-syncing checkboxes');
        setTimeout(syncCheckboxes, 100);
    });
    
    // Handle checkbox changes
    $(document).on('change', '.wpp-priority-checkbox', function() {
        var $checkbox = $(this);
        var isChecked = $checkbox.is(':checked') ? '1' : '0';
        
        console.log('WPP: Checkbox changed to:', isChecked);
        console.log('WPP: Checkbox element:', $checkbox[0]);
        
        // Sync all checkboxes immediately
        $('.wpp-priority-checkbox').prop('checked', $checkbox.is(':checked'));
        
        // Disable during update
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
        
        var ajaxData = {
            action: 'wpp_update_priority',
            priority: isChecked,
            nonce: wpp_ajax.nonce
        };
        
        console.log('WPP: Sending AJAX data:', ajaxData);
        
        // Send AJAX request
        $.ajax({
            type: 'POST',
            url: wpp_ajax.ajax_url,
            data: ajaxData,
            success: function(response) {
                console.log('WPP: AJAX response received:', response);
                
                if (response.success && response.data.fragments) {
                    console.log('WPP: Updating fragments:', Object.keys(response.data.fragments));
                    
                    // Update checkout fragments
                    $.each(response.data.fragments, function(key, value) {
                        $(key).replaceWith(value);
                    });
                    $(document.body).trigger('updated_checkout');
                    
                    if (response.data.debug) {
                        console.log('WPP: Debug info:', response.data.debug);
                    }
                } else {
                    console.error('WPP: Failed to update priority:', response);
                    // Revert checkbox on failure
                    $('.wpp-priority-checkbox').prop('checked', !$checkbox.is(':checked'));
                }
            },
            error: function(xhr, status, error) {
                console.error('WPP: AJAX error:', status, error);
                console.error('WPP: Response text:', xhr.responseText);
                // Revert checkbox on error
                $('.wpp-priority-checkbox').prop('checked', !$checkbox.is(':checked'));
            },
            complete: function() {
                console.log('WPP: AJAX request completed');
                // Always re-enable checkboxes and unblock
                $('.wpp-priority-checkbox').prop('disabled', false);
                if ($('form.checkout').length) {
                    $('form.checkout').unblock();
                }
            }
        });
    });
    
    // Debug: Log checkbox state periodically
    setInterval(function() {
        var $checkboxes = $('.wpp-priority-checkbox');
        if ($checkboxes.length > 0) {
            var checkedCount = $checkboxes.filter(':checked').length;
            if (window.lastCheckboxState !== checkedCount) {
                console.log('WPP: Checkbox state changed -', $checkboxes.length, 'total,', checkedCount, 'checked');
                window.lastCheckboxState = checkedCount;
            }
        }
    }, 3000);
});