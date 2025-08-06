jQuery(document).ready(function($) {
    console.log('WPP: Block-compatible frontend script loaded');
    
    var checkboxInjected = false;
    var checkboxState = sessionStorage.getItem('wpp_priority_state') === 'true' || false;
    var isUpdating = false;
    
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
                            <span style="color: #dc3545; font-weight: normal;">(+$${config.fee_amount})</span>
                        </strong>
                        ${config.description ? `<small style="color: #6c757d; display: block; margin-top: 6px; line-height: 1.4;">${config.description}</small>` : ''}
                    </span>
                </label>
            </div>
        `;
    }
    
    // Function to inject custom fee line in order summary
    function injectFeeLineInOrderSummary() {
        // Remove existing custom fee line
        $('#wpp-custom-fee-line').remove();
        
        if (!checkboxState) {
            return; // Don't show fee line if not checked
        }
        
        // Try to find the subtotal row to insert after it
        var $subtotalRow = $('.wc-block-components-totals-item').filter(function() {
            return $(this).find('.wc-block-components-totals-item__label').text().toLowerCase().includes('subtotal');
        });
        
        if ($subtotalRow.length > 0) {
            var feeHTML = `
                <div id="wpp-custom-fee-line" class="wc-block-components-totals-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #e1e4e8;">
                    <span class="wc-block-components-totals-item__label" style="font-weight: 500;">
                        Priority Processing & Express Shipping
                    </span>
                    <span class="wc-block-components-totals-item__value" style="font-weight: 600; color: #d63638;">
                        ${config.fee_amount} лв.
                    </span>
                </div>
            `;
            
            $subtotalRow.after(feeHTML);
            console.log('WPP: Custom fee line injected after subtotal');
        } else {
            // Fallback - try to inject after any totals wrapper
            var $totalsWrapper = $('.wc-block-components-totals-wrapper, .wc-block-components-totals');
            if ($totalsWrapper.length > 0) {
                var feeHTML = `
                    <div id="wpp-custom-fee-line" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; margin: 10px 0;">
                        <span style="font-weight: 500;">Priority Processing & Express Shipping</span>
                        <span style="font-weight: 600; color: #d63638;">${config.fee_amount} лв.</span>
                    </div>
                `;
                
                $totalsWrapper.prepend(feeHTML);
                console.log('WPP: Custom fee line injected in totals wrapper');
            }
        }
    }
    
    // Function to inject checkbox into block checkout
    function injectCheckbox() {
        // Don't inject if already exists or if we're in the middle of an update
        if ($('#wpp-priority-block-section').length > 0 || isUpdating) {
            return;
        }
        
        console.log('WPP: Attempting to inject checkbox');
        
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
                bindCheckboxEvents();
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
                bindCheckboxEvents();
            }
        }
        
        if (!injected) {
            console.log('WPP: Could not find suitable injection point');
        } else {
            // Also inject the fee line if checkbox should be checked
            setTimeout(injectFeeLineInOrderSummary, 200);
        }
    }
    
    // Function to bind checkbox events
    function bindCheckboxEvents() {
        // Remove any existing handlers to prevent duplicates
        $(document).off('change.wpp', '.wpp-priority-checkbox');
        
        // Bind with namespaced event to avoid conflicts
        $(document).on('change.wpp', '.wpp-priority-checkbox', function(e) {
            // Prevent event bubbling
            e.stopPropagation();
            
            // Prevent multiple simultaneous updates
            if (isUpdating) {
                console.log('WPP: Update already in progress, ignoring click');
                return false;
            }
            
            var $checkbox = $(this);
            var isChecked = $checkbox.is(':checked');
            
            console.log('WPP: Block checkbox changed to:', isChecked);
            
            // Set updating flag
            isUpdating = true;
            checkboxState = isChecked;
            
            // Store state in sessionStorage
            sessionStorage.setItem('wpp_priority_state', isChecked.toString());
            
            // Immediately update the custom fee line display
            if (isChecked) {
                injectFeeLineInOrderSummary();
            } else {
                $('#wpp-custom-fee-line').remove();
            }
            
            // Disable all checkboxes during update
            $('.wpp-priority-checkbox').prop('disabled', true);
            
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
                        console.log('WPP: Priority updated successfully');
                        
                        // Update the checkbox state to match what we sent
                        $('.wpp-priority-checkbox').prop('checked', isChecked);
                        
                        // Update the fee line display
                        setTimeout(function() {
                            if (isChecked) {
                                injectFeeLineInOrderSummary();
                            } else {
                                $('#wpp-custom-fee-line').remove();
                            }
                        }, 500);
                        
                    } else {
                        console.error('WPP: Block AJAX failed:', response);
                        // Revert checkbox state
                        $('.wpp-priority-checkbox').prop('checked', !isChecked);
                        checkboxState = !isChecked;
                        sessionStorage.setItem('wpp_priority_state', (!isChecked).toString());
                        
                        // Revert fee line
                        if (!isChecked) {
                            injectFeeLineInOrderSummary();
                        } else {
                            $('#wpp-custom-fee-line').remove();
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('WPP: Block AJAX error:', status, error);
                    // Revert checkbox state
                    $('.wpp-priority-checkbox').prop('checked', !isChecked);
                    checkboxState = !isChecked;
                    sessionStorage.setItem('wpp_priority_state', (!isChecked).toString());
                    
                    // Revert fee line
                    if (!isChecked) {
                        injectFeeLineInOrderSummary();
                    } else {
                        $('#wpp-custom-fee-line').remove();
                    }
                },
                complete: function() {
                    // Re-enable checkbox and clear updating flag
                    $('.wpp-priority-checkbox').prop('disabled', false);
                    isUpdating = false;
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
            
            if ($blockCheckout.length > 0 && !$('#wpp-priority-block-section').length) {
                console.log('WPP: Block checkout detected, injecting checkbox');
                clearInterval(checkInterval);
                setTimeout(injectCheckbox, 500);
            } else if (attempts >= maxAttempts) {
                console.log('WPP: Block checkout not found after', maxAttempts, 'attempts');
                clearInterval(checkInterval);
            }
        }, 500);
    }
    
    // Start monitoring
    waitForBlocks();
    
    // Also try immediate injection in case blocks are already loaded
    setTimeout(function() {
        if (!$('#wpp-priority-block-section').length) {
            injectCheckbox();
        }
    }, 1000);
    
    // Simplified DOM observer - only watch for major changes
    var observer = new MutationObserver(function(mutations) {
        var shouldReinject = false;
        var shouldUpdateFeeLine = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                // Only re-inject if our checkbox is completely missing and checkout blocks exist
                if (!$('#wpp-priority-block-section').length && 
                    $('.wp-block-woocommerce-checkout').length > 0 && 
                    !isUpdating) {
                    shouldReinject = true;
                }
                
                // Check if we need to update the fee line
                if (checkboxState && !$('#wpp-custom-fee-line').length && 
                    $('.wc-block-components-totals-item').length > 0) {
                    shouldUpdateFeeLine = true;
                }
            }
        });
        
        if (shouldReinject) {
            console.log('WPP: Checkbox missing, re-injecting');
            setTimeout(injectCheckbox, 200);
        } else if (shouldUpdateFeeLine) {
            console.log('WPP: Fee line missing, re-injecting');
            setTimeout(injectFeeLineInOrderSummary, 200);
        }
    });
    
    // Start observing with less aggressive settings
    observer.observe(document.body, {
        childList: true,
        subtree: false // Only watch direct children, not deep changes
    });
    
    // Clear session storage when leaving checkout
    $(window).on('beforeunload', function() {
        if (window.location.href.indexOf('order-received') === -1) {
            sessionStorage.removeItem('wpp_priority_state');
        }
    });
    
    console.log('WPP: Block checkout monitoring initialized');
});