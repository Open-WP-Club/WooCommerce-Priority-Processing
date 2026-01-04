# WooCommerce Priority Processing - Implementation Notes

## Overview
Plugin that adds priority processing option at checkout, with fee added directly to shipping costs.

## Architecture

### Fee Implementation
The priority fee is added **directly to shipping rates**, not as a separate cart fee.

**Why this approach:**
- More intuitive for customers (single shipping line item)
- Better integration with third-party shipping plugins
- Cleaner checkout appearance
- Shipping total already includes priority handling

### Key Components

1. **Shipping Rate Modification** (`includes/frontend/shipping.php`)
   - Hooks into `woocommerce_package_rates` at priority 100
   - Adds priority fee to each shipping method's cost
   - Preserves original shipping method data
   - Works with ALL shipping plugins

2. **Block Checkout Integration** (`includes/frontend/blocks-integration.php`)
   - Extends WooCommerce Store API
   - Registers priority data in checkout/cart endpoints
   - Handles React-based checkout blocks

3. **AJAX Handler** (`includes/frontend/ajax.php`)
   - Updates priority session
   - Forces shipping recalculation
   - Returns updated cart data
   - Works with both classic and block checkout

4. **Frontend Scripts**
   - `assets/js/frontend-blocks.js` - Classic checkout
   - `assets/js/blocks-checkout.js` - Block checkout (React)

## Critical Hooks & Timing

### Session Update Timing
```
Priority 5: woocommerce_checkout_update_order_review
  └─> Sets session BEFORE packages are built

Priority 100: woocommerce_package_rates
  └─> Modifies shipping rates with priority fee

Default: woocommerce_cart_calculate_fees
  └─> REMOVED - no longer adds separate fee
```

### Why This Timing Matters
- Session must be set BEFORE shipping packages are created
- Rate modification happens AFTER base rates are calculated
- Too early = rates not available yet
- Too late = shipping methods disappear

## Shipping Method Compatibility

### Tested With:
- WooCommerce built-in (Flat Rate, Free Shipping, Local Pickup)
- Third-party carriers (FedEx, UPS, USPS, DHL)
- Table Rate shipping
- Custom shipping methods

### How It Works:
1. Get all available shipping rates for package
2. If priority is active, add fee to each rate's cost
3. Update rate label to indicate priority (optional)
4. Preserve all other rate metadata

## WooCommerce Blocks Support

### Block Checkout Flow:
1. User checks priority checkbox
2. JavaScript calls AJAX endpoint
3. Session updated on server
4. Cart store invalidated
5. Block checkout re-renders with new rates
6. Priority fee now included in shipping costs

### Store API Extensions:
- Namespace: `wpp-priority`
- Endpoints: checkout, cart
- Data: enabled, is_active, fee_amount, labels, etc.

## Known Issues & Solutions

### Issue: Shipping methods disappear when checkbox checked
**Cause:** Late package modification interfered with rate calculation
**Solution:** Removed package modification hook, use rate modification instead

### Issue: Race conditions between AJAX and shipping calculation
**Cause:** update_checkout triggered before session was saved
**Solution:** Added 100ms delay before triggering update

### Issue: Block checkout doesn't update
**Cause:** Cart store not invalidated after session change
**Solution:** Call `cartStore.invalidateResolutionForStore()`

## Configuration

### Settings (wp_options):
- `wpp_enabled` - Enable/disable feature
- `wpp_fee_amount` - Priority fee amount (added to shipping)
- `wpp_fee_label` - Fee label for display
- `wpp_checkbox_label` - Checkbox text
- `wpp_description` - Help text under checkbox
- `wpp_section_title` - Section heading
- `wpp_allowed_user_roles` - Which roles can use
- `wpp_allow_guests` - Allow guest checkout

### Session Data:
- `priority_processing` (bool) - Current priority state

### Order Meta:
- `_priority_processing` - 'yes' if priority order
- `_requires_express_shipping` - Flag for shipping plugins
- `_priority_fee_amount` - Amount that was added
- `_priority_service_level` - 'express'

## Development Notes

### Adding New Shipping Plugin Support
No code changes needed! The `woocommerce_package_rates` hook works with all shipping plugins automatically.

### Debugging
Enable WordPress debug logging and search for:
- `WPP:` prefix for general logs
- `WPP AJAX:` for AJAX operations
- `WPP Blocks:` for block checkout events

### Testing Checklist
- [ ] Classic checkout works
- [ ] Block checkout works
- [ ] Checkbox appears for allowed users
- [ ] Checkbox hidden for non-allowed users
- [ ] Fee added to shipping on check
- [ ] Fee removed from shipping on uncheck
- [ ] Shipping methods stay visible
- [ ] Order saves priority meta
- [ ] Guest checkout works (if enabled)
- [ ] Multiple user roles work

## Future Enhancements
- Add option to show fee breakdown in shipping label
- Support for multiple shipping packages
- Per-product priority settings
- Time-based priority (rush delivery by date)
- Priority level tiers (standard, express, overnight)
