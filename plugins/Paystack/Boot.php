<?php
/**
 * Copyright (c) Since 2024 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Plugin\Paystack;

use Illuminate\Support\Facades\Route;
use Plugin\Paystack\Services\PaystackService;

class Boot
{
    /**
     * Initialize Paystack payment plugin
     * https://paystack.com/docs
     *
     * @throws \Exception
     */
    public function init(): void
    {
        // Register routes
        $this->registerRoutes();

        // Mobile payment hook
        listen_hook_filter('service.payment.mobile_pay.data', function ($data) {
            $order = $data['order'];
            if ($order->payment_method_code != 'paystack') {
                return $data;
            }

            $data['params'] = (new PaystackService($order))->getMobilePaymentData();

            return $data;
        });
    }

    /**
     * Register plugin routes
     */
    private function registerRoutes(): void
    {
        Route::middleware(['web'])->group(function () {
            require plugin_path('Paystack/Routes/front.php');
        });
    }
}
