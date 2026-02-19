<?php
/**
 * Copyright (c) Since 2024 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

use Illuminate\Support\Facades\Route;
use Plugin\Paystack\Controllers\PaystackController;

// Frontend payment endpoints
Route::post('/paystack/initialize', [PaystackController::class, 'initialize'])->name('paystack_initialize');
Route::post('/paystack/verify', [PaystackController::class, 'verify'])->name('paystack_verify');

// Webhook/Callback endpoints - callback via GET, webhook via POST
Route::post('/webhook/paystack', [PaystackController::class, 'webhook'])->name('paystack_webhook');
Route::post('/paystack/webhook', [PaystackController::class, 'webhook'])->name('paystack_webhook_alt');
Route::get('/paystack/callback', [PaystackController::class, 'callback'])->name('paystack_callback');
