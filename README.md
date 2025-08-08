# WooCommerce Priority Processing

A WordPress plugin that adds a priority processing and express shipping option to WooCommerce checkout, allowing customers to pay an additional fee for faster order handling.

## Features

- **Priority Processing Option**: Add a checkbox at checkout for priority processing
- **Configurable Fee**: Set custom fee amounts with automatic cart updates
- **Flexible Display**: Customizable labels, descriptions, and section titles
- **Admin Integration**: Settings panel integrated with WooCommerce settings
- **Order Management**: Visual indicators for priority orders in admin
- **Modern Compatibility**: Supports both classic and block-based checkout
- **HPOS Ready**: Compatible with WooCommerce High-Performance Order Storage

## Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+

## Installation

1. Upload the plugin files to `/wp-content/plugins/woocommerce-priority-processing/`
2. Activate the plugin through the WordPress admin
3. Go to **WooCommerce > Priority Processing** to configure settings

## Configuration

Navigate to **WooCommerce > Priority Processing** to customize:

### Basic Settings

- **Enable/Disable**: Toggle the priority processing feature
- **Fee Amount**: Set the additional charge (e.g., 5.00)
- **Section Title**: Customize the checkout section heading

### Display Options

- **Checkbox Label**: Text shown next to the checkbox
- **Description**: Help text displayed below the option
- **Fee Label**: How the fee appears in cart/order totals

## How It Works

### For Customers

1. During checkout, customers see the priority processing option
2. When selected, the fee is immediately added to their order total
3. Order proceeds with priority status for faster handling

### For Store Owners

- Priority orders are marked with ⚡ lightning bolt indicators
- Easy identification in order lists and individual order pages
- All settings managed through familiar WooCommerce interface

## Technical Features

- **AJAX Updates**: Smooth checkout experience without page reloads
- **Session Management**: Reliable state handling across checkout process
- **Security**: Proper nonce verification and data sanitization
- **Compatibility**: Works with popular themes and checkout customizations
- **Performance**: Lightweight implementation with minimal overhead

## Admin Features

- **Visual Indicators**: Priority orders clearly marked with ⚡ symbol
- **Order Integration**: Priority status shown in order details
- **Settings Integration**: Native WooCommerce settings interface
- **Fallback Support**: Separate admin page if needed

## License

Licensed under the Apache License 2.0. See [LICENSE](LICENSE) for details.

## Author

Created by [OpenWPClub.com](https://openwpclub.com)
