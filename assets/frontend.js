jQuery(document).ready(function($) {
    // Simple checkbox state sync
    function syncCheckboxes() {
        var $checkboxes = $('.wpp-priority-checkbox');
        
        if ($checkboxes.length > 1) {
            // If multiple checkboxes exist, sync them
            var firstChecked = $checkboxes.first().is(':checked');
            $checkboxes.prop('checked', firstChecked);
        }
    }
    
    // Sync on page load
    syncCheckboxes();
    
    // Re-sync after checkout updates
    $(document.body).on('updated_checkout', function() {
        setTimeout(syncCheckboxes, 100);
    });
    
    // Handle checkbox changes
    $(document).on('change', '.wpp-priority-checkbox', function() {
        var $checkbox = $(this);
        var isChecked = $checkbox.is(':checked') ? '1' : '0';
        
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
        
        // Send AJAX request
        $.ajax({
            type: 'POST',
            url: wpp_ajax.ajax_url,
            data: ajaxData,
            success: function(response) {
                if (response.success && response.data.fragments) {
                    // Update checkout fragments
                    $.each(response.data.fragments, function(key, value) {
                        $(key).replaceWith(value);
                    });
                    $(document.body).trigger('updated_checkout');
                } else {
                    // Revert checkbox on failure
                    $('.wpp-priority-checkbox').prop('checked', !$checkbox.is(':checked'));
                }
            },
            error: function(xhr, status, error) {
                // Revert checkbox on error
                $('.wpp-priority-checkbox').prop('checked', !$checkbox.is(':checked'));
            },
            complete: function() {
                // Always re-enable checkboxes and unblock
                $('.wpp-priority-checkbox').prop('disabled', false);
                if ($('form.checkout').length) {
                    $('form.checkout').unblock();
                }
            }
        });
    });
    
    // Monitor checkbox state periodically
    setInterval(function() {
        var $checkboxes = $('.wpp-priority-checkbox');
        if ($checkboxes.length > 1) {
            var firstState = $checkboxes.first().is(':checked');
            var allSynced = $checkboxes.filter(':checked').length === (firstState ? $checkboxes.length : 0);
            
            if (!allSynced) {
                syncCheckboxes();
            }
        }
    }, 3000);
});