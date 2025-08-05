jQuery(document).ready(function($) {
    // Handle priority checkbox change (support multiple selectors including block checkout)
    $(document).on('change', '#wpp_priority_checkbox, #wpp_priority_checkbox_fallback, #wpp_priority_checkbox_block, .wpp-priority-checkbox', function() {
        var isChecked = $(this).is(':checked') ? '1' : '0';
        
        // Sync all checkboxes
        $('.wpp-priority-checkbox, #wpp_priority_checkbox, #wpp_priority_checkbox_fallback, #wpp_priority_checkbox_block').prop('checked', $(this).is(':checked'));
        
        // Block checkout UI during update
        $('.woocommerce-checkout').block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
        
        // Send AJAX request to update session
        $.ajax({
            type: 'POST',
            url: wpp_ajax.ajax_url,
            data: {
                action: 'wpp_update_priority',
                priority: isChecked,
                nonce: wpp_ajax.nonce
            },
            success: function(response) {
                // Trigger checkout update to refresh totals
                $(document.body).trigger('update_checkout');
            },
            error: function() {
                console.error('Failed to update priority processing');
                $('.woocommerce-checkout').unblock();
            }
        });
    });
    
    // Maintain checkbox state after checkout updates
    $(document.body).on('updated_checkout', function() {
        // The checkbox state is maintained server-side via session
        // This ensures it persists through checkout updates
    });
});