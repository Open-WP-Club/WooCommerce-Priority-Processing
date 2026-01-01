# Testing Guide - Priority Fee in Shipping Cost

## Quick Test Checklist

### 1. Basic Functionality
- [ ] Priority checkbox appears on checkout page
- [ ] Checking the box increases shipping cost (not separate fee)
- [ ] Unchecking the box decreases shipping cost back to normal
- [ ] No separate "Priority Fee" line item appears
- [ ] Label shows "(+$X.XX added to shipping)"

### 2. Shipping Methods Test

#### Test with Single Shipping Method
1. Set up only "Flat Rate" shipping ($10.00)
2. Go to checkout
3. Note shipping cost: Should show $10.00
4. Check priority processing box
5. Verify shipping cost changes to $15.00 (if fee is $5)
6. Verify NO separate fee line appears
7. Uncheck the box
8. Verify shipping returns to $10.00

#### Test with Multiple Shipping Methods
1. Set up multiple methods:
   - Flat Rate: $10.00
   - Express: $20.00
   - Local Pickup: $0.00
2. Go to checkout
3. Note all shipping costs
4. Check priority processing box
5. Verify ALL shipping methods increase by fee amount:
   - Flat Rate: $10.00 → $15.00
   - Express: $20.00 → $25.00
   - Local Pickup: $0.00 → $5.00
6. Change selected shipping method
7. Verify fee is applied to whichever method is selected

#### Test with Free Shipping
1. Set up Free Shipping (with conditions met)
2. Go to checkout
3. Note shipping shows: $0.00
4. Check priority processing box
5. Verify Free Shipping becomes: $5.00 (the priority fee)
6. This is correct - priority shipping is NOT free

### 3. Block Checkout Testing

#### Setup
1. Go to Pages → Checkout
2. Ensure you're using the Checkout Block (not shortcode)
3. Or check if you have `<!-- wp:woocommerce/checkout -->` in the page

#### Test Flow
1. Add product to cart
2. Go to checkout (block-based)
3. Verify priority checkbox appears
4. Check the priority box
5. **Important:** Watch the shipping methods section
   - Methods should remain visible ✓
   - Costs should update to include fee ✓
   - Methods should NOT disappear ✗
6. Select different shipping methods
7. Verify fee applies to each one
8. Uncheck priority box
9. Verify shipping costs return to normal

### 4. Classic Checkout Testing

#### If Using Shortcode Checkout
1. Use `[woocommerce_checkout]` shortcode
2. Follow same test as Block Checkout
3. Verify checkbox works
4. Verify shipping costs update
5. Verify no JavaScript errors in console

### 5. Third-Party Shipping Plugins

#### FedEx / UPS / USPS Testing
1. Configure your carrier plugin
2. Go to checkout
3. Note carrier rates (e.g., FedEx Ground: $12.50)
4. Check priority processing box
5. Verify rate increases: FedEx Ground: $17.50
6. Complete test order
7. Check order admin for correct shipping cost

#### Common Plugins to Test:
- WooCommerce FedEx Shipping
- WooCommerce UPS Shipping
- WooCommerce USPS Shipping
- Table Rate Shipping
- Distance-based shipping
- Any custom shipping plugins

### 6. User Permission Testing

#### Test Different User Roles
1. Test as Guest (if enabled)
   - [ ] Checkbox appears
   - [ ] Can check/uncheck
   - [ ] Fee applies correctly

2. Test as Customer
   - [ ] Checkbox appears (if role is allowed)
   - [ ] Works correctly

3. Test as other roles
   - [ ] Respects role permissions in settings

#### Admin Settings to Verify
1. Go to WooCommerce → Priority Processing
2. Check "Allowed User Roles" setting
3. Check "Allow Guests" setting
4. Test that non-allowed users DON'T see the checkbox

### 7. Order Admin Verification

After placing a priority order:

1. Go to WooCommerce → Orders
2. Open the priority order
3. Check order meta data (you may need a plugin to view)
4. Verify these meta fields exist:
   - `_priority_processing`: 'yes'
   - `_requires_express_shipping`: 'yes'
   - `_priority_fee_amount`: [amount]
   - `_priority_service_level`: 'express'

5. Check shipping line item:
   - Should show increased cost (base + priority fee)
   - Should NOT show separate priority fee line

### 8. Debug Log Testing

#### Enable Debug Logging
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

#### Check Logs
1. Go to `wp-content/debug.log`
2. Clear the file
3. Do a checkout with priority processing
4. Check for these log entries:

```
WPP: Priority session set to true before shipping calculation
WPP: Adding 5 to 3 shipping rates
WPP: Modified rate 'Flat Rate': 10 -> 15
WPP AJAX: Priority set to true, cart recalculated
WPP: Blocks checkout integration registered successfully
```

### 9. AJAX Testing

#### Using Browser Console
1. Open checkout page
2. Open browser Developer Tools (F12)
3. Go to Console tab
4. Check priority processing box
5. Watch for AJAX requests
6. Verify no JavaScript errors
7. Check Network tab for:
   - Request to `admin-ajax.php`
   - Action: `wpp_update_priority`
   - Response: `{"success":true,...}`

### 10. Edge Cases

#### Test These Scenarios:
1. **No shipping methods available**
   - What happens: No rates to modify, no error
   - Expected: Checkbox still works, no crashes

2. **Cart total $0**
   - Free products + free shipping
   - Priority box checked
   - Expected: Only priority fee charged (in shipping)

3. **Multiple cart packages**
   - Virtual + physical products
   - Expected: Fee added to physical package shipping

4. **Changing shipping address**
   - Change country/state mid-checkout
   - Priority box already checked
   - Expected: New shipping rates include priority fee

5. **Coupon codes**
   - Apply free shipping coupon
   - Check priority box
   - Expected: Shipping becomes priority fee amount

6. **Browser back button**
   - Complete checkout
   - Hit browser back
   - Expected: Checkbox state preserved

## Performance Testing

### Page Load Speed
- [ ] Checkout page loads in reasonable time
- [ ] No significant delay when checking/unchecking
- [ ] Shipping methods update within 1-2 seconds

### Server Load
- [ ] Check debug.log for excessive queries
- [ ] Verify session updates happen only once per change
- [ ] No infinite loops in shipping calculation

## Compatibility Testing

### WordPress Versions
- [ ] Works with WordPress 6.4+
- [ ] Works with WordPress 6.9

### WooCommerce Versions
- [ ] Works with WooCommerce 8.0+
- [ ] Works with WooCommerce 9.0+
- [ ] Works with latest WooCommerce

### PHP Versions
- [ ] PHP 7.4
- [ ] PHP 8.0
- [ ] PHP 8.1
- [ ] PHP 8.2

### Themes
- [ ] Storefront theme
- [ ] Astra theme
- [ ] Your current theme
- [ ] Block-based themes (FSE)

## Troubleshooting Common Issues

### Issue: Shipping methods disappear when checking box
**Solution:**
- Check if you're on latest version (1.4.1+)
- Clear site cache
- Check for conflicting plugins
- Review debug.log for errors

### Issue: Fee not being added to shipping
**Solution:**
- Verify plugin is enabled in settings
- Check user has permission to use feature
- Ensure fee amount is > 0 in settings
- Check debug.log for session update confirmation

### Issue: Block checkout doesn't work
**Solution:**
- Verify using Checkout Block (not shortcode)
- Check JavaScript console for errors
- Clear browser cache
- Verify WooCommerce Blocks is up to date

### Issue: Fee added twice
**Solution:**
- Should NOT happen in v1.4.1+
- If it does, report as bug
- Check if another plugin is also adding fees

## Success Criteria

All tests pass when:
- ✅ Checkbox appears and works smoothly
- ✅ Fee is added to shipping (not separate line)
- ✅ Shipping methods never disappear
- ✅ Works with both classic and block checkout
- ✅ Works with all shipping plugins
- ✅ No JavaScript errors
- ✅ No PHP errors in logs
- ✅ Orders save priority meta correctly
- ✅ User permissions respected
- ✅ Performance is good (< 2 sec updates)
