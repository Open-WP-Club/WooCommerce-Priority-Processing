# Shipping Cost Addition Approach

## Goal
Add priority processing fee directly to shipping rates, not as separate cart fee.

## Implementation Strategy

### 1. Remove Separate Fee System
The `Frontend_Fees` class previously added a separate fee line item. This has been modified to NOT add a cart fee.

### 2. Modify Shipping Rates Instead
Hook into `woocommerce_package_rates` to modify the actual shipping rate costs.

```php
add_filter('woocommerce_package_rates', [$this, 'add_priority_to_shipping_rates'], 100, 2);
```

**Priority 100** ensures:
- Runs AFTER shipping methods calculate their base rates
- Runs BEFORE final display to customer
- Allows all shipping plugins to finish their calculations first

### 3. Rate Modification Logic

For each shipping package:
1. Check if priority processing is active in session
2. Get the priority fee amount
3. Loop through all available shipping rates
4. Add fee to each rate's cost
5. Optionally update rate label to show priority included

Example:
```
Original: "Flat Rate - $10.00"
Modified: "Flat Rate - $15.00" (with $5 priority fee)
```

### 4. Session Management

**Critical:** Session must be set BEFORE shipping rates are calculated.

```php
add_action('woocommerce_checkout_update_order_review', [$this, 'ensure_priority_session_before_shipping'], 5);
```

**Priority 5** ensures this runs very early in the checkout update process.

### 5. Benefits of This Approach

✅ **Single line item** - Customer sees one shipping cost
✅ **Works with ALL plugins** - No plugin-specific code needed
✅ **Clean UI** - No separate "Priority Fee" line
✅ **Accurate totals** - Shipping cost is shipping cost
✅ **Better UX** - Less confusing for customers
✅ **Plugin compatibility** - Works with any shipping method

### 6. Display Options

**Option A: Silent Addition** (Current)
- Fee added to shipping cost
- Customer sees higher shipping price
- Label unchanged: "Flat Rate - $15.00"

**Option B: Label Indication** (Available)
- Fee added to shipping cost
- Label shows priority: "Flat Rate (Priority) - $15.00"
- Makes it clear what they're paying for

**Option C: Breakdown in Label**
- Label shows breakdown: "Flat Rate ($10) + Priority ($5) = $15.00"
- Most transparent but verbose

Currently using **Option A** for cleaner appearance.

## Technical Details

### Hook Execution Order
```
1. User checks priority checkbox
2. AJAX updates session (priority_processing = true)
3. Checkout triggers update_checkout event
4. woocommerce_checkout_update_order_review hook fires
   └─> ensure_priority_session_before_shipping() sets session
5. WooCommerce builds shipping packages
6. Shipping methods calculate base rates
7. woocommerce_package_rates hook fires
   └─> add_priority_to_shipping_rates() adds fee
8. Modified rates displayed to customer
9. Cart totals recalculated with new shipping cost
```

### Code Flow
```
frontend-blocks.js
  └─> AJAX call to wpp_update_priority
       └─> ajax.php: update_priority_status()
            └─> WC()->session->set('priority_processing', true)
            └─> WC()->shipping()->calculate_shipping()
                 └─> shipping.php: add_priority_to_shipping_rates()
                      └─> foreach rate: rate->cost += fee_amount
            └─> WC()->cart->calculate_totals()
            └─> Return success + fragments
```

## Compatibility Notes

### Works With:
- WooCommerce built-in shipping
- Flat Rate
- Free Shipping (cost becomes = fee_amount)
- Local Pickup
- Third-party plugins (FedEx, UPS, USPS, etc.)
- Table Rate shipping
- Zone-based shipping
- Class-based shipping

### Edge Cases Handled:
- **Free Shipping**: Cost becomes priority fee amount
- **$0 methods**: Priority fee still added
- **Multiple packages**: Each package's rates get fee added
- **No shipping methods**: No rates to modify, no error

### Not Compatible With:
- Plugins that cache shipping rates client-side
  - *Solution: Clear cache on priority change*
- Plugins that override woocommerce_package_rates
  - *Solution: Use higher priority number (>100)*

## Testing Scenarios

1. **Single shipping method**
   - Base: $10 → With Priority: $15 ✓

2. **Multiple shipping methods**
   - Standard ($5) → $10
   - Express ($15) → $20
   - All methods get fee ✓

3. **Free shipping**
   - Base: $0 → With Priority: $5 ✓

4. **No shipping methods available**
   - No rates to modify
   - No errors thrown ✓

5. **Toggle priority on/off**
   - Check: +$5 to shipping
   - Uncheck: -$5 from shipping ✓

6. **Block checkout**
   - React component updates
   - Shipping rates refresh
   - Fee applied correctly ✓
