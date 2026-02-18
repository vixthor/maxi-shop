# Paystack Plugin - Implementation Guide

## Payment Flow Comparison

### Stripe Flow (Current Implementation)
```
1. User clicks button
   ↓
2. Frontend → POST /stripe/checkout-session
   ↓
3. Backend returns: { session_id, checkout_url }
   ↓
4. Frontend redirects to Stripe with session_id
   ↓
5. User pays on Stripe
   ↓
6. Stripe redirects to success_url (configured in session)
   ↓
7. Webhook from Stripe confirms payment
```

### Paystack Flow (Your Setup)
```
1. User clicks button
   ↓
2. Frontend → POST /paystack/initialize
   ↓
3. Backend returns: { authorization_url }
   ↓
4. Frontend redirects to authorization_url
   ↓
5. User pays on Paystack
   ↓
6. Paystack redirects to redirect_url from Dashboard
   ↓
7. GET /webhook/paystack (or /paystack/callback) handles redirect
   ↓
8. Webhook from Paystack (POST) confirms payment
```

## Key Differences

### Stripe
- Sets `success_url` and `cancel_url` **per transaction** in API
- Automatically redirects user to those URLs after payment

### Paystack  
- Sets `redirect_url` **once in Dashboard** (Settings > API Keys & Webhooks)
- Redirects ALL users to that single URL regardless of which transaction

## Required Configuration

### Paystack Dashboard Settings (CRITICAL)
1. Go to **Settings > API Keys & Webhooks**
2. Find **Live Redirect URL** (or **Redirect URL** for test)
3. Set it to: `https://maxziobrand.com/paystack/callback`
4. Find **Webhook URL** 
5. Set it to: `https://maxziobrand.com/webhook/paystack`
6. Save

This is why you weren't getting redirected - Paystack needs to know where to send the user!

## Paystack API Reference

### Initialize Transaction (POST)
```
https://api.paystack.co/transaction/initialize
Headers: Authorization: Bearer {SECRET_KEY}
Body: {
  "amount": 50000,              // Amount in kobo (NGN: 50000 = ₦500)
  "email": "customer@example.com",
  "reference": "unique_ref_123",
  "metadata": {}
}
Response: {
  "status": true,
  "data": {
    "authorization_url": "https://checkout.paystack.com/...",
    "access_code": "...",
    "reference": "unique_ref_123"
  }
}
```

### Verify Transaction (GET)
```
https://api.paystack.co/transaction/verify/{reference}
Headers: Authorization: Bearer {SECRET_KEY}
Response: {
  "status": true,
  "data": {
    "status": "success",  // or "failed", "pending"
    "reference": "unique_ref_123",
    "amount": 50000,
    "customer": {...}
  }
}
```

### Webhook Event
```
POST to your webhook URL when payment status changes
Headers: x-paystack-signature: HMAC-SHA512 hash
Body: {
  "event": "charge.success",  // or "charge.failed", "invoice.create", etc
  "data": {
    "status": "success",
    "reference": "unique_ref_123",
    "amount": 50000,
    "metadata": {
      "order_number": "2026021857904"
    }
  }
}
```

## Testing on Localhost

### With ngrok
```bash
# Terminal 1: Start Laravel
php artisan serve

# Terminal 2: Create tunnel
ngrok http 8000

# Get your URL: https://abc123.ngrok.io

# Update Paystack Dashboard
# Redirect URL: https://abc123.ngrok.io/paystack/callback
# Webhook URL: https://abc123.ngrok.io/webhook/paystack
```

### Test Payment
- Card: `4111 1111 1111 1111`
- Expiry: Any future date  
- CVV: Any 3 digits

## Files in Plugin

- **Boot.php** - Registers routes and hooks
- **Controllers/PaystackController.php** - Handles initialize, verify, callback, webhook
- **Services/PaystackService.php** - API calls to Paystack
- **Routes/front.php** - API endpoints
- **Views/payment.blade.php** - Frontend button
- **fields.php** - Admin configuration fields
- **Lang/** - English and Chinese translations
