# WooCommerce Priority Processing

A WordPress plugin that adds a priority processing and express shipping option to WooCommerce checkout, allowing customers to pay an additional fee for faster order handling.

## Features

- Add priority processing checkbox at checkout
- Configurable additional fee amount
- Customizable labels and descriptions
- Admin settings panel
- Order admin indicators
- HPOS (High-Performance Order Storage) compatible
- Block-based checkout support

## Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+

## Installation

1. Upload the plugin files to `/wp-content/plugins/woocommerce-priority-processing/`
2. Activate the plugin through WordPress admin
3. Go to **WooCommerce > Priority Processing** to configure settings

## Configuration

Navigate to **WooCommerce > Priority Processing** to set up:

- **Enable/Disable**: Toggle the feature on/off
- **Fee Amount**: Set the additional charge (e.g., 5.00)
- **Checkbox Label**: Customize the checkout option text
- **Description**: Add helpful text below the checkbox
- **Fee Label**: How the fee appears in cart totals

## Usage

Once configured, customers will see the priority processing option during checkout. When selected:

- Additional fee is added to order total
- Order is marked with priority status
- Admin can easily identify priority orders

## Admin Features

- Priority orders are clearly marked in order admin with a ⚡ indicator
- Customizable fee amounts and messaging
- Session-based cart updates for smooth UX

## Technical Details

- Uses WooCommerce sessions for state management
- AJAX-powered checkout updates
- Compatible with both classic and block-based checkout
- Follows WordPress coding standards
- Includes proper sanitization and security measures

## License

Licensed under the Apache License 2.0. See [LICENSE](LICENSE) for details.

## Author

Created by [OpenWPClub.com](https://openwpclub.com)
