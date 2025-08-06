jQuery(document).ready(function($) {
    console.log('WPP: Block-compatible frontend script loaded');
    
    var checkboxInjected = false;
    var checkboxState = sessionStorage.getItem('wpp_priority_state') === 'true' || false;
    
    // Configuration from PHP
    var config = {
        fee_amount: wpp_ajax.fee_amount || '5.00',
        checkbox_label: wpp_ajax.checkbox_label || 'Priority processing + Express shipping',
        description: wpp_ajax.description || '',
        section_title: wpp_ajax.section_title || 'Express Options'
    };
    
    console.log('WPP: Block checkout config:', config);
    console.log('WPP: Restored checkbox state:', checkboxState);
    
    // Function to create the checkbox HTML
    function createCheckboxHTML() {
        return `
            <div id="wpp-priority-block-section" style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
                <h4 style="margin: 0 0 12px 0; color: #495057; font-size: 16px; font-weight: 600;">
                    ⚡ ${config.section_title}
                </h4>
                <label style="display: flex; align-items: flex-start; cursor: pointer; font-size: 14px; line-height: 1.5;">
                    <input type="checkbox" id="wpp_priority_checkbox_block" class="wpp-priority-checkbox" 
                           name="priority_processing" value="1" ${checkboxState ? 'checked' : ''}
                           style="margin-right: 12px; margin-top: 3px; transform: scale(1.2);" />
                    <span>
                        <strong style="color: #28a745; display: block;">
                            ${config.checkbox_label}
                            <span style="color: #dc3545; font-weight: normal;">(+${config.fee_amount})</span>
                        </strong>
                        ${config.description ? `<small style="color: #6c757d; display: block; margin-top: 6px; line-height: 1.4;">${config.description}</small>` : ''}
                    </span>
                </label>
            </div>
        `;
    }
    
    // Function to inject checkbox into block checkout
    function injectCheckbox() {
        // Remove existing checkbox first
        $('#wpp-priority-block-section').remove();
        checkboxInjected = false;
        
        // Try different selectors to find where to inject the checkbox
        var targetSelectors = [
            '.wp-block-woocommerce-checkout-order-summary-block',
            '.wc-block-components-totals-wrapper',
            '.wc-block-checkout__main',
            '.wc-block-components-checkout-sidebar',
            '.wp-block-woocommerce-checkout'
        ];
        
        var injected = false;
        
        for (var i = 0; i < targetSelectors.length; i++) {
            var $target = $(targetSelectors[i]);
            if ($target.length > 0) {
                console.log('WPP: Injecting checkbox using selector:', targetSelectors[i]);
                $target.before(createCheckboxHTML());
                checkboxInjected = true;
                injected = true;
                break;
            }
        }
        
        if (!injected) {
            // Fallback - inject at the top of the checkout page
            var $checkout = $('.wc-block-checkout, .wp-block-woocommerce-checkout');
            if ($checkout.length > 0) {
                console.log('WPP: Using fallback injection method');
                $checkout.prepend(createCheckboxHTML());
                checkboxInjected = true;
                injected = true;
            }
        }
        
        if (injected) {
            bindCheckboxEvents();
        } else {
            console.log('WPP: Could not find suitable injection point');
        }
    }
    
    // Function to bind checkbox events
    function bindCheckboxEvents() {
        $(document).off('change', '.wpp-priority-checkbox').on('change', '.wpp-priority-checkbox', function() {
            var $checkbox = $(this);
            var isChecked = $checkbox.is(':checked');
            checkboxState = isChecked;
            
            // Store state in sessionStorage to persist across page updates
            sessionStorage.setItem('wpp_priority_state', isChecked.toString());
            
            console.log('WPP: Block checkbox changed to:', isChecked);
            
            // Disable checkbox during update
            $('.wpp-priority-checkbox').prop('disabled', true);
            
            // Update all checkboxes
            $('.wpp-priority-checkbox').prop('checked', isChecked);
            
            // Send AJAX request
            $.ajax({
                type: 'POST',
                url: wpp_ajax.ajax_url,
                data: {
                    action: 'wpp_update_priority',
                    priority: isChecked ? '1' : '0',
                    nonce: wpp_ajax.nonce
                },
                success: function(response) {
                    console.log('WPP: Block AJAX success:', response);
                    
                    if (response.success) {
                        // DON'T refresh the page - let WooCommerce blocks handle the update
                        console.log('WPP: Priority updated successfully, no page refresh needed');
                        
                        // Try to trigger block checkout updates without page refresh
                        if (typeof window.wc !== 'undefined' && window.wc.wcBlocksData) {
                            console.log('WPP: Triggering WooCommerce blocks update');
                            $(document.body).trigger('wc-blocks-checkout-set-checkout-data');
                        }
                        
                        // Trigger custom events that might update totals
                        $(document.body).trigger('wc_update_cart');
                        $(document.body).trigger('updated_wc_div');
                        
                        // Update the displayed fee amount in the checkbox label if needed
                        setTimeout(function() {
                            // Re-inject checkbox to ensure it stays visible and maintains state
                            if (!$('#wpp-priority-block-section').length) {
                                console.log('WPP: Re-injecting checkbox after update');
                                injectCheckbox();
                            }
                        }, 100);
                        
                    } else {
                        console.error('WPP: Block AJAX failed:', response);
                        // Revert checkbox
                        $('.wpp-priority-checkbox').prop('checked', !isChecked);
                        checkboxState = !isChecked;
                        sessionStorage.setItem('wpp_priority_state', (!isChecked).toString());
                    }
                },
                error: function(xhr, status, error) {
                    console.error('WPP: Block AJAX error:', status, error);
                    // Revert checkbox
                    $('.wpp-priority-checkbox').prop('checked', !isChecked);
                    checkboxState = !isChecked;
                    sessionStorage.setItem('wpp_priority_state', (!isChecked).toString());
                },
                complete: function() {
                    // Re-enable checkbox
                    $('.wpp-priority-checkbox').prop('disabled', false);
                }
            });
        });
    }
    
    // Function to monitor for checkout blocks loading
    function waitForBlocks() {
        var attempts = 0;
        var maxAttempts = 20; // Try for 10 seconds
        
        var checkInterval = setInterval(function() {
            attempts++;
            
            // Look for block checkout elements
            var $blockCheckout = $('.wp-block-woocommerce-checkout, .wc-block-checkout');
            
            if ($blockCheckout.length > 0) {
                console.log('WPP: Block checkout detected, injecting checkbox');
                clearInterval(checkInterval);
                setTimeout(injectCheckbox, 500); // Small delay to ensure blocks are fully loaded
            } else if (attempts >= maxAttempts) {
                console.log('WPP: Block checkout not found after', maxAttempts, 'attempts');
                clearInterval(checkInterval);
            }
        }, 500);
    }
    
    // Start monitoring
    waitForBlocks();
    
    // Also try immediate injection in case blocks are already loaded
    setTimeout(injectCheckbox, 1000);
    
    // Re-inject checkbox when DOM changes (but preserve state)
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                // Check if our checkbox is still present
                if (!$('#wpp-priority-block-section').length && $('.wp-block-woocommerce-checkout').length > 0) {
                    console.log('WPP: Checkbox missing after DOM change, re-injecting');
                    setTimeout(injectCheckbox, 200);
                }
            }
        });
    });
    
    // Start observing
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Clear session storage when leaving the page (except to thank you page)
    $(window).on('beforeunload', function() {
        if (window.location.href.indexOf('order-received') === -1) {
            sessionStorage.removeItem('wpp_priority_state');
        }
    });
    
    console.log('WPP: Block checkout monitoring initialized');
});