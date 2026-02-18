# Paystack Payment Plugin

A complete Paystack payment gateway integration for InnoShop e-commerce platform.

## Features

- Secure payment processing via Paystack
- Support for multiple currencies
- Webhook integration for payment confirmation
- Mobile payment support
- Test and Live mode support
- Full order tracking and payment history

## Installation

1. Place this plugin in the `plugins/Paystack` directory
2. Run `composer install` to install dependencies:
   ```
   cd plugins/Paystack
   composer install
   ```
3. Enable the plugin in the InnoShop admin panel
4. Configure your Paystack API keys in the plugin settings

## Configuration

To configure the Paystack payment plugin:

1. Go to Admin Panel > Payments > Paystack
2. Enter your Paystack API credentials:
   - **Public Key**: Found in your Paystack Dashboard
   - **Secret Key**: Found in your Paystack Dashboard
   - **Webhook Secret**: Optional, for validating incoming webhooks
   - **Test Mode**: Toggle between test and live mode

## Getting Paystack API Keys

1. Create a Paystack account at [paystack.com](https://paystack.com)
2. Navigate to Settings > API Keys & Webhooks
3. Copy your Public Key and Secret Key
4. For webhooks, navigate to Settings > Webhooks and set the URL

## Webhook Setup

To enable automatic payment confirmation:

1. Get your webhook URL from below (use one of these):
   - **Test Callback URL**: `https://yourdomain.com/webhook/paystack`
   - **Test Webhook URL**: `https://yourdomain.com/paystack/webhook`

2. Go to Paystack Dashboard:
   - Navigate to **Settings > API Keys & Webhooks**
   - Scroll to **Test Webhooks** section
   - Enter your webhook URL in the **Test Callback URL** field
   - (Optional) Copy the webhook secret and add to plugin settings

3. Subscribe to events:
   - In Paystack Dashboard, select events to listen to
   - Recommended: `charge.success` event

4. Add the webhook secret to your plugin settings:
   - Admin Panel > Payments > Paystack
   - Paste the webhook secret (if using signature verification)

## API Endpoints

- `POST /paystack/initialize` - Start payment transaction
- `POST /paystack/verify` - Verify payment completion  
- `POST /webhook/paystack` - **Webhook endpoint** (use this for "Test Callback URL" in Paystack)
- `POST /paystack/webhook` - Alternative webhook endpoint

## Requirements

- PHP 7.4 or higher
- Laravel 9.0 or higher
- Paystack PHP SDK (^2.3)
- InnoShop Core

## Support

For issues or questions regarding this plugin, please contact:
- Email: team@innoshop.com
- Website: https://www.innoshop.com

## License

Open Software License (OSL 3.0)
