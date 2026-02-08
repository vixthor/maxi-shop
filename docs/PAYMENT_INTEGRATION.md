# Payment Integration Guide (Stripe + Paystack)

This guide shows how to integrate Stripe Checkout and Paystack into this Laravel/InnoShop app. It includes environment setup, routes, controller methods, a simple payments table, and verification/troubleshooting steps on Windows.


## 1) Prerequisites
- Laravel app running locally
- PHP extensions for your DB and cURL enabled
- Database configured and reachable (see "Verify DB" below)


## 2) Environment variables
Add these to your `.env` (use test/sandbox keys first):

```ini
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

PAYSTACK_PUBLIC=pk_test_xxx
PAYSTACK_SECRET=sk_test_xxx
PAYSTACK_CALLBACK_URL=${APP_URL}/pay/paystack/callback
```

Clear cached config after editing `.env`:

```powershell
php artisan config:clear
php artisan optimize:clear
```


## 3) Install SDK
Install Stripe PHP SDK:

```powershell
composer require stripe/stripe-php
```


## 4) Optional: payments table migration
Create a migration that records payment attempts and results. Adjust fields as needed.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->index();
            $table->string('provider');              // stripe | paystack
            $table->string('reference')->unique();   // session id / paystack ref
            $table->integer('amount');               // smallest unit
            $table->string('currency', 10)->default('NGN');
            $table->string('status')->index();       // init|paid|failed|canceled
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('payments');
    }
};
```

Run migrations:

```powershell
php artisan migrate
```


## 5) Routes to add (routes/web.php)
```php
use InnoShop\Front\Controllers\PaymentController;

// existing success/fail/cancel already in PaymentController
Route::get('/payment/success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('/payment/fail', [PaymentController::class, 'fail'])->name('payment.fail');
Route::get('/payment/cancel', [PaymentController::class, 'cancel'])->name('payment.cancel');

// Stripe
Route::post('/pay/stripe/checkout', [PaymentController::class, 'stripeCheckout'])->name('pay.stripe.checkout');
Route::post('/webhook/stripe', [PaymentController::class, 'stripeWebhook'])->name('webhook.stripe');

// Paystack
Route::post('/pay/paystack/init', [PaymentController::class, 'paystackInit'])->name('pay.paystack.init');
Route::get('/pay/paystack/callback', [PaymentController::class, 'paystackCallback'])->name('pay.paystack.callback');
```


## 6) Controller methods (extend `innopacks/front/src/Controllers/PaymentController.php`)
Below adds 4 methods for Stripe and Paystack. Adjust order fields to your domain (`$order->total`, `$order->currency`, `$order->email`, etc.).

```php
<?php

namespace InnoShop\Front\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InnoShop\Common\Repositories\OrderRepo;

class PaymentController extends Controller
{
    // ...existing success/fail/cancel...

    // --- Stripe Checkout ---
    public function stripeCheckout(Request $request)
    {
        $orderNumber = $request->get('order_number');
        $order = $orderNumber ? OrderRepo::getInstance()->builder(['number' => $orderNumber])->first() : null;
        abort_unless($order, 404);

        $currency = strtolower($order->currency ?? 'ngn');
        $amountCents = (int) round(($order->total ?? 0) * 100);

        \Stripe\Stripe::setApiKey(config('services.stripe.secret', env('STRIPE_SECRET')));

        $session = \Stripe\Checkout\Session::create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'client_reference_id' => $orderNumber,
            'metadata' => ['order_number' => $orderNumber],
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => ['name' => 'Order ' . $orderNumber],
                    'unit_amount' => $amountCents,
                ],
                'quantity' => 1,
            ]],
            'success_url' => route('payment.success', ['order_number' => $orderNumber]),
            'cancel_url' => route('payment.cancel', ['order_number' => $orderNumber]),
        ]);

        DB::table('payments')->updateOrInsert(
            ['reference' => $session->id],
            [
                'order_number' => $orderNumber,
                'provider' => 'stripe',
                'amount' => $amountCents,
                'currency' => strtoupper($currency),
                'status' => 'init',
                'payload' => json_encode(['checkout' => $session]),
                'updated_at' => now(), 'created_at' => now(),
            ]
        );

        return redirect($session->url);
    }

    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');
        $secret = env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig, $secret);
        } catch (\Throwable $e) {
            return response('Invalid signature', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $orderNumber = $session->metadata->order_number ?? $session->client_reference_id ?? null;

            if ($orderNumber) {
                $order = OrderRepo::getInstance()->builder(['number' => $orderNumber])->first();
                if ($order) {
                    // TODO: mark order as paid in your domain
                    // OrderRepo::getInstance()->update($order, ['status' => 'paid']);
                }

                DB::table('payments')->where('reference', $session->id)->update([
                    'status' => 'paid',
                    'payload' => json_encode(['webhook' => $session]),
                    'updated_at' => now(),
                ]);
            }
        }

        return response('ok', 200);
    }

    // --- Paystack Redirect Flow ---
    public function paystackInit(Request $request)
    {
        $orderNumber = $request->get('order_number');
        $order = $orderNumber ? OrderRepo::getInstance()->builder(['number' => $orderNumber])->first() : null;
        abort_unless($order, 404);

        $amountKobo = (int) round(($order->total ?? 0) * 100); // NGN -> kobo
        $email = $order->email ?? ($order->customer->email ?? 'customer@example.com');
        $reference = 'PSK_' . $orderNumber . '_' . uniqid();

        $res = Http::withToken(env('PAYSTACK_SECRET'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $email,
                'amount' => $amountKobo,
                'currency' => 'NGN',
                'reference' => $reference,
                'callback_url' => env('PAYSTACK_CALLBACK_URL', route('pay.paystack.callback')),
                'metadata' => ['order_number' => $orderNumber],
            ]);

        abort_unless($res->ok() && ($res->json('status') === true), 500, 'Paystack init failed');

        $authUrl = $res->json('data.authorization_url');

        DB::table('payments')->updateOrInsert(
            ['reference' => $reference],
            [
                'order_number' => $orderNumber,
                'provider' => 'paystack',
                'amount' => $amountKobo,
                'currency' => 'NGN',
                'status' => 'init',
                'payload' => json_encode(['init' => $res->json()]),
                'updated_at' => now(), 'created_at' => now(),
            ]
        );

        return redirect($authUrl);
    }

    public function paystackCallback(Request $request)
    {
        $reference = $request->get('reference');
        abort_unless($reference, 400);

        $verify = Http::withToken(env('PAYSTACK_SECRET'))
            ->get("https://api.paystack.co/transaction/verify/{$reference}");

        if (! $verify->ok() || $verify->json('data.status') !== 'success') {
            return redirect()->route('payment.fail')->with('error', 'Payment failed');
        }

        $metadata = $verify->json('data.metadata') ?? [];
        $orderNumber = $metadata['order_number'] ?? null;

        if ($orderNumber) {
            $order = OrderRepo::getInstance()->builder(['number' => $orderNumber])->first();
            if ($order) {
                // TODO: mark order as paid in your domain
                // OrderRepo::getInstance()->update($order, ['status' => 'paid']);
            }
        }

        DB::table('payments')->where('reference', $reference)->update([
            'status' => 'paid',
            'payload' => json_encode(['verify' => $verify->json()]),
            'updated_at' => now(),
        ]);

        return redirect()->route('payment.success', ['order_number' => $orderNumber]);
    }
}
```


## 7) Configure services (optional helper)
Add to `config/services.php`:

```php
'stripe' => [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
],
```


## 8) How to use
- Stripe: POST to `/pay/stripe/checkout` with `order_number`. You’ll be redirected to Stripe Checkout.
- Paystack: POST to `/pay/paystack/init` with `order_number`. You’ll be redirected to Paystack payment page.
- Success/Cancel/Fail pages: already provided by `PaymentController::success|cancel|fail` (ensure your views exist).


## 9) Webhooks
- Set Stripe webhook endpoint to: `https://your-domain.com/webhook/stripe`
- Subscribe to `checkout.session.completed`
- Put the signing secret in `.env` as `STRIPE_WEBHOOK_SECRET`


## 10) Verify DB connection (handy checks)
Check your DB config in `.env`:

```ini
DB_CONNECTION=mysql   # or pgsql/sqlsrv/sqlite
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

Quick checks:

```powershell
php artisan migrate:status
php artisan tinker
```
Inside Tinker:
```php
DB::connection()->getPdo();
DB::select('select 1 as ok');
```


## 11) Handle pending migrations

```powershell
php artisan migrate
php artisan migrate:status
```

Common options:

```powershell
php artisan migrate --pretend
php artisan migrate --force         # production only
php artisan migrate:rollback        # undo last batch
php artisan migrate:fresh --seed    # destructive: recreates tables
```


## 12) Troubleshooting
- 403/Access is denied writing `bootstrap/cache/packages.php` on Windows:
  1) Stop `php artisan serve` and any PHP processes.
  2) Remove read-only and grant permissions:
     ```powershell
     attrib -R "C:\\Users\\TEGA\\Documents\\work2\\innoshop\\bootstrap\\cache\\*.*" /S
     icacls "C:\\Users\\TEGA\\Documents\\work2\\innoshop\\bootstrap\\cache" /grant $env:USERNAME:(OI)(CI)F /T
     ```
  3) Clear and regenerate:
     ```powershell
     del /Q "C:\\Users\\TEGA\\Documents\\work2\\innoshop\\bootstrap\\cache\\packages.php"
     del /Q "C:\\Users\\TEGA\\Documents\\work2\\innoshop\\bootstrap\\cache\\services.php"
     composer dump-autoload
     php artisan optimize:clear
     php artisan package:discover
     ```
  4) If needed, run terminal as Administrator and exclude the folder from antivirus scanning.

- Payments not marking order as paid:
  - Ensure you implement the domain-specific status update (see TODO comments).
  - Check `storage/logs/laravel.log` for SQLSTATE or webhook validation errors.
  - Verify `.env` keys and that config cache is cleared.

- Paystack test cards: https://paystack.com/docs/payments/test-payments
- Stripe test cards: https://stripe.com/docs/testing


## 13) Security notes
- Always use HTTPS in production.
- Validate amounts and currency server-side using your order in DB, not from client input.
- Verify webhook signatures. Do not trust unauthenticated callbacks.
- Store only what you need; do not log sensitive PAN/CVV (never touch raw card data using these hosted pages).


## 14) Next steps
- Add provider selection UI (Stripe or Paystack) on the checkout page.
- Add eventing to update order status and send emails on successful payment.
- Add admin views for the `payments` table to audit transactions.
