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
   - **Webhook Secret**: Optional (legacy field). Webhook signatures are validated with your Paystack Secret Key
   - **Test Mode**: Toggle between test and live mode

## Getting Paystack API Keys

1. Create a Paystack account at [paystack.com](https://paystack.com)
2. Navigate to Settings > API Keys & Webhooks
3. Copy your Public Key and Secret Key
4. For webhooks, navigate to Settings > Webhooks and set the URL

## Webhook Setup

To enable automatic payment confirmation:

1. Get your webhook URL from below (use one of these):
   - **Callback URL (user redirect)**: `https://yourdomain.com/paystack/callback`
   - **Webhook URL (server event)**: `https://yourdomain.com/webhook/paystack`

2. Go to Paystack Dashboard:
   - Navigate to **Settings > API Keys & Webhooks**
   - Set **Callback URL** to `https://yourdomain.com/paystack/callback`
   - Set **Webhook URL** to `https://yourdomain.com/webhook/paystack`

3. Subscribe to events:
   - In Paystack Dashboard, select events to listen to
   - Recommended: `charge.success` event

4. Add the webhook secret to your plugin settings:
   - Admin Panel > Payments > Paystack
   - Optional: keep this empty (signature verification uses your Secret Key)

## API Endpoints

- `POST /paystack/initialize` - Start payment transaction
- `POST /paystack/verify` - Verify payment completion  
- `GET /paystack/callback` - Callback endpoint for Paystack user redirect
- `POST /webhook/paystack` - **Webhook endpoint** (use this for Webhook URL in Paystack)
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
